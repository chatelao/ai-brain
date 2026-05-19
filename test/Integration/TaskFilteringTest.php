<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Database;
use App\Task;
use PDO;
use Tests\TestDatabaseTrait;

class TaskFilteringTest extends TestCase
{
    use TestDatabaseTrait;

    private $db;
    private $pdo;
    private $taskModel;

    protected function setUp(): void
    {
        $this->pdo = $this->getTestPdo();
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        $this->pdo->exec("DROP TABLE IF EXISTS tasks");
        $this->pdo->exec("DROP TABLE IF EXISTS projects");

        $pk = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
        $timestamp = $driver === 'sqlite' ? 'DATETIME DEFAULT CURRENT_TIMESTAMP' : 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP';

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
            status VARCHAR(50) DEFAULT 'pending',
            github_state VARCHAR(20) DEFAULT 'open',
            created_at $timestamp,
            UNIQUE(project_id, issue_number)
        )");

        $this->db = $this->createMock(Database::class);
        $this->db->method('getConnection')->willReturn($this->pdo);

        $this->taskModel = new Task($this->db);
    }

    private function createTask($projectId, $issueNumber, $state, $status, $createdAt)
    {
        $stmt = $this->pdo->prepare("INSERT INTO tasks (user_id, project_id, issue_number, title, github_state, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([1, $projectId, $issueNumber, "Issue $issueNumber", $state, $status, $createdAt]);
    }

    public function testFilteringLogic()
    {
        // Project 1
        $this->createTask(1, 1, 'open', 'pending', '2023-01-01 10:00:00');
        $this->createTask(1, 2, 'open', 'executing', '2023-01-01 11:00:00');
        $this->createTask(1, 3, 'closed', Task::STATUS_FINISHED, '2023-01-01 12:00:00'); // 5th completed globally
        $this->createTask(1, 4, 'closed', 'completed', '2023-01-01 13:00:00');          // 4th completed globally (legacy)
        $this->createTask(1, 5, 'closed', Task::STATUS_FINISHED, '2023-01-01 14:00:00'); // 3rd completed globally
        $this->createTask(1, 6, 'closed', 'completed', '2023-01-01 15:00:00');          // 2nd completed globally (legacy)
        $this->createTask(1, 7, 'closed', 'failed', '2023-01-01 16:00:00');    // failed, should be hidden

        // Project 2 (just to test findByUserProjects)
        $this->pdo->exec("INSERT INTO projects (project_id, user_id, github_repo) VALUES (1, 1, 'repo1'), (2, 1, 'repo2')");
        $this->createTask(2, 10, 'open', 'pending', '2023-01-01 10:00:00');
        $this->createTask(2, 11, 'closed', Task::STATUS_FINISHED, '2023-01-01 17:00:00'); // 1st completed globally

        // Orphan and invalid tasks
        $this->createTask(1, 0, 'open', 'pending', '2023-01-01 18:00:00'); // issue_number 0
        $stmt = $this->pdo->prepare("INSERT INTO tasks (user_id, project_id, issue_number, title, github_state, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([1, 1, 99, "", "open", "pending"]); // empty title

        // findByProjectId(1, false)
        $tasks = $this->taskModel->findByProjectId(1, false);

        // Expected issues for Project 1: 1, 2 (open), 4, 5, 6 (last 3 completed for this project)
        $issueNumbers = array_column($tasks, 'issue_number');
        sort($issueNumbers);
        $this->assertEquals([1, 2, 4, 5, 6], $issueNumbers, "Should only show open issues and last 3 completed issues for Project 1");

        // findActiveByUserProjects(1)
        $activeTasks = $this->taskModel->findActiveByUserProjects(1);
        $issueNumbers = array_column($activeTasks, 'issue_number');
        sort($issueNumbers);

        // Globally open: 1, 2, 10
        // Globally completed (last 3): 11 (17:00), 6 (15:00), 5 (14:00)
        // Hidden orphans: 0, 99 (empty title)
        $this->assertEquals([1, 2, 5, 6, 10, 11], $issueNumbers, "Should only show active issues and last 3 globally completed issues");
    }
}
