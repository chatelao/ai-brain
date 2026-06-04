<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Database;
use App\WebhookHandler;
use App\Task;
use App\GitHubService;
use App\JulesService;
use App\Logger;
use PDO;

class JulesRetryTest extends TestCase
{
    private $db;
    private $pdo;
    private $handler;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec("CREATE TABLE users (
            user_id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255),
            jules_api_key VARCHAR(255),
            jules_quota_updated_at DATETIME,
            automations_enabled BOOLEAN DEFAULT 1
        )");

        $this->pdo->exec("CREATE TABLE projects (
            project_id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INT NOT NULL,
            github_repo VARCHAR(255) NOT NULL,
            github_token VARCHAR(255)
        )");

        $this->pdo->exec("CREATE TABLE tasks (
            task_id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INT NOT NULL,
            project_id INT NOT NULL,
            issue_number INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            body TEXT,
            status TEXT DEFAULT 'pending',
            jules_status TEXT,
            jules_session_id VARCHAR(255),
            jules_url TEXT,
            github_state VARCHAR(20) DEFAULT 'open',
            github_data TEXT,
            autorepeat_remaining INT DEFAULT 0,
            agent_response TEXT,
            pr_url VARCHAR(255),
            last_synced_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(project_id, issue_number)
        )");

        $this->pdo->exec("CREATE TABLE performance_logs (
            log_id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INT,
            type VARCHAR(50),
            target TEXT,
            duration FLOAT,
            context TEXT,
            status_code INT,
            error_message TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE task_logs (
            task_log_id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INT,
            task_id INT,
            message TEXT,
            level VARCHAR(20),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->db = $this->createMock(Database::class);
        $this->db->method('getConnection')->willReturn($this->pdo);

        Logger::resetInstance();
        new Logger($this->db);

        $this->handler = new WebhookHandler($this->db);
    }

    public function testWebhookTriggersRetryOnlyOnJulesComment()
    {
        $project = ['user_id' => 1, 'project_id' => 1, 'github_repo' => 'owner/repo'];
        $this->pdo->exec("INSERT INTO users (user_id, name) VALUES (1, 'Test User')");
        $this->pdo->exec("INSERT INTO projects (project_id, user_id, github_repo) VALUES (1, 1, 'owner/repo')");

        $this->pdo->prepare("INSERT INTO tasks (user_id, project_id, issue_number, title) VALUES (?, ?, ?, ?)")
            ->execute([1, 1, 123, 'Test Issue']);
        $taskId = $this->pdo->lastInsertId();

        $githubService = $this->createMock(GitHubService::class);
        $failureComment = "Jules has failed to create a task. You can try again later by removing and re-adding the 'jules' label.";

        // 1. Not a Jules comment
        $eventNonJules = [
            'action' => 'created',
            'issue' => ['number' => 123, 'title' => 'Test Issue'],
            'comment' => ['body' => $failureComment, 'user' => ['login' => 'random_user']],
            'repository' => ['full_name' => 'owner/repo']
        ];
        $githubService->expects($this->exactly(1))->method('removeLabel')->with('owner/repo', 123, 'jules');
        $githubService->expects($this->exactly(1))->method('addLabel')->with('owner/repo', 123, 'jules');

        // This should NOT call githubService
        $this->handler->handle($project, $eventNonJules, $githubService);

        // 2. Jules comment
        $eventJules = [
            'action' => 'created',
            'issue' => ['number' => 123, 'title' => 'Test Issue'],
            'comment' => ['body' => $failureComment, 'user' => ['login' => 'google-labs-jules[bot]']],
            'repository' => ['full_name' => 'owner/repo']
        ];
        // This SHOULD call githubService once
        $this->handler->handle($project, $eventJules, $githubService);

        // 3. Idempotency (same comment again)
        // This should NOT call githubService again
        $this->handler->handle($project, $eventJules, $githubService);
    }

    public function testSyncTriggersRetryIdempotently()
    {
        $userId = 1;
        $projectId = 1;
        $repo = 'owner/repo';

        $this->pdo->exec("INSERT INTO users (user_id, name, jules_api_key) VALUES ($userId, 'Test User', 'fake-key')");
        $this->pdo->exec("INSERT INTO projects (project_id, user_id, github_repo) VALUES ($projectId, $userId, '$repo')");

        $githubData = [
            'assignee' => ['login' => 'jules'],
            'labels' => [['name' => 'jules']]
        ];

        $this->pdo->prepare("INSERT INTO tasks (user_id, project_id, issue_number, title, status, github_data) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$userId, $projectId, 123, 'Test Issue', 'pending', json_encode($githubData)]);
        $taskId = $this->pdo->lastInsertId();

        $githubService = $this->createMock(GitHubService::class);
        $githubService->method('getIssue')->willReturn(['number' => 123, 'title' => 'Test Issue', 'state' => 'open']);

        $failureComment = [
            'body' => "Jules has failed to create a task. You can try again later by removing and re-adding the 'jules' label.",
            'user' => ['login' => 'google-labs-jules[bot]']
        ];
        $githubService->method('getIssueComments')->willReturn([$failureComment]);

        // Should only trigger ONCE across multiple syncs
        $githubService->expects($this->once())->method('removeLabel')->with($repo, 123, 'jules');
        $githubService->expects($this->once())->method('addLabel')->with($repo, 123, 'jules');

        $julesService = $this->createMock(JulesService::class);
        $taskModel = new Task($this->db);

        // First sync
        $taskModel->refreshJulesStatus($userId, $githubService, $julesService, null, (int)$taskId);

        // Second sync (idempotency check)
        $taskModel->refreshJulesStatus($userId, $githubService, $julesService, null, (int)$taskId);
    }
}
