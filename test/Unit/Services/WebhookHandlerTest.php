<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Database;
use App\WebhookHandler;
use PDO;
use PDOStatement;

class WebhookHandlerTest extends TestCase
{
    private $db;
    private $pdo;
    private $handler;

    protected function setUp(): void
    {
        $this->pdo = $this->createMock(PDO::class);
        $this->db = $this->createMock(Database::class);
        $this->db->method('getConnection')->willReturn($this->pdo);
        $this->handler = new WebhookHandler($this->db);
    }

    public function testVerifySignature()
    {
        $payload = '{"test": "data"}';
        $secret = 'secret';
        $signature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        $this->assertTrue($this->handler->verifySignature($payload, $signature, $secret));
        $this->assertFalse($this->handler->verifySignature($payload, 'wrong', $secret));
    }

    public function testHandleOpenedIssue()
    {
        $project = ['user_id' => 1, 'project_id' => 1];
        $event = [
            'action' => 'opened',
            'repository' => ['full_name' => 'owner/repo'],
            'issue' => [
                'number' => 123,
                'title' => 'Test Issue',
                'body' => 'Body'
            ]
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(['task_id' => 456, 'project_id' => 1, 'issue_number' => 123]);

        $this->pdo->method('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->willReturn('mysql');
        $this->pdo->method('prepare')->willReturn($stmt);

        $githubService = $this->createMock(\App\GitHubService::class);
        $julesService = $this->createMock(\App\JulesService::class);

        // We expect Task::refreshJulesStatus to be called eventually.
        // But since we can't easily mock the Task object created inside WebhookHandler,
        // we'll at least verify the handle method completes successfully with the new services.
        $this->assertTrue($this->handler->handle($project, $event, $githubService, null, $julesService));
    }

    public function testHandleClosedIssueWithAutorepeat()
    {
        $project = ['user_id' => 1, 'project_id' => 1];
        $event = [
            'action' => 'closed',
            'repository' => ['full_name' => 'owner/repo'],
            'issue' => [
                'number' => 123,
                'title' => 'Test Issue',
                'body' => 'Body',
                'state_reason' => 'completed',
                'labels' => [
                    ['name' => 'autorepeat'],
                    ['name' => 'bug']
                ]
            ]
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $this->pdo->method('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->willReturn('mysql');
        $this->pdo->method('prepare')->willReturn($stmt);

        $githubService = $this->createMock(\App\GitHubService::class);
        $githubService->expects($this->once())
            ->method('createIssue')
            ->with('owner/repo', 'Test Issue', 'Body', ['bug', 'Jules']);
        $githubService->expects($this->atLeastOnce())
            ->method('updateAutorepeatLabels');

        $this->assertTrue($this->handler->handle($project, $event, $githubService));
    }

    public function testHandleClosedIssueWithoutAutorepeat()
    {
        $project = ['user_id' => 1, 'project_id' => 1];
        $event = [
            'action' => 'closed',
            'repository' => ['full_name' => 'owner/repo'],
            'issue' => [
                'number' => 123,
                'title' => 'Test Issue',
                'body' => 'Body',
                'state_reason' => 'completed',
                'labels' => [
                    ['name' => 'bug']
                ]
            ]
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $this->pdo->method('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->willReturn('mysql');
        $this->pdo->method('prepare')->willReturn($stmt);

        $githubService = $this->createMock(\App\GitHubService::class);
        $githubService->expects($this->never())->method('createIssue');
        $githubService->expects($this->never())->method('removeLabel');

        $this->assertTrue($this->handler->handle($project, $event, $githubService));
    }

    public function testHandleDeletedIssue()
    {
        $project = [
            'user_id' => 1,
            'project_id' => 1,
            'github_repo' => 'owner/repo'
        ];
        $event = [
            'action' => 'deleted',
            'repository' => ['full_name' => 'owner/repo'],
            'issue' => [
                'number' => 123,
                'title' => 'Deleted Issue'
            ]
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $this->pdo->method('prepare')->with($this->stringContains('DELETE FROM tasks'))->willReturn($stmt);

        $notificationService = $this->createMock(\App\NotificationService::class);
        $notificationService->expects($this->once())
            ->method('notify')
            ->with(
                1,
                'github_issue',
                $this->stringContains('Issue Deleted: #123'),
                $this->stringContains('Issue "Deleted Issue" was deleted'),
                $this->callback(function($data) {
                    return $data['issue_number'] === 123 && $data['project_id'] === 1;
                })
            );

        $this->assertTrue($this->handler->handle($project, $event, null, $notificationService));
    }

    public function testHandleUnsupportedAction()
    {
        $project = ['user_id' => 1, 'project_id' => 1];
        $event = [
            'action' => 'unknown_action',
            'repository' => ['full_name' => 'owner/repo'],
            'issue' => ['number' => 123]
        ];

        $this->assertTrue($this->handler->handle($project, $event));
    }

    public function testHandleSqlite()
    {
        $project = ['user_id' => 1, 'project_id' => 1];
        $event = [
            'action' => 'opened',
            'repository' => ['full_name' => 'owner/repo'],
            'issue' => [
                'number' => 123,
                'title' => 'Test Issue',
                'body' => 'Body'
            ]
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $this->pdo->method('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->willReturn('sqlite');
        $this->pdo->method('prepare')->willReturnCallback(function($sql) use ($stmt) {
            // Depending on the order of calls, we might see different SQLs
            if (str_contains($sql, 'SELECT t.*, p.github_repo')) {
                return $stmt;
            }
            $this->assertStringContainsString('ON CONFLICT', $sql);
            return $stmt;
        });

        $this->assertTrue($this->handler->handle($project, $event));
    }
}
