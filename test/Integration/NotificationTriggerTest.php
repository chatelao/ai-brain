<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Database;
use App\WebhookHandler;
use App\NotificationService;
use App\Task;
use App\GitHubService;
use App\JulesService;
use App\TelegramService;
use App\NotificationChannelInterface;
use PDO;
use Tests\TestDatabaseTrait;

class NotificationTriggerTest extends TestCase
{
    use TestDatabaseTrait;

    private $pdo;
    private $db;
    private $notificationService;
    private $webhookHandler;
    private $taskModel;

    protected function setUp(): void
    {
        $this->pdo = $this->getTestPdo();
        $this->setUpDatabase();

        $this->db = $this->createMock(Database::class);
        $this->db->method('getConnection')->willReturn($this->pdo);

        $this->notificationService = new NotificationService($this->db);
        $this->webhookHandler = new WebhookHandler($this->db);
        $this->taskModel = new Task($this->db);
    }

    private function setUpDatabase(): void
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $pk = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';

        $this->pdo->exec("DROP TABLE IF EXISTS task_external_peers");
        $this->pdo->exec("DROP TABLE IF EXISTS notifications");
        $this->pdo->exec("DROP TABLE IF EXISTS user_notification_settings");
        $this->pdo->exec("DROP TABLE IF EXISTS project_notification_settings");
        $this->pdo->exec("DROP TABLE IF EXISTS user_event_notification_settings");
        $this->pdo->exec("DROP TABLE IF EXISTS project_status_notification_settings");
        $this->pdo->exec("DROP TABLE IF EXISTS task_notification_settings");
        $this->pdo->exec("DROP TABLE IF EXISTS tasks");
        $this->pdo->exec("DROP TABLE IF EXISTS projects");
        $this->pdo->exec("DROP TABLE IF EXISTS users");
        $this->pdo->exec("DROP TABLE IF EXISTS user_telegram_accounts");

        $this->pdo->exec("CREATE TABLE users (
            user_id $pk,
            google_id VARCHAR(255) UNIQUE,
            name VARCHAR(255),
            email VARCHAR(255),
            jules_api_key VARCHAR(255),
            telegram_bot_token VARCHAR(255),
            jules_quota_usage INT DEFAULT 0,
            jules_quota_limit INT DEFAULT 0,
            jules_quota_updated_at DATETIME
        )");

        $this->pdo->exec("CREATE TABLE user_telegram_accounts (
            user_id INT PRIMARY KEY,
            telegram_chat_id VARCHAR(255)
        )");

        $this->pdo->exec("CREATE TABLE projects (
            project_id $pk,
            user_id INT,
            github_repo VARCHAR(255),
            github_token VARCHAR(255),
            webhook_secret VARCHAR(255)
        )");

        $this->pdo->exec("CREATE TABLE tasks (
            task_id $pk,
            user_id INT,
            project_id INT,
            issue_number INT,
            title VARCHAR(255),
            body TEXT,
            status VARCHAR(50) DEFAULT 'CREATED',
            substatus VARCHAR(50),
            github_state VARCHAR(20) DEFAULT 'open',
            github_data TEXT,
            jules_session_id VARCHAR(255),
            jules_status VARCHAR(50),
            jules_url VARCHAR(255),
            pr_url VARCHAR(255),
            last_synced_at DATETIME,
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

        $this->pdo->exec("CREATE TABLE task_notification_settings (
            task_id INT PRIMARY KEY,
            is_muted BOOLEAN DEFAULT FALSE
        )");

        $this->pdo->exec("CREATE TABLE task_external_peers (
            peer_id $pk,
            task_id INT NOT NULL,
            source VARCHAR(50) NOT NULL,
            id VARCHAR(255) NOT NULL,
            state VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(task_id, source, id)
        )");

        $this->pdo->exec("INSERT INTO users (user_id, google_id, name, email, jules_api_key) VALUES (1, 'google-1', 'User 1', 'user1@example.com', 'key-123')");
        $this->pdo->exec("INSERT INTO projects (project_id, user_id, github_repo) VALUES (1, 1, 'owner/repo')");
    }

