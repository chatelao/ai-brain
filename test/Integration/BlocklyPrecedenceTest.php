<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Database;
use App\WebhookHandler;
use App\SandboxService;
use App\GitHubService;
use App\NotificationService;
use App\Logger;
use App\User;
use App\Project;
use App\Task;
use PDO;
use Tests\TestDatabaseTrait;

class BlocklyPrecedenceTest extends TestCase
{
    use TestDatabaseTrait;

    private $db;
    private $pdo;
    private $githubService;
    private $notificationService;
    private $sandboxService;
    private $handler;

    protected function setUp(): void
    {
        $this->pdo = $this->getTestPdo();
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        // Reset tables
        $tables = [
            'task_logs', 'tasks', 'projects', 'users', 'user_github_accounts',
            'task_notification_settings', 'user_notification_settings',
            'project_status_notification_settings', 'user_event_notification_settings',
            'notifications'
        ];
        foreach ($tables as $table) {
            $this->pdo->exec("DROP TABLE IF EXISTS $table");
        }

        $pk = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
        $timestamp = $driver === 'sqlite' ? 'DATETIME DEFAULT CURRENT_TIMESTAMP' : 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP';

        $this->pdo->exec("CREATE TABLE users (
            user_id $pk,
            name VARCHAR(255),
            email VARCHAR(255),
            blockly_config TEXT,
            jules_api_key VARCHAR(255)
        )");

        $this->pdo->exec("CREATE TABLE user_github_accounts (
            github_account_id $pk,
            user_id INT NOT NULL,
            github_token VARCHAR(255),
            github_username VARCHAR(255)
        )");

        $this->pdo->exec("CREATE TABLE projects (
            project_id $pk,
            user_id INT NOT NULL,
            github_repo VARCHAR(255) NOT NULL,
            github_account_id INT,
            blockly_config TEXT,
            webhook_secret VARCHAR(255),
            created_at $timestamp
        )");

        $this->pdo->exec("CREATE TABLE tasks (
            task_id $pk,
            user_id INT NOT NULL,
            project_id INT NOT NULL,
            issue_number INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            body TEXT,
            status TEXT DEFAULT 'created',
            github_state VARCHAR(20) DEFAULT 'open',
            github_data TEXT,
            pr_url VARCHAR(255),
            autorepeat_remaining INT DEFAULT 0,
            jules_session_id VARCHAR(255),
            created_at $timestamp,
            updated_at $timestamp,
            UNIQUE(project_id, issue_number)
        )");

        $this->pdo->exec("CREATE TABLE task_logs (
            log_id $pk,
            task_id INT NOT NULL,
            user_id INT NOT NULL,
            message TEXT,
            level VARCHAR(20),
            created_at $timestamp
        )");

        $this->pdo->exec("CREATE TABLE task_notification_settings (
            task_id INT PRIMARY KEY,
            is_muted BOOLEAN DEFAULT 0
        )");

        $this->pdo->exec("CREATE TABLE user_event_notification_settings (
            user_id INT NOT NULL,
            notification_type VARCHAR(50) NOT NULL,
            is_enabled BOOLEAN DEFAULT 1,
            PRIMARY KEY (user_id, notification_type)
        )");

        $this->pdo->exec("CREATE TABLE project_status_notification_settings (
            project_id INT NOT NULL,
            status VARCHAR(50) NOT NULL,
            is_enabled BOOLEAN DEFAULT 1,
            PRIMARY KEY (project_id, status)
        )");

        $this->db = $this->createMock(Database::class);
        $this->db->method('getConnection')->willReturn($this->pdo);

        $this->githubService = $this->createMock(GitHubService::class);
        $this->notificationService = $this->createMock(NotificationService::class);

        $this->sandboxService = new SandboxService($this->db, $this->githubService, $this->notificationService);
        $this->handler = new WebhookHandler($this->db);

        Logger::resetInstance();
        new Logger($this->db);
    }

