<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\NotificationService;
use App\NotificationChannelInterface;
use App\Database;
use PDO;
use Tests\TestDatabaseTrait;

class NotificationServiceExtendedTest extends TestCase
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

        $this->pdo->exec("INSERT INTO users (user_id, google_id, name, email) VALUES (1, 'google-1', 'User 1', 'user1@example.com')");
    }

    public function testGetNotificationsPagination()
    {
        $userId = 1;
        for ($i = 1; $i <= 5; $i++) {
            $this->notificationService->notify($userId, 'type', "Title $i", "Message $i");
        }

        $notifications = $this->notificationService->getNotifications($userId, 2, 0);
        $this->assertCount(2, $notifications);
        $this->assertEquals('Title 5', $notifications[0]['title']);
        $this->assertEquals('Title 4', $notifications[1]['title']);

        $notifications = $this->notificationService->getNotifications($userId, 2, 2);
        $this->assertCount(2, $notifications);
        $this->assertEquals('Title 3', $notifications[0]['title']);
        $this->assertEquals('Title 2', $notifications[1]['title']);
    }

    public function testMarkAllAsRead()
    {
        $userId = 1;
        $this->notificationService->notify($userId, 'type', 'T1', 'M1');
        $this->notificationService->notify($userId, 'type', 'T2', 'M2');

        $this->assertEquals(2, $this->notificationService->getUnreadCount($userId));

        $this->notificationService->markAllAsRead($userId);

        $this->assertEquals(0, $this->notificationService->getUnreadCount($userId));
    }

    public function testGetTotalCount()
    {
        $userId = 1;
        $this->notificationService->notify($userId, 'type', 'T1', 'M1');
        $this->notificationService->notify($userId, 'type', 'T2', 'M2');

        $this->assertEquals(2, $this->notificationService->getTotalCount($userId));
    }

    public function testGetAndUpdateUserSettings()
    {
        $userId = 1;

        // Default
        $settings = $this->notificationService->getUserSettings($userId);
        $this->assertTrue($settings['in_app']);

        // Update
        $newSettings = ['in_app' => false, 'telegram' => true];
        $this->notificationService->updateUserSettings($userId, $newSettings);

        $updatedSettings = $this->notificationService->getUserSettings($userId);
        $this->assertFalse($updatedSettings['in_app']);
        $this->assertTrue($updatedSettings['telegram']);
    }
}
