<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Database;
use App\WebhookHandler;
use App\NotificationService;
use PDO;
use Tests\TestDatabaseTrait;

class UserActionNotificationTest extends TestCase
{
    use TestDatabaseTrait;

    private $pdo;
    private $db;
    private $notificationService;
    private $webhookHandler;

    protected function setUp(): void
    {
        $this->pdo = $this->getTestPdo();
        $this->setUpDatabase();

        $this->db = $this->createMock(Database::class);
        $this->db->method('getConnection')->willReturn($this->pdo);

        $this->notificationService = new NotificationService($this->db);
        $this->webhookHandler = new WebhookHandler($this->db);
    }

    private function setUpDatabase(): void
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $pk = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';

        $this->pdo->exec("DROP TABLE IF EXISTS notifications");
        $this->pdo->exec("DROP TABLE IF EXISTS user_notification_settings");
        $this->pdo->exec("DROP TABLE IF EXISTS project_notification_settings");
        $this->pdo->exec("DROP TABLE IF EXISTS user_event_notification_settings");
        $this->pdo->exec("DROP TABLE IF EXISTS project_status_notification_settings");
        $this->pdo->exec("DROP TABLE IF EXISTS task_notification_settings");
        $this->pdo->exec("DROP TABLE IF EXISTS tasks");
        $this->pdo->exec("DROP TABLE IF EXISTS projects");
        $this->pdo->exec("DROP TABLE IF EXISTS users");
        $this->pdo->exec("DROP TABLE IF EXISTS user_event_notification_settings");
        $this->pdo->exec("DROP TABLE IF EXISTS project_status_notification_settings");

        $this->pdo->exec("CREATE TABLE users (
            user_id $pk,
            google_id VARCHAR(255) UNIQUE,
            name VARCHAR(255),
            email VARCHAR(255)
        )");

        $this->pdo->exec("CREATE TABLE projects (
            project_id $pk,
            user_id INT,
            github_repo VARCHAR(255),
            webhook_secret VARCHAR(255)
        )");

        $this->pdo->exec("CREATE TABLE tasks (
            task_id $pk,
            user_id INT,
            project_id INT,
            issue_number INT,
            title VARCHAR(255),
            body TEXT,
            status VARCHAR(50) DEFAULT 'pending',
            github_state VARCHAR(20) DEFAULT 'open',
            github_data TEXT,
            UNIQUE(project_id, issue_number)
        )");

