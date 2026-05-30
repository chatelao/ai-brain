<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Database;
use App\Task;
use App\Project;
use App\User;
use App\GitHubService;
use App\JulesService;
use App\NotificationService;
use App\WebhookHandler;
use App\Logger;
use PDO;
use Tests\TestDatabaseTrait;

class CronAutorepeatTest extends TestCase
{
    use TestDatabaseTrait;

    private $db;
    private $pdo;
    private $githubService;
    private $julesService;
    private $notificationService;
    private $taskModel;

    protected function setUp(): void
    {
        $this->pdo = $this->getTestPdo();
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        // Reset tables
        $tables = ['task_logs', 'tasks', 'projects', 'users', 'notifications', 'user_github_accounts'];
        foreach ($tables as $table) {
            $this->pdo->exec("DROP TABLE IF EXISTS $table");
        }

        $pk = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
        $timestamp = $driver === 'sqlite' ? 'DATETIME DEFAULT CURRENT_TIMESTAMP' : 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP';

        $this->pdo->exec("CREATE TABLE users (
            user_id $pk,
            name VARCHAR(255),
            email VARCHAR(255) UNIQUE,
            jules_api_key VARCHAR(255),
            jules_quota_usage INT DEFAULT 0,
            jules_quota_limit INT DEFAULT 0,
            jules_quota_updated_at DATETIME,
            automations_enabled BOOLEAN DEFAULT 1
        )");

        $this->pdo->exec("CREATE TABLE projects (
            project_id $pk,
            user_id INT NOT NULL,
            github_repo VARCHAR(255) NOT NULL,
            github_token VARCHAR(255),
            github_account_id INT,
            blockly_config TEXT,
            created_at $timestamp
        )");

        $this->pdo->exec("CREATE TABLE user_github_accounts (
            github_account_id $pk,
            user_id INT NOT NULL,
            github_token VARCHAR(255),
            github_username VARCHAR(255)
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
            agent_response TEXT,
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

        $this->db = $this->createMock(Database::class);
        $this->db->method('getConnection')->willReturn($this->pdo);

        $this->githubService = $this->createMock(GitHubService::class);
        $this->julesService = $this->createMock(JulesService::class);
        $this->notificationService = $this->createMock(NotificationService::class);

        $this->taskModel = new Task($this->db);

        Logger::resetInstance();
        new Logger($this->db);
    }

    public function testCronTriggersAutoMergeForReadyAutorepeatTask()
    {
        // Setup user, project, and task
        $this->pdo->exec("INSERT INTO users (user_id, name, email, jules_api_key) VALUES (1, 'Test User', 'test@example.com', 'key')");
        $this->pdo->exec("INSERT INTO user_github_accounts (github_account_id, user_id, github_username, github_token) VALUES (1, 1, 'testuser', 'token')");
        $this->pdo->exec("INSERT INTO projects (project_id, user_id, github_repo, github_token, github_account_id) VALUES (1, 1, 'owner/repo', 'token', 1)");

        // Task is already 'ready', with autorepeat_remaining = 3, and is Jules related
        $githubData = json_encode([
            'title' => 'Autorepeat Task',
            'labels' => [['name' => 'Jules'], ['name' => 'autorepeat: 3']],
            'assignee' => ['login' => 'jules']
        ]);
        $this->pdo->prepare("INSERT INTO tasks (task_id, user_id, project_id, issue_number, title, status, pr_url, autorepeat_remaining, github_data, jules_status, jules_session_id)
                          VALUES (1, 1, 1, 101, 'Autorepeat Task', 'ready', 'https://github.com/owner/repo/pull/42', 3, ?, 'completed', 'sess_123')")
                  ->execute([$githubData]);

        // Mock GitHub API calls that refreshJulesStatus and autoMergeAndDuplicate will make
        $this->githubService->method('extractPrNumber')->willReturn(42);
        $this->githubService->method('getPullRequest')->willReturn([
            'number' => 42,
            'state' => 'open',
            'mergeable_state' => 'clean',
            'head' => ['sha' => 'abcdef']
        ]);
        $this->githubService->method('getCheckSuites')->willReturn([
            'check_suites' => [['status' => 'completed', 'conclusion' => 'success']]
        ]);
        $this->githubService->method('getCombinedStatus')->willReturn(['statuses' => []]);

        // Expect auto-merge actions
        $this->githubService->expects($this->once())
            ->method('mergePullRequest')
            ->with('owner/repo', 42, $this->stringContains('Merged automatically'));

        $this->githubService->expects($this->once())
            ->method('closeIssue')
            ->with('owner/repo', 101, 'completed');

        // Simulate Cron Call
        $this->taskModel->refreshJulesStatus(1, $this->githubService, $this->julesService, $this->notificationService, null, 1);

        // Verification: Task should be marked as finished in DB
        $stmt = $this->pdo->query("SELECT status, github_state FROM tasks WHERE task_id = 1");
        $row = $stmt->fetch();
        $this->assertEquals('finished', $row['status']);
        $this->assertEquals('closed', $row['github_state']);
    }

    public function testCronTriggersAutoMergeEvenIfStatusDoesNotChange()
    {
        // Setup same as above, but starting with 'ready' status
        $this->pdo->exec("INSERT INTO users (user_id, name, email, jules_api_key) VALUES (1, 'Test User', 'test@example.com', 'key')");
        $this->pdo->exec("INSERT INTO user_github_accounts (github_account_id, user_id, github_username, github_token) VALUES (1, 1, 'testuser', 'token')");
        $this->pdo->exec("INSERT INTO projects (project_id, user_id, github_repo, github_token, github_account_id) VALUES (1, 1, 'owner/repo', 'token', 1)");

        $githubData = json_encode([
            'title' => 'Autorepeat Task',
            'labels' => [['name' => 'Jules'], ['name' => 'autorepeat: 3']]
        ]);
        $this->pdo->prepare("INSERT INTO tasks (task_id, user_id, project_id, issue_number, title, status, pr_url, autorepeat_remaining, github_data, jules_status, jules_session_id)
                          VALUES (1, 1, 1, 101, 'Autorepeat Task', 'ready', 'https://github.com/owner/repo/pull/42', 3, ?, 'completed', 'sess_123')")
                  ->execute([$githubData]);

        // Mock GitHub API
        $this->githubService->method('extractPrNumber')->willReturn(42);
        $this->githubService->method('getPullRequest')->willReturn([
            'number' => 42,
            'state' => 'open',
            'mergeable_state' => 'clean',
            'head' => ['sha' => 'abcdef']
        ]);
        $this->githubService->method('getCheckSuites')->willReturn([
            'check_suites' => [['status' => 'completed', 'conclusion' => 'success']]
        ]);

        // Expect merge
        $this->githubService->expects($this->once())->method('mergePullRequest');

        // Run cron refresh
        $this->taskModel->refreshJulesStatus(1, $this->githubService, $this->julesService, $this->notificationService, null, 1);

        $stmt = $this->pdo->query("SELECT status FROM tasks WHERE task_id = 1");
        $this->assertEquals('finished', $stmt->fetchColumn());
    }

    public function testCronTriggersDuplicationForMissedWebhook()
    {
        // 1. Setup: A task that is already 'finished' (closed) but still has autorepeat cycles
        // and NO 'autorepeat_triggered' marker in agent_response.
        $this->pdo->exec("INSERT INTO users (user_id, name, email) VALUES (1, 'Test User', 'test@example.com')");
        $this->pdo->exec("INSERT INTO user_github_accounts (github_account_id, user_id, github_username, github_token) VALUES (1, 1, 'testuser', 'token')");
        $this->pdo->exec("INSERT INTO projects (project_id, user_id, github_repo, github_token, github_account_id) VALUES (1, 1, 'owner/repo', 'token', 1)");

        $githubData = [
            'number' => 101,
            'title' => 'Finished Autorepeat Task',
            'body' => 'Some body',
            'state' => 'closed',
            'labels' => [['name' => 'autorepeat: 2'], ['name' => 'Jules']]
        ];

        $this->pdo->prepare("INSERT INTO tasks (task_id, user_id, project_id, issue_number, title, status, github_state, autorepeat_remaining, github_data, agent_response)
                          VALUES (1, 1, 1, 101, 'Finished Autorepeat Task', 'finished', 'closed', 2, ?, '')")
                  ->execute([json_encode($githubData)]);

        // 2. Expectation: duplication logic should be triggered
        $this->githubService->expects($this->once())
            ->method('createIssue')
            ->with('owner/repo', 'Finished Autorepeat Task', 'Some body', $this->anything())
            ->willReturn(['number' => 102, 'title' => 'Finished Autorepeat Task', 'state' => 'open']);

        // 3. Run cron refresh
        $this->taskModel->refreshJulesStatus(1, $this->githubService, $this->julesService, $this->notificationService, null, 1);

        // 4. Verification:
        // Original task should have the marker
        $stmt = $this->pdo->query("SELECT agent_response FROM tasks WHERE task_id = 1");
        $this->assertStringContainsString('autorepeat_triggered', $stmt->fetchColumn());

        // New task should be created in DB
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM tasks WHERE issue_number = 102");
        $this->assertEquals(1, $stmt->fetchColumn());
    }

    public function testCronProcessesNonJulesAutorepeatTask()
    {
        // Setup a task with autorepeat but NOT Jules related (no Jules label/assignee)
        $this->pdo->exec("INSERT INTO users (user_id, name, email) VALUES (1, 'Test User', 'test@example.com')");
        $this->pdo->exec("INSERT INTO user_github_accounts (github_account_id, user_id, github_username, github_token) VALUES (1, 1, 'testuser', 'token')");
        $this->pdo->exec("INSERT INTO projects (project_id, user_id, github_repo, github_token, github_account_id) VALUES (1, 1, 'owner/repo', 'token', 1)");

        $githubData = json_encode([
            'title' => 'Non-Jules Autorepeat',
            'labels' => [['name' => 'autorepeat: 3']],
            'assignee' => null
        ]);
        $this->pdo->prepare("INSERT INTO tasks (task_id, user_id, project_id, issue_number, title, status, pr_url, autorepeat_remaining, github_data)
                          VALUES (1, 1, 1, 101, 'Non-Jules Autorepeat', 'ready', 'https://github.com/owner/repo/pull/42', 3, ?)")
                  ->execute([$githubData]);

        // Mock GitHub API for PR check
        $this->githubService->method('extractPrNumber')->willReturn(42);
        $this->githubService->method('getPullRequest')->willReturn([
            'number' => 42,
            'state' => 'open',
            'mergeable_state' => 'clean',
            'head' => ['sha' => 'abcdef']
        ]);
        $this->githubService->method('getCheckSuites')->willReturn([
            'check_suites' => [['status' => 'completed', 'conclusion' => 'success']]
        ]);

        // Expect merge
        $this->githubService->expects($this->once())->method('mergePullRequest');

        // Run cron refresh
        $this->taskModel->refreshJulesStatus(1, $this->githubService, $this->julesService, $this->notificationService, null, 1);

        $stmt = $this->pdo->query("SELECT status FROM tasks WHERE task_id = 1");
        $this->assertEquals('finished', $stmt->fetchColumn());
    }

    public function testLastIterationIsSynchronized()
    {
        $this->pdo->exec("INSERT INTO users (user_id, name, email) VALUES (1, 'Test User', 'test@example.com')");
        $this->pdo->exec("INSERT INTO user_github_accounts (github_account_id, user_id, github_username, github_token) VALUES (1, 1, 'testuser', 'token')");
        $this->pdo->exec("INSERT INTO projects (project_id, user_id, github_repo, github_token, github_account_id) VALUES (1, 1, 'owner/repo', 'token', 1)");

        $githubData = [
            'number' => 101,
            'title' => 'Last Iteration',
            'body' => 'Body',
            'state' => 'closed',
            'state_reason' => 'completed',
            'labels' => [['name' => 'autorepeat: 1'], ['name' => 'Jules']]
        ];

        $this->pdo->prepare("INSERT INTO tasks (task_id, user_id, project_id, issue_number, title, status, github_state, autorepeat_remaining, github_data)
                          VALUES (1, 1, 1, 101, 'Last Iteration', 'finished', 'closed', 1, ?)")
                  ->execute([json_encode($githubData)]);

        $webhookHandler = new WebhookHandler($this->db);

        $this->githubService->expects($this->once())
            ->method('createIssue')
            ->willReturn(['number' => 102, 'title' => 'Last Iteration', 'state' => 'open']);

        $project = ['project_id' => 1, 'user_id' => 1, 'github_repo' => 'owner/repo'];
        $event = ['issue' => $githubData, 'repository' => ['full_name' => 'owner/repo']];

        $webhookHandler->maybeDuplicateTask($project, $event, $this->githubService);

        // Verify new task with autorepeat_remaining = 0 is in DB
        $stmt = $this->pdo->query("SELECT autorepeat_remaining FROM tasks WHERE issue_number = 102");
        $result = $stmt->fetch();
        $this->assertNotFalse($result);
        $this->assertEquals(0, $result['autorepeat_remaining']);
    }
}
