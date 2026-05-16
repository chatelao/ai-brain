<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\NotificationService;
use App\NotificationChannelInterface;
use App\Database;
use PDO;
use Tests\TestDatabaseTrait;

class NotificationServiceTest extends TestCase
{
    use TestDatabaseTrait;

    private $pdo;
    private $db;
    private $notificationService;

    protected function setUp(): void
    {
        $this->pdo = $this->getTestPdo();
        $this->setUpDatabase();

        $this->db = $this->createMock(Database::class);
        $this->db->method('getConnection')->willReturn($this->pdo);

        $this->notificationService = new NotificationService($this->db);
    }

    private function setUpDatabase(): void
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        $this->pdo->exec("DROP TABLE IF EXISTS notifications");
        $this->pdo->exec("DROP TABLE IF EXISTS user_notification_settings");
        $this->pdo->exec("DROP TABLE IF EXISTS project_notification_settings");
        $this->pdo->exec("DROP TABLE IF EXISTS task_notification_settings");
        $this->pdo->exec("DROP TABLE IF EXISTS users");

        $pk = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';

        $this->pdo->exec("CREATE TABLE users (user_id $pk, google_id VARCHAR(255) UNIQUE, name VARCHAR(255), email VARCHAR(255))");

        $this->pdo->exec("CREATE TABLE notifications (
            notification_id $pk,
            user_id INT,
            type VARCHAR(50),
            title VARCHAR(255),
            message TEXT,
            data JSON,
            is_read BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE user_notification_settings (
            user_id INT,
            channel VARCHAR(20),
            is_enabled BOOLEAN DEFAULT TRUE,
            PRIMARY KEY (user_id, channel)
        )");

        $this->pdo->exec("CREATE TABLE project_notification_settings (
            project_id INT,
            notification_type VARCHAR(50),
            is_enabled BOOLEAN DEFAULT TRUE,
            PRIMARY KEY (project_id, notification_type)
        )");

        $this->pdo->exec("CREATE TABLE task_notification_settings (
            task_id INT PRIMARY KEY,
            is_muted BOOLEAN DEFAULT FALSE
        )");

        $this->pdo->exec("INSERT INTO users (user_id, google_id, name, email) VALUES (1, 'google-1', 'User 1', 'user1@example.com')");
    }

    public function testNotifyPersistsNotificationWhenInAppEnabled()
    {
        $userId = 1;
        $type = 'test_type';
        $title = 'Test Title';
        $message = 'Test Message';
        $data = ['key' => 'value'];

        // In-app is enabled by default if no settings exist
        $this->notificationService->notify($userId, $type, $title, $message, $data);

        $stmt = $this->pdo->query("SELECT * FROM notifications WHERE user_id = $userId");
        $notification = $stmt->fetch();

        $this->assertNotFalse($notification);
        $this->assertEquals($type, $notification['type']);
        $this->assertEquals($title, $notification['title']);
        $this->assertEquals($message, $notification['message']);
        $this->assertEquals(json_encode($data), $notification['data']);
    }

    public function testNotifyRespectsTaskMute()
    {
        $userId = 1;
        $taskId = 100;

        $this->pdo->exec("INSERT INTO task_notification_settings (task_id, is_muted) VALUES ($taskId, 1)");

        $result = $this->notificationService->notify($userId, 'type', 'title', 'message', ['task_id' => $taskId]);

        $this->assertFalse($result);
        $this->assertEquals(0, $this->notificationService->getUnreadCount($userId));
    }

    public function testNotifyRespectsProjectSettings()
    {
        $userId = 1;
        $projectId = 200;
        $type = 'disabled_type';

        $this->pdo->exec("INSERT INTO project_notification_settings (project_id, notification_type, is_enabled) VALUES ($projectId, '$type', 0)");

        $result = $this->notificationService->notify($userId, $type, 'title', 'message', ['project_id' => $projectId]);

        $this->assertFalse($result);
        $this->assertEquals(0, $this->notificationService->getUnreadCount($userId));
    }

    public function testNotifyDispatchesToChannels()
    {
        $userId = 1;
        $channel = $this->createMock(NotificationChannelInterface::class);
        $channel->expects($this->once())
                ->method('send')
                ->willReturn(true);

        $this->notificationService->registerChannel('test_channel', $channel);

        // Enable channel for user. Note: if we only enable 'test_channel', 'in_app' might be disabled if we have ANY settings.
        // The current implementation: if ANY settings exist, it only uses what's enabled.
        $this->pdo->exec("INSERT INTO user_notification_settings (user_id, channel, is_enabled) VALUES ($userId, 'test_channel', 1)");

        $this->notificationService->notify($userId, 'type', 'title', 'message');
    }

    public function testNotifyDoesNotPersistIfInAppDisabled()
    {
        $userId = 1;
        // Enable only some other channel, implicitly disabling in_app
        $this->pdo->exec("INSERT INTO user_notification_settings (user_id, channel, is_enabled) VALUES ($userId, 'other_channel', 1)");

        $this->notificationService->notify($userId, 'type', 'title', 'message');

        $stmt = $this->pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = $userId");
        $this->assertEquals(0, $stmt->fetchColumn());
    }

    public function testMarkAsRead()
    {
        $userId = 1;
        $this->notificationService->notify($userId, 'type', 'title', 'message');
        $notification = $this->notificationService->getNotifications($userId)[0];

        $this->assertEquals(0, $notification['is_read']);

        $this->notificationService->markAsRead($notification['notification_id']);

        $updatedNotification = $this->notificationService->getNotifications($userId)[0];
        $this->assertEquals(1, $updatedNotification['is_read']);
    }

    public function testGetUnreadCount()
    {
        $userId = 1;
        $this->notificationService->notify($userId, 'type1', 'title1', 'message1');
        $this->notificationService->notify($userId, 'type2', 'title2', 'message2');

        $this->assertEquals(2, $this->notificationService->getUnreadCount($userId));

        $notifications = $this->notificationService->getNotifications($userId);
        $this->notificationService->markAsRead($notifications[0]['notification_id']);

        $this->assertEquals(1, $this->notificationService->getUnreadCount($userId));
    }
}
