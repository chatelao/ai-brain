<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\WebhookHandler;
use App\Database;
use App\Task;
use App\GitHubService;
use App\JulesService;
use App\NotificationService;
use PDO;
use PDOStatement;

class WebhookHandlerCommentTest extends TestCase
{
    private $db;
    private $handler;
    private $taskModel;
    private $githubService;
    private $julesService;
    private $notificationService;

    protected function setUp(): void
    {
        $this->db = $this->createMock(Database::class);
        $this->handler = new WebhookHandler($this->db);
        $this->taskModel = $this->createMock(Task::class);
        $this->githubService = $this->createMock(GitHubService::class);
        $this->julesService = $this->createMock(JulesService::class);
        $this->notificationService = $this->createMock(NotificationService::class);
    }

    public function testHandleIssueCommentWithOnIt()
    {
        $project = [
            'project_id' => 1,
            'user_id' => 10,
            'github_repo' => 'owner/repo'
        ];

        $event = [
            'repository' => ['full_name' => 'owner/repo'],
            'action' => 'created',
            'issue' => [
                'number' => 123
            ],
            'comment' => [
                'user' => ['login' => 'google-labs-jules[bot]'],
                'body' => 'Jules is [on it](https://jules.google.com/sessions/s123). When finished, you will see another comment.'
            ]
        ];

        $task = [
            'task_id' => 456,
            'project_id' => 1,
            'issue_number' => 123
        ];

        // Mock task model methods
        $this->taskModel->expects($this->once())
            ->method('extractSessionId')
            ->with($event['comment']['body'])
            ->willReturn('s123');

        $this->taskModel->expects($this->once())
            ->method('findByIssueNumber')
            ->with(1, 123)
            ->willReturn($task);

        // Mock DB connection and statement
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $this->db->method('getConnection')->willReturn($pdo);
        $pdo->method('prepare')->willReturn($stmt);
        $stmt->expects($this->once())->method('execute')->with(['s123', 456]);

        // Mock refreshJulesStatus
        $this->taskModel->expects($this->once())
            ->method('refreshJulesStatus')
            ->with(10, $this->githubService, $this->julesService, null, 456);

        // We need to inject the mock taskModel into the handler.
        // Since the handler creates Task internally, we might need to adjust the test or the handler.
        // Looking at WebhookHandler::handle, it does: $taskModel = new Task($this->db);
        // This is hard to mock without refactoring.
        // Let's use a partial mock or reflection if needed, but wait, Task is just a wrapper around DB.

        // Let's test the private handleIssueComment method using reflection to be sure.
        $reflection = new \ReflectionClass(WebhookHandler::class);
        $method = $reflection->getMethod('handleIssueComment');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->handler, [
            $project,
            $event,
            $this->taskModel,
            $this->githubService,
            $this->julesService,
            null
        ]);

        $this->assertTrue($result);
    }

    public function testHandleIssueCommentNotJules()
    {
        $project = ['project_id' => 1];
        $event = [
            'issue' => ['number' => 123],
            'comment' => [
                'user' => ['login' => 'someone-else'],
                'body' => 'on it'
            ]
        ];

        $this->taskModel->expects($this->never())->method('extractSessionId');

        $reflection = new \ReflectionClass(WebhookHandler::class);
        $method = $reflection->getMethod('handleIssueComment');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->handler, [
            $project,
            $event,
            $this->taskModel,
            $this->githubService,
            $this->julesService,
            null
        ]);

        $this->assertTrue($result);
    }
}
