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

    public function notify(int $userId, string $type, string $title, string $message, array $data = [], array $actions = []): bool
    {
        // 0. Auto-populate github_repo if project_id is present
        if (isset($data['project_id']) && !isset($data['github_repo'])) {
            $stmt = $this->db->getConnection()->prepare("SELECT github_repo FROM projects WHERE project_id = ?");
            $stmt->execute([$data['project_id']]);
            $repo = $stmt->fetchColumn();
            if ($repo) {
                $data['github_repo'] = $repo;
            }
        }

        // 1. Check task-level mute (if task_id is provided)
        if (isset($data['task_id']) && $this->isTaskMuted($data['task_id'])) {
            return false;
        }

        // 2. Check for status-based filtering
        if (isset($data['status'])) {
            $status = $data['status'];

            // 2.1 Check global user status settings
            if (!$this->isUserStatusEnabled($userId, $status)) {
                return false;
            }

            // 2.2 Check project-level status settings
            if (isset($data['project_id'])) {
                if (!$this->isStatusNotificationEnabled((int)$data['project_id'], $status)) {
                    return false;
                }
            }
        } else {
            // Fallback for legacy types or system notifications without status
            if (!$this->isUserTypeEnabled($userId, $type)) {
                return false;
            }
        }

        // 4. Get enabled channels
        $enabledChannels = $this->getEnabledChannels($userId);
        if (empty($enabledChannels)) {
            return false;
        }

        $notificationId = null;

        // 5. Persist to 'notifications' table if 'in_app' channel is enabled
        if (in_array('in_app', $enabledChannels)) {
            $notificationId = $this->persistNotification($userId, $type, $title, $message, $data);
        }

        // 6. Determine if we should broadcast to external channels (Telegram, Browser)
        $shouldBroadcast = true;

        // 6.1 Only system-triggered events are broadcasted
        // If is_system is explicitly set to false, it's a human action -> no broadcast
        if (!isset($data['is_system']) || $data['is_system'] === false) {
            $shouldBroadcast = false;
        }

        if (!$shouldBroadcast) {
            return true;
        }

        // 7. Dispatch to other enabled channels
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
                $channel->send($notification, $actions);
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

    /**
     * @deprecated Use getStatusSettings instead
     */
    public function getProjectSettings(int $projectId): array
    {
        return [];
    }

    /**
     * @deprecated Use updateStatusSettings instead
     */
    public function updateProjectSettings(int $projectId, array $settings): bool
    {
        return true;
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
        $statuses = [
            Task::STATUS_CREATED,
            Task::STATUS_ANALYZING,
            Task::STATUS_PLANNING,
            Task::STATUS_EXECUTING,
            Task::STATUS_VERIFYING,
            Task::STATUS_IMPLEMENTED,
            Task::STATUS_CHECKING,
            Task::STATUS_READY,
            Task::STATUS_FINISHED,
            Task::STATUS_FAILED_JULES,
            Task::STATUS_FAILED_PR
        ];

        // Actionable states default to broadcast enabled
        $broadcastByDefault = [
            Task::STATUS_READY,
            Task::STATUS_FAILED_JULES,
            Task::STATUS_FAILED_PR
        ];

        foreach ($statuses as $status) {
            $normalizedStatus = str_replace('-', '_', $status);
            if (!isset($settings[$normalizedStatus])) {
                $settings[$normalizedStatus] = in_array($status, $broadcastByDefault);
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

    private function isUserTypeEnabled(int $userId, string $type): bool
    {
        $settings = $this->getUserEventSettings($userId);
        return $settings[$type] ?? true;
    }

    private function isUserUnifiedStateEnabled(int $userId, string $unifiedState): bool
    {
        $settings = $this->getUserEventSettings($userId);
        return $settings[$unifiedState] ?? true;
    }

    private function isUserStatusEnabled(int $userId, string $status): bool
    {
        $normalizedStatus = str_replace('-', '_', $status);
        $settings = $this->getUserEventSettings($userId);

        // 1. If granular setting exists, it takes precedence
        if (isset($settings[$normalizedStatus])) {
            return $settings[$normalizedStatus];
        }

        // 2. Fallback to unified state setting
        $unifiedState = Task::getUnifiedState($status);
        return $settings[$unifiedState] ?? true;
    }

    /**
     * @deprecated
     */
    private function isProjectTypeEnabled(int $projectId, string $type): bool
    {
        return true;
    }

    private function isStatusNotificationEnabled(int $projectId, string $status): bool
    {
        $normalizedStatus = str_replace('-', '_', $status);
        $settings = $this->getStatusSettings($projectId);
        return $settings[$normalizedStatus] ?? true;
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
        $settings = $this->getUserSettings($userId);
        $enabled = [];
        foreach ($settings as $channel => $isEnabled) {
            if ($isEnabled) {
                $enabled[] = $channel;
            }
        }
        return $enabled;
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
            "SELECT n.*, p.github_repo
             FROM notifications n
             LEFT JOIN projects p ON n.project_id = p.project_id
             WHERE n.user_id = ?
             ORDER BY n.created_at DESC, n.notification_id DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$userId, $limit, $offset]);
        return $stmt->fetchAll();
    }

    public function getLatestUnread(int $userId, int $limit = 5): array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT n.*, p.github_repo
             FROM notifications n
             LEFT JOIN projects p ON n.project_id = p.project_id
             WHERE n.user_id = ? AND n.is_read = 0
             ORDER BY n.created_at DESC, n.notification_id DESC
             LIMIT ?"
        );
        $stmt->execute([$userId, $limit]);
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

    public function getUserEventSettings(int $userId): array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT notification_type, is_enabled FROM user_event_notification_settings WHERE user_id = ?"
        );
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll();

        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['notification_type']] = (bool)$row['is_enabled'];
        }

        // Unified states
        $unifiedStates = [
            Task::UNIFIED_CREATED,
            Task::UNIFIED_PROCESSING,
            Task::UNIFIED_READY,
            Task::UNIFIED_FINISHED,
            Task::UNIFIED_FAILED
        ];

        foreach ($unifiedStates as $state) {
            if (!isset($settings[$state])) {
                $settings[$state] = true;
            }
        }

        // Granular statuses
        $granularStatuses = [
            Task::STATUS_CREATED,
            Task::STATUS_ANALYZING,
            Task::STATUS_PLANNING,
            Task::STATUS_EXECUTING,
            Task::STATUS_VERIFYING,
            Task::STATUS_IMPLEMENTED,
            Task::STATUS_CHECKING,
            Task::STATUS_READY,
            Task::STATUS_FINISHED,
            Task::STATUS_FAILED_JULES,
            Task::STATUS_FAILED_PR
        ];

        foreach ($granularStatuses as $status) {
            $normalizedStatus = str_replace('-', '_', $status);
            if (!isset($settings[$normalizedStatus])) {
                // If the unified state is set, use that as default
                $unifiedState = Task::getUnifiedState($status);
                $settings[$normalizedStatus] = $settings[$unifiedState] ?? true;
            }
        }

        return $settings;
    }

    public function updateUserEventSettings(int $userId, array $settings): bool
    {
        $db = $this->db->getConnection();
        $db->beginTransaction();

        try {
            foreach ($settings as $type => $enabled) {
                $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
                if ($driver === 'sqlite') {
                    $stmt = $db->prepare(
                        "INSERT INTO user_event_notification_settings (user_id, notification_type, is_enabled)
                         VALUES (?, ?, ?)
                         ON CONFLICT(user_id, notification_type) DO UPDATE SET is_enabled = excluded.is_enabled"
                    );
                } else {
                    $stmt = $db->prepare(
                        "INSERT INTO user_event_notification_settings (user_id, notification_type, is_enabled)
                         VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled)"
                    );
                }
                $stmt->execute([$userId, $type, (int)$enabled]);
            }
            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            return false;
        }
    }
}
