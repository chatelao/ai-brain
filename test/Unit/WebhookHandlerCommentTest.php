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
                'number' => 123,
                'html_url' => 'https://github.com/owner/repo/issues/123'
            ],
            'comment' => [
                'user' => ['login' => 'google-labs-jules[bot]'],
                'body' => 'Jules is [on it](https://jules.google.com/sessions/s123). When finished, you will see another comment.',
                'html_url' => 'https://github.com/owner/repo/issues/123#issuecomment-1'
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
            ->with(10, $this->githubService, $this->julesService, $this->notificationService, 456);

        // Mock NotificationService
        $this->notificationService->expects($this->once())
            ->method('notify')
            ->with(
                10,
                'github_comment',
                '🤖 Jules commented on #123',
                $event['comment']['body'],
                $this->callback(function($data) {
                    return $data['project_id'] === 1 &&
                           $data['issue_number'] === 123 &&
                           $data['task_id'] === 456 &&
                           $data['is_system'] === true;
                })
            );

        // Let's test the private handleIssueComment method using reflection
        $reflection = new \ReflectionClass(WebhookHandler::class);
        $method = $reflection->getMethod('handleIssueComment');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->handler, [
            $project,
            $event,
            $this->taskModel,
            $this->githubService,
            $this->julesService,
            $this->notificationService
        ]);

        $this->assertTrue($result);
    }

    public function testHandleIssueCommentNotJules()
    {
        $project = [
            'project_id' => 1,
            'user_id' => 10,
            'github_repo' => 'owner/repo'
        ];
        $event = [
            'issue' => [
                'number' => 123,
                'html_url' => 'https://github.com/owner/repo/issues/123'
            ],
            'comment' => [
                'user' => ['login' => 'someone-else'],
                'body' => 'This is a comment',
                'html_url' => 'https://github.com/owner/repo/issues/123#issuecomment-2'
            ],
            'sender' => ['type' => 'User']
        ];

        $task = [
            'task_id' => 456,
            'project_id' => 1,
            'issue_number' => 123
        ];

        $this->taskModel->expects($this->once())
            ->method('findByIssueNumber')
            ->with(1, 123)
            ->willReturn($task);

        $this->taskModel->expects($this->never())->method('extractSessionId');
        $this->taskModel->expects($this->never())->method('refreshJulesStatus');

        // Mock NotificationService
        $this->notificationService->expects($this->once())
            ->method('notify')
            ->with(
                10,
                'github_comment',
                '💬 someone-else commented on #123',
                'This is a comment',
                $this->callback(function($data) {
                    return $data['project_id'] === 1 &&
                           $data['issue_number'] === 123 &&
                           $data['task_id'] === 456 &&
                           $data['is_system'] === false;
                })
            );

        $reflection = new \ReflectionClass(WebhookHandler::class);
        $method = $reflection->getMethod('handleIssueComment');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->handler, [
            $project,
            $event,
            $this->taskModel,
            $this->githubService,
            $this->julesService,
            $this->notificationService
        ]);

        $this->assertTrue($result);
    }
}
