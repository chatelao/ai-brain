<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Database;
use App\SandboxService;
use App\GitHubService;
use App\NotificationService;
use App\Logger;
use PDO;
use Tests\TestDatabaseTrait;

class SandboxServiceTest extends TestCase
{
    use TestDatabaseTrait;

    private $db;
    private $pdo;
    private $githubService;
    private $notificationService;
    private $sandboxService;

    protected function setUp(): void
    {
        $this->pdo = $this->getTestPdo();
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        // Reset tables
        $tables = [
            'task_logs', 'tasks', 'projects', 'user_github_accounts',
            'task_notification_settings', 'user_notification_settings',
            'project_status_notification_settings', 'user_event_notification_settings',
            'notifications'
        ];
        foreach ($tables as $table) {
            $this->pdo->exec("DROP TABLE IF EXISTS $table");
        }

        $pk = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
        $timestamp = $driver === 'sqlite' ? 'DATETIME DEFAULT CURRENT_TIMESTAMP' : 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP';

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

        $this->pdo->exec("CREATE TABLE user_notification_settings (
            user_id INT NOT NULL,
            channel VARCHAR(50) NOT NULL,
            is_enabled BOOLEAN DEFAULT 1,
            PRIMARY KEY (user_id, channel)
        )");

        $this->pdo->exec("CREATE TABLE project_status_notification_settings (
            project_id INT NOT NULL,
            status VARCHAR(50) NOT NULL,
            is_enabled BOOLEAN DEFAULT 1,
            PRIMARY KEY (project_id, status)
        )");

        $this->pdo->exec("CREATE TABLE user_event_notification_settings (
            user_id INT NOT NULL,
            notification_type VARCHAR(50) NOT NULL,
            is_enabled BOOLEAN DEFAULT 1,
            PRIMARY KEY (user_id, notification_type)
        )");

        $this->pdo->exec("CREATE TABLE notifications (
            notification_id $pk,
            user_id INT NOT NULL,
            project_id INT,
            type VARCHAR(50),
            title VARCHAR(255),
            message TEXT,
            data TEXT,
            is_read BOOLEAN DEFAULT 0,
            created_at $timestamp
        )");

        $this->db = $this->createMock(Database::class);
        $this->db->method('getConnection')->willReturn($this->pdo);

        $this->githubService = $this->createMock(GitHubService::class);
        $this->notificationService = $this->createMock(NotificationService::class);

        $this->sandboxService = new SandboxService($this->db, $this->githubService, $this->notificationService);

        // Ensure Logger uses the test DB
        Logger::resetInstance();
        new Logger($this->db);
    }

    public function testExecuteSuccessfulScript()
    {
        // Seed data
        $this->pdo->exec("INSERT INTO user_github_accounts (github_account_id, user_id, github_username) VALUES (1, 1, 'testuser')");
        $this->pdo->exec("INSERT INTO projects (project_id, user_id, github_repo, github_account_id) VALUES (1, 1, 'owner/repo', 1)");
        $this->pdo->exec("INSERT INTO tasks (task_id, user_id, project_id, issue_number, title, github_data, status) VALUES (1, 1, 1, 101, 'Test Task', '{\"labels\":[]}', 'created')");

        $jsCode = "
            console.log('Starting execution');
            setLabel('automated');
            notify('Task is being processed');
            if (isTaskReady()) {
                merge();
            }
        ";

        $this->githubService->expects($this->once())
            ->method('addLabel')
            ->with('owner/repo', 101, 'automated');

        $this->notificationService->expects($this->once())
            ->method('notify')
            ->with(
                1,
                'blockly_action',
                'Blockly Notification',
                'Task is being processed',
                $this->callback(function($data) {
                    return $data['task_id'] === 1 && $data['project_id'] === 1;
                })
            );

        $result = $this->sandboxService->execute(1, 1, $jsCode);

        $this->assertTrue($result['success']);

        // Verify logs
        $stmt = $this->pdo->query("SELECT message FROM task_logs WHERE task_id = 1 AND message LIKE 'Blockly Log:%'");
        $logs = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('Blockly Log: Starting execution', $logs);

        $stmt = $this->pdo->query("SELECT message FROM task_logs WHERE task_id = 1 AND message LIKE 'Blockly Action [unknown]:%'");
        $actions = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('Blockly Action [unknown]: Added label \'automated\'', $actions);
    }

    public function testExecuteWithTaskReady()
    {
        // Seed data with ready status and PR URL
        $this->pdo->exec("INSERT INTO user_github_accounts (github_account_id, user_id, github_username) VALUES (1, 1, 'testuser')");
        $this->pdo->exec("INSERT INTO projects (project_id, user_id, github_repo, github_account_id) VALUES (1, 1, 'owner/repo', 1)");
        $this->pdo->exec("INSERT INTO tasks (task_id, user_id, project_id, issue_number, title, github_data, status, pr_url) VALUES (1, 1, 1, 101, 'Test Task', '{\"labels\":[]}', 'ready', 'https://github.com/owner/repo/pull/42')");

        $jsCode = "
            if (isTaskReady()) {
                merge();
            }
        ";

        $this->githubService->expects($this->once())
            ->method('extractPrNumber')
            ->with('https://github.com/owner/repo/pull/42')
            ->willReturn(42);

        $this->githubService->expects($this->once())
            ->method('mergePullRequest')
            ->with('owner/repo', 42, 'Merged via Blockly Automation');

        $result = $this->sandboxService->execute(1, 1, $jsCode);

        $this->assertTrue($result['success']);

        $stmt = $this->pdo->query("SELECT message FROM task_logs WHERE task_id = 1 AND message LIKE 'Blockly Action [unknown]:%'");
        $actions = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('Blockly Action [unknown]: Merged PR #42', $actions);
    }

    public function testExecuteScriptWithError()
    {
        $this->pdo->exec("INSERT INTO user_github_accounts (github_account_id, user_id, github_username) VALUES (1, 1, 'testuser')");
        $this->pdo->exec("INSERT INTO projects (project_id, user_id, github_repo, github_account_id) VALUES (1, 1, 'owner/repo', 1)");
        // Add dummy github_repo to tasks for getTargetUrl in Logger
        $this->pdo->exec("ALTER TABLE tasks ADD COLUMN github_repo VARCHAR(255)");
        $this->pdo->exec("INSERT INTO tasks (task_id, user_id, project_id, issue_number, title, github_data, github_repo) VALUES (1, 1, 1, 101, 'Test Task', '{\"labels\":[]}', 'owner/repo')");

        $jsCode = "throw new Error('Boom!');";

        $result = $this->sandboxService->execute(1, 1, $jsCode);

        $this->assertFalse($result['success']);

        $stmt = $this->pdo->query("SELECT message FROM task_logs WHERE task_id = 1 AND level = 'error'");
        $errorLogs = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $this->assertNotEmpty($errorLogs);
        $this->assertStringContainsString('Boom!', $errorLogs[0]);
    }
}