    public function testWebhookIssueOpenedTriggersNotification()
    {
        $project = ['user_id' => 1, 'project_id' => 1, 'github_repo' => 'owner/repo'];
        $event = [
            'action' => 'opened',
            'issue' => [
                'number' => 101,
                'title' => 'New Issue',
                'body' => 'Issue body',
                'html_url' => 'https://github.com/owner/repo/issues/101',
                'state' => 'open'
            ]
        ];

        $this->webhookHandler->handle($project, $event, null, $this->notificationService);

        $stmt = $this->pdo->query("SELECT * FROM notifications WHERE user_id = 1 AND type = 'github_issue'");
        $notification = $stmt->fetch();

        $this->assertNotFalse($notification);
        $this->assertStringContainsString('Issue Opened: #101', $notification['title']);
    }

    public function testWebhookPrOpenedTriggersNotification()
    {
        $project = ['user_id' => 1, 'project_id' => 1, 'github_repo' => 'owner/repo'];
        $event = [
            'action' => 'opened',
            'pull_request' => [
                'number' => 202,
                'title' => 'New PR',
                'html_url' => 'https://github.com/owner/repo/pull/202',
                'merged' => false
            ]
        ];

        $this->webhookHandler->handle($project, $event, null, $this->notificationService);

        $stmt = $this->pdo->query("SELECT * FROM notifications WHERE user_id = 1 AND type = 'github_pr'");
        $notification = $stmt->fetch();

        $this->assertNotFalse($notification);
        $this->assertStringContainsString('PR Opened: #202', $notification['title']);
    }

