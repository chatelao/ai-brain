<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Database;
use App\Task;
use PDO;
use Tests\TestDatabaseTrait;

class AutorepeatPersistenceTest extends TestCase
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
            body TEXT,
            status TEXT DEFAULT 'created',
            github_state VARCHAR(20) DEFAULT 'open',
            github_data TEXT,
            autorepeat_remaining INT DEFAULT 0,
            created_at $timestamp,
            updated_at $timestamp,
            UNIQUE(project_id, issue_number)
        )");

        $this->db = $this->createMock(Database::class);
        $this->db->method('getConnection')->willReturn($this->pdo);

        $this->taskModel = new Task($this->db);
    }

    public function testUpsertPreservesAutorepeatRemaining()
    {
        $this->pdo->exec("INSERT INTO projects (project_id, user_id, github_repo) VALUES (1, 1, 'test/repo')");

        $issue = [
            'number' => 123,
            'title' => 'Test Issue',
            'body' => 'Initial Body',
            'state' => 'open'
        ];

        // 1. Create task with autorepeat_remaining = 5
        $this->taskModel->upsert(1, 1, $issue, 5);

        $task = $this->taskModel->findByIssueNumber(1, 123);
        $this->assertEquals(5, $task['autorepeat_remaining']);

        // 2. Upsert with null (default) - should preserve the value 5
        $issue['body'] = 'Updated Body';
        $this->taskModel->upsert(1, 1, $issue);

        $task = $this->taskModel->findByIssueNumber(1, 123);
        $this->assertEquals(5, $task['autorepeat_remaining'], "Autorepeat remaining should be preserved when upserted with null");
        $this->assertEquals('Updated Body', $task['body']);

        // 3. Upsert with explicit value - should update to the new value
        $this->taskModel->upsert(1, 1, $issue, 3);
        $task = $this->taskModel->findByIssueNumber(1, 123);
        $this->assertEquals(3, $task['autorepeat_remaining'], "Autorepeat remaining should be updated when an explicit value is provided");
    }

    public function testUpsertDefaultsToZeroOnNewTask()
    {
        $this->pdo->exec("INSERT INTO projects (project_id, user_id, github_repo) VALUES (1, 1, 'test/repo')");

        $issue = [
            'number' => 456,
            'title' => 'New Issue',
            'body' => 'New Body',
            'state' => 'open'
        ];

        // Upsert new task without explicit autorepeat_remaining
        $this->taskModel->upsert(1, 1, $issue);

        $task = $this->taskModel->findByIssueNumber(1, 456);
        $this->assertEquals(0, $task['autorepeat_remaining'], "Autorepeat remaining should default to 0 for new tasks");
    }

    public function testUpsertExtractsCountFromLabels()
    {
        $this->pdo->exec("INSERT INTO projects (project_id, user_id, github_repo) VALUES (1, 1, 'test/repo')");

        $issue = [
            'number' => 789,
            'title' => 'Labeled Issue',
            'body' => 'Body',
            'state' => 'open',
            'labels' => [
                ['name' => 'bug'],
                ['name' => 'autorepeat: 7']
            ]
        ];

        // Upsert should find 'autorepeat: 7'
        $this->taskModel->upsert(1, 1, $issue);

        $task = $this->taskModel->findByIssueNumber(1, 789);
        $this->assertEquals(7, $task['autorepeat_remaining'], "Autorepeat remaining should be extracted from labels");
    }

    public function testHasAutorepeatLabelSupportsNewFormat()
    {
        $task = [
            'github_data' => json_encode([
                'labels' => [['name' => 'autorepeat: 3']]
            ])
        ];
        $this->assertTrue($this->taskModel->hasAutorepeatLabel($task));

        $task2 = [
            'github_data' => json_encode([
                'labels' => [['name' => 'autorepeat']]
            ])
        ];
        $this->assertTrue($this->taskModel->hasAutorepeatLabel($task2));
    }
}
