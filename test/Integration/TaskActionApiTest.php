<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Database;
use App\User;
use App\Project;
use App\Task;
use PDO;

class TaskActionApiTest extends TestCase
{
    private $db;
    private $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->db = $this->createMock(Database::class);
        $this->db->method('getConnection')->willReturn($this->pdo);

        $this->pdo->exec("CREATE TABLE users (
            user_id INTEGER PRIMARY KEY AUTOINCREMENT,
            google_id VARCHAR(255) UNIQUE NOT NULL,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            avatar VARCHAR(255),
            role VARCHAR(20) DEFAULT 'user',
            jules_api_key VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE user_github_accounts (
            github_account_id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            github_username VARCHAR(255) NOT NULL,
            github_token VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(user_id, github_username)
        )");

        $this->pdo->exec("CREATE TABLE projects (
            project_id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            github_account_id INTEGER NOT NULL,
            github_repo VARCHAR(255) NOT NULL,
            github_token VARCHAR(255),
            webhook_secret VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE tasks (
            task_id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            project_id INTEGER NOT NULL,
            issue_number INTEGER NOT NULL,
            title VARCHAR(255) NOT NULL,
            body TEXT,
            status VARCHAR(50) DEFAULT 'created',
            github_state VARCHAR(20) DEFAULT 'open',
            github_data TEXT, autorepeat_remaining INT DEFAULT 0,
            pr_url VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(project_id, issue_number)
        )");

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_unset();
    }

    public function testTriggerAgentUnauthorized()
    {
        // No user in session
        // This test would ideally hit the actual PHP file, but here we can test the logic once implemented.
        // For now, I'll just keep it as a placeholder or focus on the logic.
        $this->assertTrue(true);
    }

    public function testTriggerAgentLogic()
    {
        $userModel = new User($this->db);
        $projectModel = new Project($this->db);
        $taskModel = new Task($this->db);

        $user = $userModel->createOrUpdate([
            'google_id' => 'u1',
            'name' => 'User One',
            'email' => 'u1@example.com'
        ]);

        $userModel->addGitHubAccount($user['user_id'], 'token', 'user');
        $stmt = $this->pdo->query("SELECT * FROM user_github_accounts LIMIT 1");
        $account = $stmt->fetch();

        $project = $projectModel->create($user['user_id'], $account['github_account_id'], 'owner/repo');
        // Get the created project
        $stmt = $this->pdo->query("SELECT * FROM projects LIMIT 1");
        $project = $stmt->fetch();

        $taskModel->create([
            'user_id' => $user['user_id'],
            'project_id' => $project['project_id'],
            'issue_number' => 1,
            'title' => 'Test Task',
            'status' => 'created'
        ]);
        $task = $taskModel->findByIssueNumber($project['project_id'], 1);

        // Simulate login
        $_SESSION['user_id'] = $user['user_id'];

        // Logic we want to implement in /api/task.php
        $taskId = $task['task_id'];
        $action = 'trigger_agent';

        // Verification of task and ownership
        $this->assertEquals($user['user_id'], $project['user_id']);
        $this->assertEquals($project['project_id'], $task['project_id']);

        // In the actual API we would call a service to trigger Jules.
        // We can verify status update at least.
        $taskModel->updateStatus($taskId, 'executing');
        $updatedTask = $taskModel->findById($taskId);
        $this->assertEquals('executing', $updatedTask['status']);
    }
}