    public function testRefreshJulesStatusTriggersNotificationOnStatusChange()
    {
        $userId = 1;
        $this->pdo->exec("INSERT INTO tasks (user_id, project_id, issue_number, title, status, substatus, jules_session_id, jules_status, github_data)
                          VALUES (1, 1, 303, 'Task 303', 'PROCESSING', 'ANALYZING', 'sess-123', 'researching', '{\"assignee\":{\"login\":\"jules\"}}')");
        $taskId = $this->pdo->lastInsertId();

        $githubService = $this->createMock(GitHubService::class);
        $julesService = $this->createMock(JulesService::class);

        $julesService->method('fetchSessionStatus')->willReturn([
            'status' => 'coding',
            'url' => 'https://jules.google.com/task/abc'
        ]);

        $this->taskModel->refreshJulesStatus($userId, $githubService, $julesService, $this->notificationService, $taskId);

        $stmt = $this->pdo->query("SELECT * FROM notifications WHERE user_id = 1 AND type = 'task_status' ORDER BY created_at DESC LIMIT 1");
        $notification = $stmt->fetch();

        $this->assertNotFalse($notification);
        $this->assertStringContainsString('Task Update: #303', $notification['title']);
        $this->assertStringContainsString('status changed to PROCESSING (EXECUTING)', $notification['message']);
    }

    public function testNotificationDispatchesToTelegram()
    {
        $userId = 1;
        $this->pdo->exec("UPDATE users SET telegram_bot_token = 'bot-token' WHERE user_id = $userId");
        $this->pdo->exec("INSERT INTO user_telegram_accounts (user_id, telegram_chat_id) VALUES ($userId, '12345')");
        $this->pdo->exec("INSERT INTO user_notification_settings (user_id, channel, is_enabled) VALUES ($userId, 'telegram', 1)");

        $telegramService = $this->createMock(TelegramService::class);
        $mockWithToken = $this->createMock(TelegramService::class);

        $telegramService->method('withToken')->with('bot-token')->willReturn($mockWithToken);
        $mockWithToken->expects($this->once())
            ->method('sendMessage')
            ->with('12345', $this->anything())
            ->willReturn(true);

        // We need to inject the mocked TelegramService into the NotificationService's channel
        $userModel = new \App\User($this->db);
        $telegramChannel = new \App\TelegramChannelHandler($userModel, $telegramService);
        $this->notificationService->registerChannel('telegram', $telegramChannel);

        $this->notificationService->notify($userId, 'test_event', 'Test Title', 'Test Message');
    }

    public function testNotificationRespectsProjectDisabledType()
    {
        $userId = 1;
        $projectId = 1;
        $this->pdo->exec("INSERT INTO project_notification_settings (project_id, notification_type, is_enabled) VALUES ($projectId, 'github_issue', 0)");

        $project = ['user_id' => $userId, 'project_id' => $projectId, 'github_repo' => 'owner/repo'];
        $event = [
            'action' => 'opened',
            'issue' => [
                'number' => 404,
                'title' => 'Hidden Issue',
                'body' => '...',
                'html_url' => '...',
                'state' => 'open'
            ]
        ];

        $this->webhookHandler->handle($project, $event, null, $this->notificationService);

        $stmt = $this->pdo->query("SELECT COUNT(*) FROM notifications WHERE type = 'github_issue'");
        $this->assertEquals(0, $stmt->fetchColumn());
    }

    public function testNotificationRespectsStatusDisabledForBroadcastButKeepsInbox()
    {
        $userId = 1;
        $projectId = 1;
        // Disable 'EXECUTING' status broadcast
        $this->pdo->exec("INSERT INTO project_status_notification_settings (project_id, status, is_enabled) VALUES ($projectId, 'EXECUTING', 0)");

        // Enable a mock channel AND in_app
        $this->pdo->exec("INSERT INTO user_notification_settings (user_id, channel, is_enabled) VALUES ($userId, 'mock_channel', 1)");
        $this->pdo->exec("INSERT INTO user_notification_settings (user_id, channel, is_enabled) VALUES ($userId, 'in_app', 1)");
        $mockChannel = $this->createMock(NotificationChannelInterface::class);
        $this->notificationService->registerChannel('mock_channel', $mockChannel);

        // Broadcast SHOULD NOT happen
        $mockChannel->expects($this->never())->method('send');

        $this->pdo->exec("INSERT INTO tasks (user_id, project_id, issue_number, title, status, substatus, jules_session_id, jules_status, github_data)
                          VALUES (1, 1, 505, 'Task 505', 'PROCESSING', 'ANALYZING', 'sess-456', 'researching', '{\"assignee\":{\"login\":\"jules\"}}')");
        $taskId = $this->pdo->lastInsertId();

        $githubService = $this->createMock(GitHubService::class);
        $julesService = $this->createMock(JulesService::class);

        $julesService->method('fetchSessionStatus')->willReturn([
            'status' => 'coding',
            'url' => '...'
        ]);

        $this->taskModel->refreshJulesStatus($userId, $githubService, $julesService, $this->notificationService, $taskId);

        // Notification SHOULD still be in the inbox
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM notifications WHERE type = 'task_status'");
        $this->assertEquals(1, $stmt->fetchColumn());
    }

    public function testNotificationNormalizationForHyphenatedStatus()
    {
        $userId = 1;
        $projectId = 1;

        // Disable 'PROCESSING' status broadcast
        $this->pdo->exec("INSERT INTO project_status_notification_settings (project_id, status, is_enabled) VALUES ($projectId, 'PROCESSING', 0)");

        // Enable a mock channel
        $this->pdo->exec("INSERT INTO user_notification_settings (user_id, channel, is_enabled) VALUES ($userId, 'mock_channel', 1)");
        $mockChannel = $this->createMock(NotificationChannelInterface::class);
        $this->notificationService->registerChannel('mock_channel', $mockChannel);

        // EXPECTATION: Broadcast SHOULD NOT happen because 'PROCESSING' is disabled
        $mockChannel->expects($this->never())->method('send');

        // Trigger notification with status 'PROCESSING'
        $this->notificationService->notify($userId, 'task_status', 'Title', 'Message', [
            'project_id' => $projectId,
            'status' => 'PROCESSING'
        ]);
    }
}