        $this->pdo->exec("CREATE TABLE notifications (
            notification_id $pk,
            user_id INT,
            project_id INT,
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

        $this->pdo->exec("CREATE TABLE user_event_notification_settings (
            user_id INT,
            notification_type VARCHAR(50),
            is_enabled BOOLEAN DEFAULT TRUE,
            PRIMARY KEY (user_id, notification_type)
        )");

        $this->pdo->exec("CREATE TABLE project_notification_settings (
            project_id INT,
            notification_type VARCHAR(50),
            is_enabled BOOLEAN DEFAULT TRUE,
            PRIMARY KEY (project_id, notification_type)
        )");

        $this->pdo->exec("CREATE TABLE project_status_notification_settings (
            project_id INT,
            status VARCHAR(50),
            is_enabled BOOLEAN DEFAULT TRUE,
            PRIMARY KEY (project_id, status)
        )");

        // Task-level notification settings (required by NotificationService)
        $this->pdo->exec("CREATE TABLE task_notification_settings (
            task_id INTEGER PRIMARY KEY,
            is_muted BOOLEAN DEFAULT FALSE
        )");

        // Performance logs (required by Logger)
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS performance_logs (
            log_id $pk,
            user_id INT,
            category VARCHAR(50),
            target VARCHAR(255),
            duration FLOAT,
            memory INT,
            status_code INT,
            error_message TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("INSERT INTO users (user_id, google_id, name, email) VALUES (1, 'google-1', 'User 1', 'user1@example.com')");
        $this->pdo->exec("INSERT INTO projects (project_id, user_id, github_repo) VALUES (1, 1, 'owner/repo')");
        // Enable CREATED notifications for user 1
        $this->pdo->exec("INSERT INTO user_event_notification_settings (user_id, notification_type, is_enabled) VALUES (1, 'CREATED', 1)");
        $this->pdo->exec("INSERT INTO user_event_notification_settings (user_id, notification_type, is_enabled) VALUES (1, 'FINISHED', 1)");
        $this->pdo->exec("INSERT INTO user_event_notification_settings (user_id, notification_type, is_enabled) VALUES (1, 'CHECKING', 1)");

        $this->pdo->exec("INSERT INTO project_status_notification_settings (project_id, status, is_enabled) VALUES (1, 'created', 1)");
        $this->pdo->exec("INSERT INTO project_status_notification_settings (project_id, status, is_enabled) VALUES (1, 'finished', 1)");
        $this->pdo->exec("INSERT INTO project_status_notification_settings (project_id, status, is_enabled) VALUES (1, 'checking', 1)");
    }

    public function testHandleSendsNotificationForUserSender()
    {
        $project = ['user_id' => 1, 'project_id' => 1, 'github_repo' => 'owner/repo'];
        $event = [
            'action' => 'opened',
            'repository' => ['full_name' => 'owner/repo'],
            'sender' => ['type' => 'User'],
            'issue' => [
                'number' => 101,
                'title' => 'Manual Issue',
                'body' => 'Issue body',
                'html_url' => 'https://github.com/owner/repo/issues/101',
                'state' => 'open'
            ]
        ];

        $this->webhookHandler->handle($project, $event, null, $this->notificationService);

        $stmt = $this->pdo->query("SELECT COUNT(*) FROM notifications");
        $this->assertEquals(1, $stmt->fetchColumn(), "Notification SHOULD be triggered for human user action.");
    }

    public function testHandleSendsNotificationForBotSender()
    {
        $project = ['user_id' => 1, 'project_id' => 1, 'github_repo' => 'owner/repo'];
        $event = [
            'action' => 'opened',
            'repository' => ['full_name' => 'owner/repo'],
            'sender' => ['type' => 'Bot'],
            'issue' => [
                'number' => 102,
                'title' => 'Bot Issue',
                'body' => 'Issue body',
                'html_url' => 'https://github.com/owner/repo/issues/102',
                'state' => 'open'
            ]
        ];

        $this->webhookHandler->handle($project, $event, null, $this->notificationService);

        $stmt = $this->pdo->query("SELECT COUNT(*) FROM notifications");
        $this->assertEquals(1, $stmt->fetchColumn(), "Notification SHOULD be triggered for non-user (Bot) action.");
    }

    public function testHandleSendsNotificationWhenSenderMissing()
    {
        $project = ['user_id' => 1, 'project_id' => 1, 'github_repo' => 'owner/repo'];
        $event = [
            'action' => 'opened',
            'repository' => ['full_name' => 'owner/repo'],
            'issue' => [
                'number' => 103,
                'title' => 'Unknown Sender Issue',
                'body' => 'Issue body',
                'html_url' => 'https://github.com/owner/repo/issues/103',
                'state' => 'open'
            ]
        ];

        $this->webhookHandler->handle($project, $event, null, $this->notificationService);

        $stmt = $this->pdo->query("SELECT COUNT(*) FROM notifications");
        $this->assertEquals(1, $stmt->fetchColumn(), "Notification SHOULD be triggered if sender information is missing (backwards compatibility).");
    }

    public function testHandlePrSendsNotificationForUserSender()
    {
        $project = ['user_id' => 1, 'project_id' => 1, 'github_repo' => 'owner/repo'];
        $event = [
            'action' => 'opened',
            'repository' => ['full_name' => 'owner/repo'],
            'sender' => ['type' => 'User'],
            'pull_request' => [
                'number' => 201,
                'title' => 'Manual PR',
                'html_url' => 'https://github.com/owner/repo/pull/201',
                'merged' => false
            ]
        ];

        $this->webhookHandler->handle($project, $event, null, $this->notificationService);

        $stmt = $this->pdo->query("SELECT COUNT(*) FROM notifications");
        $this->assertEquals(1, $stmt->fetchColumn(), "Notification SHOULD be triggered for human user PR action.");
    }
}
