<?php

namespace App;

use PDO;

class NotificationService
{
    private array $channels = [];

    public function __construct(private Database $db)
    {
    }

    public function registerChannel(string $name, NotificationChannelInterface $channel): void
    {
        $this->channels[$name] = $channel;
    }

    public function notify(int $userId, string $type, string $title, string $message, array $data = []): bool
    {
        // 1. Check task-level mute (if task_id is provided)
        if (isset($data['task_id']) && $this->isTaskMuted($data['task_id'])) {
            return false;
        }

        // 2. Check project-level settings (if project_id is provided)
        if (isset($data['project_id']) && !$this->isProjectTypeEnabled($data['project_id'], $type)) {
            return false;
        }

        // 3. Get enabled channels
        $enabledChannels = $this->getEnabledChannels($userId);
        if (empty($enabledChannels)) {
            return false;
        }

        $notificationId = null;

        // 4. Persist to 'notifications' table if 'in_app' channel is enabled
        if (in_array('in_app', $enabledChannels)) {
            $notificationId = $this->persistNotification($userId, $type, $title, $message, $data);
        }

        // 5. Dispatch to other enabled channels
        $notification = [
            'notification_id' => $notificationId,
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data
        ];

        foreach ($enabledChannels as $channelName) {
            if ($channelName === 'in_app') {
                continue;
            }

            $channel = $this->channels[$channelName] ?? null;

            // Auto-register common channels if not explicitly registered
            if (!$channel) {
                if ($channelName === 'telegram') {
                    $userModel = new User($this->db);
                    $telegramService = new TelegramService();
                    $channel = new TelegramChannelHandler($userModel, $telegramService);
                    $this->registerChannel('telegram', $channel);
                }
            }

            if ($channel) {
                $channel->send($notification);
            }
        }

        return true;
    }

    private function isTaskMuted(int $taskId): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT is_muted FROM task_notification_settings WHERE task_id = ?"
        );
        $stmt->execute([$taskId]);
        $result = $stmt->fetch();
        return $result && (bool)$result['is_muted'];
    }

    private function isProjectTypeEnabled(int $projectId, string $type): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT is_enabled FROM project_notification_settings WHERE project_id = ? AND notification_type = ?"
        );
        $stmt->execute([$projectId, $type]);
        $result = $stmt->fetch();
        // If no setting exists, default to enabled
        return $result === false || (bool)$result['is_enabled'];
    }

    private function persistNotification(int $userId, string $type, string $title, string $message, array $data): int
    {
        $stmt = $this->db->getConnection()->prepare(
            "INSERT INTO notifications (user_id, type, title, message, data) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $userId,
            $type,
            $title,
            $message,
            json_encode($data)
        ]);
        return (int)$this->db->getConnection()->lastInsertId();
    }

    private function getEnabledChannels(int $userId): array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT channel FROM user_notification_settings WHERE user_id = ? AND is_enabled = 1"
        );
        $stmt->execute([$userId]);
        $channels = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // In-app channel is always "persisted", but we only explicitly return it if it's not disabled.
        // Actually, 'in_app' should probably be in user_notification_settings too if they want to toggle it.
        // If no settings exist, default to in_app enabled.
        if (empty($channels)) {
            // Check if user has ANY settings
            $stmt = $this->db->getConnection()->prepare(
                "SELECT COUNT(*) FROM user_notification_settings WHERE user_id = ?"
            );
            $stmt->execute([$userId]);
            if ($stmt->fetchColumn() == 0) {
                return ['in_app'];
            }
        }

        return $channels;
    }

    public function markAsRead(int $notificationId): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "UPDATE notifications SET is_read = 1 WHERE notification_id = ?"
        );
        return $stmt->execute([$notificationId]);
    }

    public function getUnreadCount(int $userId): int
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0"
        );
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    public function getNotifications(int $userId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC, notification_id DESC LIMIT ? OFFSET ?"
        );
        $stmt->execute([$userId, $limit, $offset]);
        return $stmt->fetchAll();
    }

    public function markAllAsRead(int $userId): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "UPDATE notifications SET is_read = 1 WHERE user_id = ?"
        );
        return $stmt->execute([$userId]);
    }

    public function getTotalCount(int $userId): int
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT COUNT(*) FROM notifications WHERE user_id = ?"
        );
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    public function getUserSettings(int $userId): array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT channel, is_enabled FROM user_notification_settings WHERE user_id = ?"
        );
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll();

        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['channel']] = (bool)$row['is_enabled'];
        }

        // Default settings if not present
        if (!isset($settings['in_app'])) {
            $settings['in_app'] = true;
        }

        return $settings;
    }

    public function updateUserSettings(int $userId, array $settings): bool
    {
        $db = $this->db->getConnection();
        $db->beginTransaction();

        try {
            foreach ($settings as $channel => $enabled) {
                $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
                if ($driver === 'sqlite') {
                    $stmt = $db->prepare(
                        "INSERT INTO user_notification_settings (user_id, channel, is_enabled)
                         VALUES (?, ?, ?)
                         ON CONFLICT(user_id, channel) DO UPDATE SET is_enabled = excluded.is_enabled"
                    );
                } else {
                    $stmt = $db->prepare(
                        "INSERT INTO user_notification_settings (user_id, channel, is_enabled)
                         VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled)"
                    );
                }
                $stmt->execute([$userId, $channel, (int)$enabled]);
            }
            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            return false;
        }
    }
}
