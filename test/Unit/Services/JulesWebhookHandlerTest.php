<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\JulesWebhookHandler;
use App\Database;
use App\Task;
use App\User;
use App\Project;
use App\GitHubService;
use App\TelegramService;
use PDO;
use PDOStatement;

class JulesWebhookHandlerTest extends TestCase
{
    private $db;
    private $githubService;
    private $telegramService;
    private $handler;

    protected function setUp(): void
    {
        $this->db = $this->createMock(Database::class);
        $this->githubService = $this->createMock(GitHubService::class);
        $this->telegramService = $this->createMock(TelegramService::class);

        $this->handler = new JulesWebhookHandler(
            $this->db,
            $this->githubService,
            $this->telegramService
        );
    }

    public function testHandleInvalidPayload()
    {
        $this->assertFalse($this->handler->handle([]));
        $this->assertFalse($this->handler->handle(['jules_token' => 'test']));
    }

    public function testHandleTaskNotFound()
    {
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $this->db->method('getConnection')->willReturn($pdo);
        $pdo->method('prepare')->willReturn($stmt);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false);

        $payload = [
            'jules_token' => 'invalid_token',
            'status' => 'completed'
        ];

        $this->assertFalse($this->handler->handle($payload));
    }

    public function testHandleSuccess()
    {
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $this->db->method('getConnection')->willReturn($pdo);
        $pdo->method('prepare')->willReturn($stmt);
        $stmt->method('execute')->willReturn(true);

        // Task data
        $task = [
            'task_id' => 1,
            'user_id' => 10,
            'project_id' => 100,
            'issue_number' => 42,
            'title' => 'Test Task'
        ];

        // User data
        $user = [
            'user_id' => 10,
            'name' => 'Test User'
        ];

        // Project data
        $project = [
            'project_id' => 100,
            'github_repo' => 'owner/repo'
        ];

        $stmt->method('fetch')->willReturnOnConsecutiveCalls(
            $task,    // findByJulesToken
            $user,    // findById (User)
            $project, // findById (Project)
            ['telegram_chat_id' => 12345] // getTelegramChatId
        );

        $payload = [
            'jules_token' => 'valid_token',
            'status' => 'completed',
            'response' => 'Agent output'
        ];

        // Verify GitHub comment is posted
        $this->githubService->expects($this->once())
            ->method('postComment')
            ->with('owner/repo', 42, $this->stringContains('Agent output'));

        // Verify Telegram message is sent
        $this->telegramService->expects($this->once())
            ->method('sendMessage')
            ->with(12345, $this->stringContains('Agent Completed'));

        $this->assertTrue($this->handler->handle($payload));
    }

    public function testHandleFailure()
    {
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $this->db->method('getConnection')->willReturn($pdo);
        $pdo->method('prepare')->willReturn($stmt);
        $stmt->method('execute')->willReturn(true);

        $task = ['task_id' => 1, 'user_id' => 10, 'project_id' => 100, 'issue_number' => 42];
        $user = ['user_id' => 10];
        $project = ['project_id' => 100, 'github_repo' => 'owner/repo'];

        $stmt->method('fetch')->willReturnOnConsecutiveCalls($task, $user, $project, null);

        $payload = [
            'jules_token' => 'valid_token',
            'status' => 'failed',
            'error' => 'Some error'
        ];

        $this->githubService->expects($this->once())
            ->method('postComment')
            ->with('owner/repo', 42, $this->stringContains('Some error'));

        $this->assertTrue($this->handler->handle($payload));
    }
}
