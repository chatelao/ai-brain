<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Database;
use App\Task;
use PDO;
use Tests\TestDatabaseTrait;

class TaskDBIntegrationTest extends TestCase
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

        $pk = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
        $timestamp = $driver === 'sqlite' ? 'DATETIME DEFAULT CURRENT_TIMESTAMP' : 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP';

        $this->pdo->exec("CREATE TABLE tasks (
            task_id $pk,
            user_id INT NOT NULL,
            project_id INT NOT NULL,
            issue_number INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            body TEXT,
            status TEXT DEFAULT 'pending',
            github_data TEXT,
            created_at $timestamp,
            updated_at $timestamp,
            UNIQUE(project_id, issue_number)
        )");

        $this->db = $this->createMock(Database::class);
        $this->db->method('getConnection')->willReturn($this->pdo);

        $this->taskModel = new Task($this->db);
    }

    public function testCreateAndFindTask()
    {
        $data = [
            'user_id' => 1,
            'project_id' => 1,
            'issue_number' => 101,
            'title' => 'Test Issue',
            'body' => 'Test Body',
            'status' => 'pending'
        ];

        $this->assertTrue($this->taskModel->create($data));

        $tasks = $this->taskModel->findByProjectId(1);
        $this->assertCount(1, $tasks);
        $this->assertEquals('Test Issue', $tasks[0]['title']);

        $task = $this->taskModel->findById($tasks[0]['task_id']);
        $this->assertNotNull($task);
        $this->assertEquals(101, $task['issue_number']);
    }

    public function testUpdateStatus()
    {
        $data = [
            'user_id' => 1,
            'project_id' => 1,
            'issue_number' => 101,
            'title' => 'Test Issue'
        ];
        $this->taskModel->create($data);
        $tasks = $this->taskModel->findByProjectId(1);
        $taskId = $tasks[0]['task_id'];

        $this->assertTrue($this->taskModel->updateStatus($taskId, 'in_progress'));

        $task = $this->taskModel->findById($taskId);
        $this->assertEquals('in_progress', $task['status']);
    }
}
