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

        $this->pdo->exec("CREATE TABLE projects (
            project_id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INT NOT NULL,
            github_repo VARCHAR(255) NOT NULL
        )");

        $this->pdo->exec("CREATE TABLE tasks (
            task_id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INT NOT NULL,
            project_id INT NOT NULL,
            issue_number INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            body TEXT,
            status TEXT DEFAULT 'pending',
            github_state VARCHAR(20) DEFAULT 'open',
            github_data TEXT, autorepeat_remaining INT DEFAULT 0,
            agent_response TEXT,
            pr_url VARCHAR(255),
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
        $project = ['user_id' => 1, 'project_id' => 1, 'github_repo' => 'owner/repo'];
        $this->pdo->exec("INSERT INTO projects (project_id, user_id, github_repo) VALUES (1, 1, 'owner/repo')");

        $event = [
            'action' => 'opened',
            'issue' => [
                'number' => 123,
                'title' => 'Test Issue',
                'body' => 'Issue description'
            ],
            'repository' => [
                'full_name' => 'owner/repo'
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
        $this->assertEquals('created', $task['status']);
    }

    public function testHandleAutorepeat()
    {
        $project = ['user_id' => 1, 'project_id' => 1, 'github_repo' => 'owner/repo'];
        $this->pdo->exec("INSERT INTO projects (project_id, user_id, github_repo) VALUES (1, 1, 'owner/repo')");

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
            ->with('owner/repo', 'Autorepeat Issue', 'Description', ['Jules']);
        $githubService->expects($this->atLeastOnce())
            ->method('updateAutorepeatLabels');

        $result = $this->handler->handle($project, $event, $githubService);
        $this->assertTrue($result);
    }

    public function testHandleAutorepeatWithoutNotificationService()
    {
        // Simulate human-triggered merge (sender.type === 'User')
        $project = ['user_id' => 1, 'project_id' => 1, 'github_repo' => 'owner/repo'];
        $this->pdo->exec("INSERT INTO projects (project_id, user_id, github_repo) VALUES (1, 1, 'owner/repo')");

        $prUrl = 'https://github.com/owner/repo/pull/123';

        // Pre-seed task
        $githubData = [
            'number' => 123,
            'title' => 'Issue to autorepeat',
            'labels' => [['name' => 'autorepeat']]
        ];

        $stmt = $this->pdo->prepare("INSERT INTO tasks (user_id, project_id, issue_number, title, github_data, pr_url, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([1, 1, 123, 'Issue to autorepeat', json_encode($githubData), $prUrl, 'checking']);

        $event = [
            'action' => 'closed',
            'pull_request' => [
                'number' => 124,
                'title' => 'PR Title',
                'html_url' => $prUrl,
                'merged' => true
            ],
            'sender' => [
                'type' => 'User'
            ],
            'repository' => [
                'full_name' => 'owner/repo'
            ]
        ];

        $githubService = $this->createMock(\App\GitHubService::class);
        $githubService->expects($this->once())
            ->method('createIssue')
            ->with('owner/repo', 'Issue to autorepeat', null, ['Jules']);

        // notificationService should be null because it's User triggered
        $result = $this->handler->handle($project, $event, $githubService, null);
        $this->assertTrue($result);
    }

    public function testHandleCheckSuiteInProgress()
    {
        $project = ['user_id' => 1, 'project_id' => 1, 'github_repo' => 'owner/repo'];
        $this->pdo->exec("INSERT INTO projects (project_id, user_id, github_repo) VALUES (1, 1, 'owner/repo')");

        $prUrl = 'https://github.com/owner/repo/pull/123';

        // Pre-seed task
        $stmt = $this->pdo->prepare("INSERT INTO tasks (user_id, project_id, issue_number, title, pr_url, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([1, 1, 123, 'Issue title', $prUrl, 'implemented']);

        $event = [
            'action' => 'requested',
            'check_suite' => [
                'status' => 'in_progress',
                'conclusion' => null,
                'pull_requests' => [
                    ['url' => 'https://api.github.com/repos/owner/repo/pulls/123']
                ]
            ],
            'repository' => [
                'full_name' => 'owner/repo'
            ]
        ];

        $result = $this->handler->handle($project, $event);
        $this->assertTrue($result);

        $stmt = $this->pdo->prepare("SELECT status FROM tasks WHERE pr_url = ?");
        $stmt->execute([$prUrl]);
        $status = $stmt->fetchColumn();

        $this->assertEquals('checking', $status);
    }
}
