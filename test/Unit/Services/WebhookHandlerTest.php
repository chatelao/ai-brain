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
            'issue' => [
                'number' => 123,
                'title' => 'Test Issue',
                'body' => 'Body'
            ]
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $this->pdo->method('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->willReturn('mysql');
        $this->pdo->method('prepare')->willReturn($stmt);

        $this->assertTrue($this->handler->handle($project, $event));
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
            ->with('owner/repo', 'Test Issue', 'Body', ['autorepeat', 'bug']);
        $githubService->expects($this->once())
            ->method('removeLabel')
            ->with('owner/repo', 123, 'autorepeat');

        $this->assertTrue($this->handler->handle($project, $event, $githubService));
    }

    public function testHandleClosedIssueWithoutAutorepeat()
    {
        $project = ['user_id' => 1, 'project_id' => 1];
        $event = [
            'action' => 'closed',
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

    public function testHandleUnsupportedAction()
    {
        $project = ['user_id' => 1, 'project_id' => 1];
        $event = [
            'action' => 'deleted',
            'issue' => ['number' => 123]
        ];

        $this->assertFalse($this->handler->handle($project, $event));
    }

    public function testHandleSqlite()
    {
        $project = ['user_id' => 1, 'project_id' => 1];
        $event = [
            'action' => 'opened',
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
            $this->assertStringContainsString('ON CONFLICT', $sql);
            return $stmt;
        });

        $this->assertTrue($this->handler->handle($project, $event));
    }
}
