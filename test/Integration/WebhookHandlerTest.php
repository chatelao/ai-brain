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
            task_id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INT NOT NULL,
            project_id INT NOT NULL,
            issue_number INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            body TEXT,
            status TEXT DEFAULT 'pending',
            github_state VARCHAR(20) DEFAULT 'open',
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
        $project = ['user_id' => 1, 'project_id' => 1];
        $event = [
            'action' => 'opened',
            'issue' => [
                'number' => 123,
                'title' => 'Test Issue',
                'body' => 'Issue description'
            ]
        ];

        $result = $this->handler->handle($project, $event);
        $this->assertTrue($result);

        $stmt = $this->pdo->prepare("SELECT * FROM tasks WHERE project_id = ? AND issue_number = ?");
        $stmt->execute([$project['project_id'], 123]);
        $task = $stmt->fetch();

        $this->assertNotFalse($task);
        $this->assertEquals('Test Issue', $task['title']);
        $this->assertEquals('Issue description', $task['body']);
        $this->assertEquals('pending', $task['status']);
    }

    public function testHandleAutorepeat()
    {
        $project = ['user_id' => 1, 'project_id' => 1];
        $event = [
            'action' => 'closed',
            'issue' => [
                'number' => 123,
                'title' => 'Autorepeat Issue',
                'body' => 'Description',
                'state_reason' => 'completed',
                'labels' => [
                    ['name' => 'autorepeat']
                ]
            ],
            'repository' => [
                'full_name' => 'owner/repo'
            ]
        ];

        $githubService = $this->createMock(\App\GitHubService::class);
        $githubService->expects($this->once())
            ->method('createIssue')
            ->with('owner/repo', 'Autorepeat Issue', 'Description', ['autorepeat']);
        $githubService->expects($this->once())
            ->method('removeLabel')
            ->with('owner/repo', 123, 'autorepeat');

        $result = $this->handler->handle($project, $event, $githubService);
        $this->assertTrue($result);
    }
}