    public function testLocalOverridesGlobal()
    {
        // Global config: handles ISSUE_LABELED
        $globalJs = 'onEvent("ISSUE_LABELED", (event) => { notify("Global Notify"); });';
        $this->pdo->prepare("INSERT INTO users (user_id, blockly_config) VALUES (1, ?)")
            ->execute([json_encode(['js' => $globalJs])]);

        // Local config: handles ISSUE_LABELED too
        $localJs = 'onEvent("ISSUE_LABELED", (event) => { notify("Local Notify"); });';
        $this->pdo->prepare("INSERT INTO projects (project_id, user_id, github_repo, blockly_config) VALUES (1, 1, 'owner/repo', ?)")
            ->execute([json_encode(['js' => $localJs])]);

        // Task
        $this->pdo->exec("INSERT INTO tasks (task_id, user_id, project_id, issue_number, title, github_data, status) VALUES (1, 1, 1, 101, 'Test Task', '{\"labels\":[]}', 'created')");

        // Event payload
        $event = [
            'action' => 'labeled',
            'issue' => [
                'number' => 101,
                'title' => 'Test Task',
                'html_url' => 'https://github.com/owner/repo/issues/101',
                'labels' => [['name' => 'bug']]
            ],
            'repository' => [
                'full_name' => 'owner/repo'
            ]
        ];

        // Capture notifications to verify
        $capturedNotifs = [];
        $this->notificationService->method('notify')
            ->willReturnCallback(function($userId, $type, $title, $message) use (&$capturedNotifs) {
                $capturedNotifs[] = $message;
                return true;
            });

        // Explicitly trigger the handler (which will call runBlocklyAutomations)
        $project = [
            'project_id' => 1,
            'user_id' => 1,
            'github_repo' => 'owner/repo',
            'blockly_config' => json_encode(['js' => $localJs])
        ];

        $this->handler->handle($project, $event, $this->githubService, $this->notificationService, null, $this->sandboxService, 'issues');

        $this->assertContains('Local Notify', $capturedNotifs);
        $this->assertNotContains('Global Notify', $capturedNotifs);

        // Verify logs show Global was suppressed
        $stmt = $this->pdo->query("SELECT message FROM task_logs WHERE task_id = 1 AND message LIKE 'Blockly Log:%'");
        $logs = $stmt->fetchAll(PDO::FETCH_COLUMN);

        error_log("LOGS FOUND: " . print_r($logs, true));
        $this->assertContains('Blockly Log: Event ISSUE_LABELED is ignored (suppressed by Local automation).', $logs);
    }

    public function testGlobalRunsWhenDifferentEvent()
    {
        // Global config: handles ISSUE_CLOSED
        $globalJs = 'onEvent("ISSUE_CLOSED", (event) => { notify("Global Closed"); });';
        $this->pdo->prepare("INSERT INTO users (user_id, blockly_config) VALUES (1, ?)")
            ->execute([json_encode(['js' => $globalJs])]);

        // Local config: handles ISSUE_LABELED
        $localJs = 'onEvent("ISSUE_LABELED", (event) => { notify("Local Labeled"); });';
        $this->pdo->prepare("INSERT INTO projects (project_id, user_id, github_repo, blockly_config) VALUES (1, 1, 'owner/repo', ?)")
            ->execute([json_encode(['js' => $localJs])]);

        // Task
        $this->pdo->exec("INSERT INTO tasks (task_id, user_id, project_id, issue_number, title, github_data, status) VALUES (1, 1, 1, 101, 'Test Task', '{\"labels\":[]}', 'created')");

        // We'll test two separate calls to WebhookHandler::handle to simulate two events

        // 1. Labeled event
        $labeledEvent = [
            'action' => 'labeled',
            'issue' => ['number' => 101, 'title' => 'Test Task', 'html_url' => '...'],
            'repository' => ['full_name' => 'owner/repo']
        ];

        $project = [
            'project_id' => 1, 'user_id' => 1, 'github_repo' => 'owner/repo',
            'blockly_config' => json_encode(['js' => $localJs])
        ];

        $capturedNotifs = [];
        $this->notificationService->method('notify')
            ->willReturnCallback(function($userId, $type, $title, $message) use (&$capturedNotifs) {
                $capturedNotifs[] = $message;
                return true;
            });

        $this->handler->handle($project, $labeledEvent, $this->githubService, $this->notificationService, null, $this->sandboxService, 'issues');

        // 2. Closed event
        $closedEvent = [
            'action' => 'closed',
            'issue' => ['number' => 101, 'title' => 'Test Task', 'html_url' => '...'],
            'repository' => ['full_name' => 'owner/repo']
        ];

        $this->handler->handle($project, $closedEvent, $this->githubService, $this->notificationService, null, $this->sandboxService, 'issues');

        $this->assertContains('Local Labeled', $capturedNotifs);
        $this->assertContains('Global Closed', $capturedNotifs);
    }
}
