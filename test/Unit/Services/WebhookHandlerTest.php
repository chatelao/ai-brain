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
        $projectId = 1;
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

        $this->assertTrue($this->handler->handle($projectId, $event));
    }

    public function testHandleUnsupportedAction()
    {
        $projectId = 1;
        $event = [
            'action' => 'closed',
            'issue' => ['number' => 123]
        ];

        $this->assertFalse($this->handler->handle($projectId, $event));
    }

    public function testHandleSqlite()
    {
        $projectId = 1;
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

        $this->assertTrue($this->handler->handle($projectId, $event));
    }
}
