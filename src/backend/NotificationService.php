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

        // 5. Check if broadcast is enabled for this status (if it's a task_status event)
        $shouldBroadcast = true;
        if ($type === 'task_status' && isset($data['project_id']) && isset($data['status'])) {
            $shouldBroadcast = $this->isStatusBroadcastEnabled((int)$data['project_id'], $data['status']);
        }

        if (!$shouldBroadcast) {
            return true;
        }

        // 6. Dispatch to other enabled channels
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

            $channel = $this->getChannelInstance($channelName);

            if ($channel) {
                $channel->send($notification);
            }
        }

        return true;
    }

    public function sendTestNotification(int $userId): array
    {
        $enabledChannels = $this->getEnabledChannels($userId);
        if (empty($enabledChannels)) {
            return ['status' => 'error', 'message' => 'No notification channels enabled.'];
        }

        $results = [];
        $notification = [
            'notification_id' => null,
            'user_id' => $userId,
            'type' => 'test_broadcast',
            'title' => 'Test Broadcast',
            'message' => 'This is a test notification from your Agent Control settings.',
            'data' => ['is_test' => true]
        ];

        foreach ($enabledChannels as $channelName) {
            if ($channelName === 'in_app') {
                $results['in_app'] = true; // Handled by frontend for test
                continue;
            }

            $channel = $this->getChannelInstance($channelName);
            if ($channel) {
                $results[$channelName] = $channel->send($notification);
            } else {
                $results[$channelName] = false;
            }
        }

        return ['status' => 'success', 'channels' => $results];
    }

    private function getChannelInstance(string $channelName): ?NotificationChannelInterface
    {
        $channel = $this->channels[$channelName] ?? null;

        // Auto-register common channels if not explicitly registered
        if (!$channel) {
            if ($channelName === 'telegram') {
                $userModel = new User($this->db);
                $telegramService = new TelegramService();
                $channel = new TelegramChannelHandler($userModel, $telegramService);
                $this->registerChannel('telegram', $channel);
            } elseif ($channelName === 'browser') {
                $channel = new BrowserChannelHandler();
                $this->registerChannel('browser', $channel);
            }
        }

        return $channel;
    }

    public function getTaskSettings(int $taskId): array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT is_muted FROM task_notification_settings WHERE task_id = ?"
        );
        $stmt->execute([$taskId]);
        $result = $stmt->fetch();
        return [
            'is_muted' => $result ? (bool)$result['is_muted'] : false
        ];
    }

    public function updateTaskSettings(int $taskId, bool $isMuted): bool
    {
        $db = $this->db->getConnection();
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $db->prepare(
                "INSERT INTO task_notification_settings (task_id, is_muted)
                 VALUES (?, ?)
                 ON CONFLICT(task_id) DO UPDATE SET is_muted = excluded.is_muted"
            );
        } else {
            $stmt = $db->prepare(
                "INSERT INTO task_notification_settings (task_id, is_muted)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE is_muted = VALUES(is_muted)"
            );
        }
        return $stmt->execute([$taskId, (int)$isMuted]);
    }

    public function getProjectSettings(int $projectId): array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT notification_type, is_enabled FROM project_notification_settings WHERE project_id = ?"
        );
        $stmt->execute([$projectId]);
        $rows = $stmt->fetchAll();

        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['notification_type']] = (bool)$row['is_enabled'];
        }

        // Default settings if not present
        $types = ['github_issue', 'github_pr', 'task_status', 'agent_event'];
        foreach ($types as $type) {
            if (!isset($settings[$type])) {
                $settings[$type] = true;
            }
        }

        return $settings;
    }

    public function updateProjectSettings(int $projectId, array $settings): bool
    {
        $db = $this->db->getConnection();
        $db->beginTransaction();

        try {
            foreach ($settings as $type => $enabled) {
                $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
                if ($driver === 'sqlite') {
                    $stmt = $db->prepare(
                        "INSERT INTO project_notification_settings (project_id, notification_type, is_enabled)
                         VALUES (?, ?, ?)
                         ON CONFLICT(project_id, notification_type) DO UPDATE SET is_enabled = excluded.is_enabled"
                    );
                } else {
                    $stmt = $db->prepare(
                        "INSERT INTO project_notification_settings (project_id, notification_type, is_enabled)
                         VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled)"
                    );
                }
                $stmt->execute([$projectId, $type, (int)$enabled]);
            }
            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            return false;
        }
    }

    public function getStatusSettings(int $projectId): array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT status, is_enabled FROM project_status_notification_settings WHERE project_id = ?"
        );
        $stmt->execute([$projectId]);
        $rows = $stmt->fetchAll();

        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['status']] = (bool)$row['is_enabled'];
        }

        // Default settings if not present
        $statuses = ['researching', 'planning', 'coding', 'testing', 'in_progress', 'implemented', 'completed', 'failed_jules', 'failed_pr'];
        foreach ($statuses as $status) {
            if (!isset($settings[$status])) {
                $settings[$status] = true;
            }
        }

        return $settings;
    }

    public function updateStatusSettings(int $projectId, array $settings): bool
    {
        $db = $this->db->getConnection();
        $db->beginTransaction();

        try {
            foreach ($settings as $status => $enabled) {
                $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
                if ($driver === 'sqlite') {
                    $stmt = $db->prepare(
                        "INSERT INTO project_status_notification_settings (project_id, status, is_enabled)
                         VALUES (?, ?, ?)
                         ON CONFLICT(project_id, status) DO UPDATE SET is_enabled = excluded.is_enabled"
                    );
                } else {
                    $stmt = $db->prepare(
                        "INSERT INTO project_status_notification_settings (project_id, status, is_enabled)
                         VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled)"
                    );
                }
                $stmt->execute([$projectId, $status, (int)$enabled]);
            }
            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            return false;
        }
    }

    private function isTaskMuted(int $taskId): bool
    {
        $settings = $this->getTaskSettings($taskId);
        return $settings['is_muted'];
    }

    private function isProjectTypeEnabled(int $projectId, string $type): bool
    {
        $settings = $this->getProjectSettings($projectId);
        return $settings[$type] ?? true;
    }

    private function isStatusBroadcastEnabled(int $projectId, string $status): bool
    {
        $settings = $this->getStatusSettings($projectId);
        return $settings[$status] ?? true;
    }

    private function persistNotification(int $userId, string $type, string $title, string $message, array $data): int
    {
        $projectId = isset($data['project_id']) ? (int)$data['project_id'] : null;

        $stmt = $this->db->getConnection()->prepare(
            "INSERT INTO notifications (user_id, project_id, type, title, message, data) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $userId,
            $projectId,
            $type,
            $title,
            $message,
            json_encode($data)
        ]);
        $notificationId = (int)$this->db->getConnection()->lastInsertId();

        if ($projectId !== null) {
            $this->limitNotificationsByProject($projectId);
        }

        return $notificationId;
    }

    private function limitNotificationsByProject(int $projectId): void
    {
        $db = $this->db->getConnection();
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

        // We use a very large number for the LIMIT to effectively mean "all remaining"
        // MySQL: 18446744073709551615, SQLite: -1
        $limit = ($driver === 'sqlite') ? -1 : '18446744073709551615';

        $stmt = $db->prepare(
            "SELECT notification_id FROM notifications
             WHERE project_id = ?
             ORDER BY created_at DESC, notification_id DESC
             LIMIT $limit OFFSET 25"
        );

        $stmt->execute([$projectId]);
        $idsToDelete = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($idsToDelete)) {
            $placeholders = implode(',', array_fill(0, count($idsToDelete), '?'));
            $stmt = $db->prepare("DELETE FROM notifications WHERE notification_id IN ($placeholders)");
            $stmt->execute($idsToDelete);
        }
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
        if (!isset($settings['browser'])) {
            $settings['browser'] = false;
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
