<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Database;
use App\Task;
use PDO;

class IssueSyncIntegrationTest extends TestCase
{
    private $db;
    private $pdo;
    private $taskModel;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec("CREATE TABLE tasks (
            task_id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INT NOT NULL,
            project_id INT NOT NULL,
            issue_number INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            body TEXT,
            status TEXT DEFAULT 'pending',
            github_data TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(project_id, issue_number)
        )");

        $this->db = $this->createMock(Database::class);
        $this->db->method('getConnection')->willReturn($this->pdo);

        $this->taskModel = new Task($this->db);
    }

    public function testUpsertNewIssue()
    {
        $userId = 1;
        $projectId = 1;
        $issue = [
            'number' => 1,
            'title' => 'Initial Issue',
            'body' => 'Initial Body'
        ];

        $this->taskModel->upsert($userId, $projectId, $issue);

        $stmt = $this->pdo->prepare("SELECT * FROM tasks WHERE project_id = ? AND issue_number = ?");
        $stmt->execute([$projectId, 1]);
        $task = $stmt->fetch();

        $this->assertNotFalse($task);
        $this->assertEquals('Initial Issue', $task['title']);
    }

    public function testUpsertExistingIssue()
    {
        $userId = 1;
        $projectId = 1;
        $issue1 = [
            'number' => 1,
            'title' => 'Initial Issue',
            'body' => 'Initial Body'
        ];

        $this->taskModel->upsert($userId, $projectId, $issue1);

        $issue2 = [
            'number' => 1,
            'title' => 'Updated Issue',
            'body' => 'Updated Body'
        ];

        $this->taskModel->upsert($userId, $projectId, $issue2);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tasks WHERE project_id = ? AND issue_number = ?");
        $stmt->execute([$projectId, 1]);
        $count = $stmt->fetchColumn();

        $this->assertEquals(1, $count);

        $stmt = $this->pdo->prepare("SELECT * FROM tasks WHERE project_id = ? AND issue_number = ?");
        $stmt->execute([$projectId, 1]);
        $task = $stmt->fetch();

        $this->assertEquals('Updated Issue', $task['title']);
        $this->assertEquals('Updated Body', $task['body']);
    }
}
