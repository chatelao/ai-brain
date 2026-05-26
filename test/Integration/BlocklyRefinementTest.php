<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Database;
use App\SandboxService;
use App\GitHubService;
use App\NotificationService;
use App\Logger;
use App\Task;
use App\Project;
use App\User;
use App\WebhookHandler;
use PDO;
use Tests\TestDatabaseTrait;

class BlocklyRefinementTest extends TestCase
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
            'task_logs', 'tasks', 'projects', 'users', 'notifications', 'user_github_accounts'
        ];
        foreach ($tables as $table) {
            $this->pdo->exec("DROP TABLE IF EXISTS $table");
        }

        $pk = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
        $timestamp = $driver === 'sqlite' ? 'DATETIME DEFAULT CURRENT_TIMESTAMP' : 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP';
        $textType = $driver === 'sqlite' ? 'TEXT' : 'LONGTEXT';

        $this->pdo->exec("CREATE TABLE users (
            user_id $pk,
            username VARCHAR(255) NOT NULL,
            blockly_config $textType,
            jules_api_key VARCHAR(255),
            jules_quota_updated_at DATETIME
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
            blockly_config $textType,
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
            github_pr_data TEXT,
            pr_url VARCHAR(255),
            jules_session_id VARCHAR(255),
            jules_status VARCHAR(50),
            jules_url VARCHAR(255),
            github_repo VARCHAR(255),
            autorepeat_remaining INT DEFAULT 0,
            last_synced_at $timestamp,
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

        Logger::resetInstance();
        new Logger($this->db);
    }

    public function testGetTaskStatusBlock()
    {
        $this->pdo->exec("INSERT INTO users (user_id, username) VALUES (1, 'testuser')");
        $this->pdo->exec("INSERT INTO user_github_accounts (github_account_id, user_id, github_username) VALUES (1, 1, 'testuser')");
        $this->pdo->exec("INSERT INTO projects (project_id, user_id, github_repo, github_account_id) VALUES (1, 1, 'owner/repo', 1)");
        $this->pdo->exec("INSERT INTO tasks (task_id, user_id, project_id, issue_number, title, github_data, status, github_repo) VALUES (1, 1, 1, 101, 'Test Task', '{\"labels\":[]}', 'checking', 'owner/repo')");

        $jsCode = "
            const status = getTaskStatus();
            notify('Status is ' + status);
        ";

        $this->notificationService->expects($this->once())
            ->method('notify')
            ->with(1, 'blockly_action', 'Blockly Notification', 'Status is checking');

        $result = $this->sandboxService->execute(1, 1, $jsCode);
        $this->assertTrue($result['success']);
    }

    public function testIsPrDraftBlock()
    {
        $this->pdo->exec("INSERT INTO users (user_id, username) VALUES (1, 'testuser')");
        $this->pdo->exec("INSERT INTO user_github_accounts (github_account_id, user_id, github_username) VALUES (1, 1, 'testuser')");
        $this->pdo->exec("INSERT INTO projects (project_id, user_id, github_repo, github_account_id) VALUES (1, 1, 'owner/repo', 1)");

        // Task with Draft PR
        $prData = json_encode(['draft' => true]);
        $this->pdo->exec("INSERT INTO tasks (task_id, user_id, project_id, issue_number, title, github_data, github_pr_data, status, github_repo) VALUES (1, 1, 1, 101, 'Draft Task', '{\"labels\":[]}', '$prData', 'checking', 'owner/repo')");

        $jsCode = "
            if (isPrDraft()) {
                notify('PR is a draft');
            } else {
                notify('PR is not a draft');
            }
        ";

        $this->notificationService->expects($this->once())
            ->method('notify')
            ->with(1, 'blockly_action', 'Blockly Notification', 'PR is a draft');

        $result = $this->sandboxService->execute(1, 1, $jsCode);
        $this->assertTrue($result['success']);
    }

    public function testStatusChangedTrigger()
    {
        $this->pdo->exec("INSERT INTO users (user_id, username) VALUES (1, 'testuser')");
        $this->pdo->exec("INSERT INTO user_github_accounts (github_account_id, user_id, github_username) VALUES (1, 1, 'testuser')");
        $this->pdo->exec("INSERT INTO projects (project_id, user_id, github_repo, github_account_id) VALUES (1, 1, 'owner/repo', 1)");

        $prUrl = 'https://github.com/owner/repo/pull/42';
        $this->pdo->exec("INSERT INTO tasks (task_id, user_id, project_id, issue_number, title, pr_url, status, github_repo, github_data) VALUES (1, 1, 1, 101, 'Test Task', '$prUrl', 'checking', 'owner/repo', '{\"labels\":[]}')");

        // Set up Blockly automation for STATUS_CHANGED
        $jsAutomation = "onEvent('STATUS_CHANGED', (event) => { notify('Status changed to ' + getTaskStatus()); });";
        $blocklyConfig = json_encode([
            'js' => $jsAutomation
        ]);

        $stmt = $this->pdo->prepare("UPDATE projects SET blockly_config = ? WHERE project_id = 1");
        $stmt->execute([$blocklyConfig]);

        $handler = new WebhookHandler($this->db);

        $event = [
            'action' => 'completed',
            'check_suite' => [
                'status' => 'completed',
                'conclusion' => 'success',
                'pull_requests' => [['url' => 'https://api.github.com/repos/owner/repo/pulls/42']]
            ],
            'repository' => ['full_name' => 'owner/repo']
        ];

        // Mock GitHub calls inside handleCheckSuite
        $this->githubService->method('extractPrNumber')->willReturn(42);
        $this->githubService->method('getPullRequest')->willReturn(['head' => ['sha' => 'abc'], 'mergeable_state' => 'clean']);
        $this->githubService->method('getCheckSuites')->willReturn(['check_suites' => [['status' => 'completed', 'conclusion' => 'success']]]);
        $this->githubService->method('getCombinedStatus')->willReturn(['statuses' => []]);

        $this->notificationService->expects($this->atLeastOnce())
            ->method('notify')
            ->with($this->anything(), $this->anything(), $this->anything(), $this->callback(function($msg) {
                return strpos($msg, 'Status changed to ready') !== false || strpos($msg, 'PR checks') !== false;
            }));

        $project = [
            'user_id' => 1,
            'project_id' => 1,
            'github_repo' => 'owner/repo',
            'blockly_config' => $blocklyConfig
        ];

        $handler->handle($project, $event, $this->githubService, $this->notificationService, null, $this->sandboxService, 'check_suite');

        // Verify the status was actually updated in DB
        $stmt = $this->pdo->query("SELECT status FROM tasks WHERE task_id = 1");
        $this->assertEquals('ready', $stmt->fetchColumn());
    }
}
