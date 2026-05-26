<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Database;
use App\WebhookHandler;
use App\SandboxService;
use App\GitHubService;
use App\NotificationService;
use App\JulesService;
use App\Logger;
use App\Task;
use App\Project;
use App\User;
use PDO;
use Tests\TestDatabaseTrait;

class BlocklyWorkflowPhase6Test extends TestCase
{
    use TestDatabaseTrait;

    private $db;
    private $pdo;
    private $githubService;
    private $notificationService;
    private $julesService;
    private $sandboxService;
    private $webhookHandler;

    protected function setUp(): void
    {
        $this->pdo = $this->getTestPdo();
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        // Reset tables
        $tables = [
            'task_logs', 'tasks', 'projects', 'users', 'user_github_accounts',
            'notifications', 'task_notification_settings', 'user_event_notification_settings',
            'project_status_notification_settings', 'user_notification_settings'
        ];
        foreach ($tables as $table) {
            $this->pdo->exec("DROP TABLE IF EXISTS $table");
        }

        $pk = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
        $timestamp = $driver === 'sqlite' ? 'DATETIME DEFAULT CURRENT_TIMESTAMP' : 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP';

        $this->pdo->exec("CREATE TABLE users (
            user_id $pk,
            email VARCHAR(255) UNIQUE,
            blockly_config TEXT,
            jules_api_key VARCHAR(255),
            jules_quota_usage INT DEFAULT 0,
            jules_quota_limit INT DEFAULT 0,
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
            github_pr_data TEXT,
            pr_url VARCHAR(255),
            jules_session_id VARCHAR(255),
            jules_status VARCHAR(50),
            jules_url TEXT,
            autorepeat_remaining INT DEFAULT 0,
            github_repo VARCHAR(255),
            last_synced_at DATETIME,
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

        $this->pdo->exec("CREATE TABLE user_notification_settings (
            user_id INT NOT NULL,
            channel VARCHAR(50) NOT NULL,
            is_enabled BOOLEAN DEFAULT 1,
            PRIMARY KEY (user_id, channel)
        )");

        $this->db = $this->createMock(Database::class);
        $this->db->method('getConnection')->willReturn($this->pdo);

        $this->githubService = $this->createMock(GitHubService::class);
        $this->notificationService = $this->createMock(NotificationService::class);
        $this->julesService = $this->createMock(JulesService::class);

        $this->sandboxService = new SandboxService($this->db, $this->githubService, $this->notificationService, $this->julesService);
        $this->webhookHandler = new WebhookHandler($this->db);

        Logger::resetInstance();
        new Logger($this->db);
    }

    public function testAutoMergeOnCiSuccess()
    {
        // 1. Setup: Project with Blockly auto-merge script
        $jsCode = "onEvent('CHECKS_COMPLETED', (event) => { if (isTaskReady()) { merge(); } });";
        $blocklyConfig = json_encode(['js' => $jsCode]);

        $this->pdo->exec("INSERT INTO users (user_id, email) VALUES (1, 'test@example.com')");
        $this->pdo->exec("INSERT INTO user_github_accounts (github_account_id, user_id, github_username, github_token) VALUES (1, 1, 'testuser', 'token')");
        $stmt = $this->pdo->prepare("INSERT INTO projects (project_id, user_id, github_repo, blockly_config, github_account_id) VALUES (1, 1, 'owner/repo', ?, 1)");
        $stmt->execute([$blocklyConfig]);

        // Task is already in CHECKING status, with a PR URL
        $this->pdo->prepare("INSERT INTO tasks (task_id, user_id, project_id, issue_number, title, status, pr_url, github_data)
                          VALUES (1, 1, 1, 101, 'Fix Bug', 'checking', 'https://github.com/owner/repo/pull/42', '{\"labels\":[]}')")->execute();
        $this->pdo->exec("UPDATE tasks SET github_repo = 'owner/repo' WHERE task_id = 1");

        $project = [
            'project_id' => 1,
            'user_id' => 1,
            'github_repo' => 'owner/repo',
            'blockly_config' => $blocklyConfig
        ];

        // 2. Mock GitHub API: PR checks now pass
        $this->githubService->method('extractPrNumber')->willReturn(42);
        $this->githubService->method('getPullRequest')->willReturn([
            'state' => 'open',
            'head' => ['sha' => 'abcdef']
        ]);
        $this->githubService->method('getCheckSuites')->willReturn([
            'check_suites' => [['status' => 'completed', 'conclusion' => 'success']]
        ]);
        $this->githubService->method('getCombinedStatus')->willReturn(['statuses' => []]);

        // Expectation: merge() should be called by Blockly
        $this->githubService->expects($this->once())
            ->method('mergePullRequest')
            ->with('owner/repo', 42, 'Merged via Blockly Automation');

        // 3. Trigger check_suite completed event
        $payload = [
            'action' => 'completed',
            'check_suite' => [
                'pull_requests' => [['url' => 'https://api.github.com/repos/owner/repo/pulls/42']]
            ],
            'repository' => ['full_name' => 'owner/repo']
        ];

        $this->webhookHandler->handle($project, $payload, $this->githubService, $this->notificationService, $this->julesService, $this->sandboxService, 'check_suite');

        // 4. Verification: Task status updated to ready (then probably finished if merge was successful, but merge is mocked here)
        $stmt = $this->pdo->query("SELECT status FROM tasks WHERE task_id = 1");
        $this->assertEquals('ready', $stmt->fetchColumn());

        // Verify Blockly log
        $stmt = $this->pdo->query("SELECT message FROM task_logs WHERE task_id = 1 AND message LIKE '%Blockly Action [Local]: Merged PR #42%'");
        $logMessage = $stmt->fetchColumn();
        if (!$logMessage) {
            $allLogs = $this->pdo->query("SELECT message FROM task_logs WHERE task_id = 1")->fetchAll(PDO::FETCH_COLUMN);
            $this->fail("Log message not found. Available logs: " . implode(", ", $allLogs));
        }
        $this->assertNotEmpty($logMessage);
    }

    private function withConsecutive(array ...$parameterGroups): array
    {
        $count = 0;
        $callbacks = [];
        $totalParams = count($parameterGroups[0]);
        for ($i = 0; $i < $totalParams; $i++) {
            $callbacks[] = $this->callback(function ($actual) use ($parameterGroups, $i, &$count, $totalParams) {
                $groupIndex = (int)($count / $totalParams);
                if ($groupIndex >= count($parameterGroups)) {
                    return true;
                }
                $expected = $parameterGroups[$groupIndex][$i];
                $count++;

                if ($expected instanceof \PHPUnit\Framework\Constraint\Constraint) {
                    return $expected->evaluate($actual, '', true);
                }
                return ($actual === $expected);
            });
        }
        return $callbacks;
    }

    public function testAgentErrorReporting()
    {
        // 1. Setup: Global Blockly script for AGENT_ERROR
        $jsCode = "onEvent('AGENT_ERROR', (event) => { notify('Agent failed on task: ' + event.payload.issue.title); });";
        $blocklyConfig = json_encode(['js' => $jsCode]);

        $stmt = $this->pdo->prepare("INSERT INTO users (user_id, email, blockly_config, jules_api_key) VALUES (1, 'test@example.com', ?, 'fake_key')");
        $stmt->execute([$blocklyConfig]);
        $this->pdo->exec("INSERT INTO user_github_accounts (github_account_id, user_id, github_username, github_token) VALUES (1, 1, 'testuser', 'token')");
        $this->pdo->exec("INSERT INTO projects (project_id, user_id, github_repo, github_account_id) VALUES (1, 1, 'owner/repo', 1)");
        $this->pdo->prepare("INSERT INTO tasks (task_id, user_id, project_id, issue_number, title, status, github_data)
                          VALUES (1, 1, 1, 101, 'Broken Task', 'created', '{\"labels\":[]}')")->execute();
        $this->pdo->exec("UPDATE tasks SET github_repo = 'owner/repo' WHERE task_id = 1");

        $project = [
            'project_id' => 1,
            'user_id' => 1,
            'github_repo' => 'owner/repo',
            'blockly_config' => null
        ];

        // 2. Mock Jules API failure
        $this->julesService->method('fetchSessionStatus')->willReturn(['status' => 'failed']);

        // Expectation: notify() should be called by Blockly (after task_status notification)
        $this->notificationService->expects($this->exactly(2))
            ->method('notify')
            ->with(...$this->withConsecutive(
                [
                    1,
                    'task_status',
                    $this->stringContains('Jules Failed'),
                    $this->stringContains('failed'),
                    $this->anything(),
                    $this->anything()
                ],
                [
                    1,
                    'blockly_action',
                    'Blockly Notification',
                    'Agent failed on task: Broken Task',
                    $this->anything(),
                    $this->anything()
                ]
            ));

        // 3. Trigger an event that causes refreshJulesStatus (e.g., labeled)
        $payload = [
            'action' => 'labeled',
            'issue' => [
                'number' => 101,
                'title' => 'Broken Task',
                'state' => 'open',
                'labels' => [['name' => 'Jules']]
            ],
            'repository' => ['full_name' => 'owner/repo']
        ];

        // We need to set jules_session_id so refreshJulesStatus actually calls fetchSessionStatus
        $this->pdo->exec("UPDATE tasks SET jules_session_id = 'sess_123' WHERE task_id = 1");

        // Mock GitHub getIssue for upsert
        $this->githubService->method('getIssue')->willReturn($payload['issue']);

        $this->webhookHandler->handle($project, $payload, $this->githubService, $this->notificationService, $this->julesService, $this->sandboxService, 'issues');

        // 4. Verification: Task status updated to failed_jules
        $stmt = $this->pdo->query("SELECT status FROM tasks WHERE task_id = 1");
        $actualStatus = $stmt->fetchColumn();
        if ($actualStatus !== 'failed_jules') {
            $allLogs = $this->pdo->query("SELECT message FROM task_logs WHERE task_id = 1")->fetchAll(PDO::FETCH_COLUMN);
            $this->fail("Status is $actualStatus, expected failed_jules. Available logs: " . implode(", ", $allLogs));
        }
        $this->assertEquals('failed_jules', $actualStatus);

        // Verify Blockly log
        $stmt = $this->pdo->query("SELECT message FROM task_logs WHERE task_id = 1 AND message LIKE '%Executing [Global] Blockly automation%'");
        $logMessage = $stmt->fetchColumn();
        if (!$logMessage) {
            $allLogs = $this->pdo->query("SELECT message FROM task_logs WHERE task_id = 1")->fetchAll(PDO::FETCH_COLUMN);
            $this->fail("Log message not found. Available logs: " . implode(", ", $allLogs));
        }
        $this->assertNotEmpty($logMessage);
    }
}
