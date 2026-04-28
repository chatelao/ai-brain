<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Database;
use App\WebhookHandler;
use PDO;

class WebhookHandlerTest extends TestCase
{
    private $db;
    private $pdo;
    private $handler;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec("CREATE TABLE tasks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
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

        $this->handler = new WebhookHandler($this->db);
    }

    public function testVerifySignature()
    {
        $payload = '{"action":"opened"}';
        $secret = 'test_secret';
        $signature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        $this->assertTrue($this->handler->verifySignature($payload, $signature, $secret));
        $this->assertFalse($this->handler->verifySignature($payload, 'wrong_signature', $secret));
    }

    public function testHandleIssueOpened()
    {
        $projectId = 1;
        $event = [
            'action' => 'opened',
            'issue' => [
                'number' => 123,
                'title' => 'Test Issue',
                'body' => 'Issue description'
            ]
        ];

        $result = $this->handler->handle($projectId, $event);
        $this->assertTrue($result);

        $stmt = $this->pdo->prepare("SELECT * FROM tasks WHERE project_id = ? AND issue_number = ?");
        $stmt->execute([$projectId, 123]);
        $task = $stmt->fetch();

        $this->assertNotFalse($task);
        $this->assertEquals('Test Issue', $task['title']);
        $this->assertEquals('Issue description', $task['body']);
        $this->assertEquals('pending', $task['status']);
    }

    public function testHandleIssueUpdate()
    {
        $projectId = 1;
        $eventOpened = [
            'action' => 'opened',
            'issue' => [
                'number' => 123,
                'title' => 'Original Title',
                'body' => 'Original Body'
            ]
        ];

        $this->handler->handle($projectId, $eventOpened);

        $eventEdited = [
            'action' => 'edited',
            'issue' => [
                'number' => 123,
                'title' => 'Updated Title',
                'body' => 'Updated Body'
            ]
        ];

        $result = $this->handler->handle($projectId, $eventEdited);
        $this->assertTrue($result);

        $stmt = $this->pdo->prepare("SELECT * FROM tasks WHERE project_id = ? AND issue_number = ?");
        $stmt->execute([$projectId, 123]);
        $task = $stmt->fetch();

        $this->assertEquals('Updated Title', $task['title']);
        $this->assertEquals('Updated Body', $task['body']);
    }

    public function testHandleUnsupportedAction()
    {
        $projectId = 1;
        $event = [
            'action' => 'deleted',
            'issue' => [
                'number' => 123,
                'title' => 'Test Issue',
                'body' => 'Issue description'
            ]
        ];

        $result = $this->handler->handle($projectId, $event);
        $this->assertFalse($result);
    }
}
