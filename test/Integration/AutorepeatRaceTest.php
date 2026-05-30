<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Database;
use App\Task;
use App\WebhookHandler;
use App\GitHubService;
use App\Logger;
use PDO;
use Tests\TestDatabaseTrait;

class AutorepeatRaceTest extends TestCase
{
    use TestDatabaseTrait;

    private $db;
    private $pdo;
    private $githubService;

    protected function setUp(): void
    {
        $this->pdo = $this->getTestPdo();
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        // Reset tables
        $tables = ['task_logs', 'tasks', 'projects', 'users', 'user_github_accounts'];
        foreach ($tables as $table) {
            $this->pdo->exec("DROP TABLE IF EXISTS $table");
        }

        $pk = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
        $timestamp = $driver === 'sqlite' ? 'DATETIME DEFAULT CURRENT_TIMESTAMP' : 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP';

        $this->pdo->exec("CREATE TABLE users (
            user_id $pk,
            name VARCHAR(255),
            email VARCHAR(255) UNIQUE
        )");

        $this->pdo->exec("CREATE TABLE projects (
            project_id $pk,
            user_id INT NOT NULL,
            github_repo VARCHAR(255) NOT NULL
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
            autorepeat_remaining INT DEFAULT 0,
            agent_response TEXT,
            created_at $timestamp,
            UNIQUE(project_id, issue_number)
        )");

        $this->db = $this->createMock(Database::class);
        $this->db->method('getConnection')->willReturn($this->pdo);

        $this->githubService = $this->createMock(GitHubService::class);

        Logger::resetInstance();
        new Logger($this->db);
    }

    public function testMaybeDuplicateTaskIsAtomic()
    {
        // 1. Setup project and task
        $this->pdo->exec("INSERT INTO users (user_id, name, email) VALUES (1, 'Test User', 'test@example.com')");
        $this->pdo->exec("INSERT INTO projects (project_id, user_id, github_repo) VALUES (1, 1, 'owner/repo')");

        $issueData = [
            'number' => 101,
            'title' => 'Race Condition Task',
            'body' => 'Test body',
            'state' => 'closed',
            'state_reason' => 'completed',
            'labels' => [['name' => 'autorepeat: 2'], ['name' => 'Jules']]
        ];

        $this->pdo->prepare("INSERT INTO tasks (task_id, user_id, project_id, issue_number, title, status, github_state, autorepeat_remaining, github_data)
                          VALUES (1, 1, 1, 101, 'Race Condition Task', 'finished', 'closed', 2, ?)")
                  ->execute([json_encode($issueData)]);

        $webhookHandler = new WebhookHandler($this->db);
        $project = ['project_id' => 1, 'user_id' => 1, 'github_repo' => 'owner/repo'];
        $event = ['issue' => $issueData, 'repository' => ['full_name' => 'owner/repo']];

        // 2. Expectation: createIssue should only be called ONCE despite multiple calls to maybeDuplicateTask
        $this->githubService->expects($this->once())
            ->method('createIssue')
            ->willReturn(['number' => 102, 'title' => 'Race Condition Task', 'state' => 'open']);

        // 3. Simultaneous-ish calls
        $webhookHandler->maybeDuplicateTask($project, $event, $this->githubService);
        $webhookHandler->maybeDuplicateTask($project, $event, $this->githubService);
        $webhookHandler->maybeDuplicateTask($project, $event, $this->githubService);

        // 4. Verification: Check that only one new task was created in DB
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM tasks WHERE issue_number = 102");
        $this->assertEquals(1, $stmt->fetchColumn());

        // Check that the marker is present
        $stmt = $this->pdo->query("SELECT agent_response FROM tasks WHERE task_id = 1");
        $this->assertStringContainsString('autorepeat_triggered', $stmt->fetchColumn());
    }

    public function testUpsertDoesNotResetAutorepeatCount()
    {
        $this->pdo->exec("INSERT INTO users (user_id, name, email) VALUES (1, 'Test User', 'test@example.com')");
        $this->pdo->exec("INSERT INTO projects (project_id, user_id, github_repo) VALUES (1, 1, 'owner/repo')");

        $taskModel = new Task($this->db);

        // Initial creation with count = 3
        $issue = [
            'number' => 201,
            'title' => 'Sync Test',
            'state' => 'open',
            'labels' => [['name' => 'autorepeat: 3']]
        ];
        $taskModel->upsert(1, 1, $issue);

        $stmt = $this->pdo->query("SELECT autorepeat_remaining FROM tasks WHERE issue_number = 201");
        $this->assertEquals(3, $stmt->fetchColumn());

        // Simulate a webhook update with ONLY generic 'autorepeat' label
        $issueUpdate = [
            'number' => 201,
            'title' => 'Sync Test',
            'state' => 'open',
            'labels' => [['name' => 'autorepeat']]
        ];
        $taskModel->upsert(1, 1, $issueUpdate);

        // Should NOT change anything, should stay 3
        $stmt = $this->pdo->query("SELECT autorepeat_remaining FROM tasks WHERE issue_number = 201");
        $this->assertEquals(3, $stmt->fetchColumn());

        // Simulate explicit update (e.g. from UI)
        $taskModel->upsert(1, 1, $issueUpdate, 2);
        $stmt = $this->pdo->query("SELECT autorepeat_remaining FROM tasks WHERE issue_number = 201");
        $this->assertEquals(2, $stmt->fetchColumn());

        // Final sanity check: if it was 0, generic label should NOT set it to 5 anymore
        $issueNew = [
            'number' => 202,
            'title' => 'New Task',
            'state' => 'open',
            'labels' => [['name' => 'autorepeat']]
        ];
        $taskModel->upsert(1, 1, $issueNew);
        $stmt = $this->pdo->query("SELECT autorepeat_remaining FROM tasks WHERE issue_number = 202");
        $this->assertEquals(0, $stmt->fetchColumn());
    }
}
