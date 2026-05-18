<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\TelegramWebhookHandler;
use App\User;
use App\TelegramService;
use App\Task;
use App\Project;
use App\GitHubService;

class TelegramWebhookHandlerTest extends TestCase
{
    private $userModel;
    private $telegramService;
    private $githubService;
    private $taskModel;
    private $projectModel;
    private $handler;
    private $secret = 'test_secret';

    protected function setUp(): void
    {
        $this->userModel = $this->createMock(User::class);
        $this->telegramService = $this->createMock(TelegramService::class);
        $this->githubService = $this->createMock(GitHubService::class);
        $this->taskModel = $this->createMock(Task::class);
        $this->projectModel = $this->createMock(Project::class);
        $this->handler = new TelegramWebhookHandler(
            $this->userModel,
            $this->telegramService,
            $this->githubService,
            $this->secret,
            $this->taskModel,
            $this->projectModel
        );
    }

    public function testVerifySecretSuccess()
    {
        $this->assertTrue($this->handler->verifySecret($this->secret));
    }

    public function testVerifySecretFailure()
    {
        $this->assertFalse($this->handler->verifySecret('wrong_secret'));
    }

    public function testHandleStartCommandWithoutToken()
    {
        $update = [
            'message' => [
                'chat' => ['id' => 123],
                'text' => '/start'
            ]
        ];

        $this->telegramService->expects($this->once())
            ->method('sendMessage')
            ->with(123, $this->stringContains('Welcome'));

        $this->assertTrue($this->handler->handle($update));
    }

    public function testHandleStartCommandWithValidToken()
    {
        $update = [
            'message' => [
                'chat' => ['id' => 123],
                'text' => '/start valid_token'
            ]
        ];

        $this->userModel->expects($this->once())
            ->method('linkTelegramAccount')
            ->with('valid_token', 123)
            ->willReturn(true);

        $this->telegramService->expects($this->once())
            ->method('sendMessage')
            ->with(123, $this->stringContains('Success'));

        $this->assertTrue($this->handler->handle($update));
    }

    public function testHandleStartCommandWithInvalidToken()
    {
        $update = [
            'message' => [
                'chat' => ['id' => 123],
                'text' => '/start invalid_token'
            ]
        ];

        $this->userModel->expects($this->once())
            ->method('linkTelegramAccount')
            ->with('invalid_token', 123)
            ->willReturn(false);

        $this->telegramService->expects($this->once())
            ->method('sendMessage')
            ->with(123, $this->stringContains('Invalid or expired'));

        $this->assertFalse($this->handler->handle($update));
    }

    public function testHandleNonStartMessage()
    {
        $update = [
            'message' => [
                'chat' => ['id' => 123],
                'text' => 'Hello'
            ]
        ];

        $this->telegramService->expects($this->never())
            ->method('sendMessage');

        $this->assertFalse($this->handler->handle($update));
    }

    public function testHandleAcknowledgeCallback()
    {
        $update = [
            'callback_query' => [
                'id' => 'cb123',
                'data' => 'acknowledge:456',
                'message' => [
                    'chat' => ['id' => 123],
                    'message_id' => 789,
                    'text' => 'Original Text'
                ]
            ]
        ];

        $this->userModel->method('findByTelegramChatId')->willReturn(['user_id' => 1]);
        $this->taskModel->method('findById')->with(456)->willReturn([
            'task_id' => 456,
            'user_id' => 1,
            'project_id' => 10,
            'issue_number' => 100,
            'title' => 'Test Task'
        ]);

        $this->projectModel->method('findById')->with(10)->willReturn([
            'project_id' => 10,
            'github_repo' => 'owner/repo',
            'github_token' => 'test_token'
        ]);

        $this->telegramService->expects($this->once())
            ->method('answerCallbackQuery')
            ->with('cb123', $this->callback(fn($p) => str_contains($p['text'], 'Processing')));

        $this->telegramService->expects($this->once())
            ->method('editMessageText')
            ->with(123, 789, $this->stringContains('Acknowledged'), $this->callback(fn($p) => empty($p['reply_markup']['inline_keyboard'])));

        $this->assertTrue($this->handler->handle($update));
    }

    public function testHandleRetryCallback()
    {
        $update = [
            'callback_query' => [
                'id' => 'cb123',
                'data' => 'retry:456',
                'message' => [
                    'chat' => ['id' => 123],
                    'message_id' => 789,
                    'text' => 'Original Text'
                ]
            ]
        ];

        $this->userModel->method('findByTelegramChatId')->willReturn(['user_id' => 1]);
        $this->taskModel->method('findById')->with(456)->willReturn([
            'task_id' => 456,
            'user_id' => 1,
            'project_id' => 10,
            'issue_number' => 100,
            'title' => 'Test Task'
        ]);

        $this->projectModel->method('findById')->with(10)->willReturn([
            'project_id' => 10,
            'github_repo' => 'owner/repo',
            'github_token' => 'test_token'
        ]);

        $this->githubService->expects($this->once())
            ->method('postComment')
            ->with('owner/repo', 100, 'retry');

        $this->assertTrue($this->handler->handle($update));
    }

    public function testHandleRestartCallback()
    {
        $update = [
            'callback_query' => [
                'id' => 'cb123',
                'data' => 'restart:456',
                'message' => [
                    'chat' => ['id' => 123],
                    'message_id' => 789,
                    'text' => 'Original Text'
                ]
            ]
        ];

        $this->userModel->method('findByTelegramChatId')->willReturn(['user_id' => 1]);
        $this->taskModel->method('findById')->with(456)->willReturn([
            'task_id' => 456,
            'user_id' => 1,
            'project_id' => 10,
            'issue_number' => 100,
            'title' => 'Test Task'
        ]);

        $this->projectModel->method('findById')->with(10)->willReturn([
            'project_id' => 10,
            'github_repo' => 'owner/repo',
            'github_token' => 'test_token'
        ]);

        $this->githubService->expects($this->once())
            ->method('removeLabel')
            ->with('owner/repo', 100, 'Jules');
        $this->githubService->expects($this->once())
            ->method('addLabel')
            ->with('owner/repo', 100, 'Jules');

        $this->assertTrue($this->handler->handle($update));
    }

    public function testHandleMergeCallback()
    {
        $update = [
            'callback_query' => [
                'id' => 'cb123',
                'data' => 'merge:456',
                'message' => [
                    'chat' => ['id' => 123],
                    'message_id' => 789,
                    'text' => 'Original Text'
                ]
            ]
        ];

        $this->userModel->method('findByTelegramChatId')->willReturn(['user_id' => 1]);
        $this->taskModel->method('findById')->with(456)->willReturn([
            'task_id' => 456,
            'user_id' => 1,
            'project_id' => 10,
            'issue_number' => 100,
            'title' => 'Test Task',
            'pr_url' => 'https://github.com/owner/repo/pull/5'
        ]);

        $this->projectModel->method('findById')->with(10)->willReturn([
            'project_id' => 10,
            'github_repo' => 'owner/repo',
            'github_token' => 'test_token'
        ]);

        $this->githubService->expects($this->once())
            ->method('extractPrNumber')
            ->with('https://github.com/owner/repo/pull/5')
            ->willReturn(5);

        $this->githubService->expects($this->once())
            ->method('mergePullRequest')
            ->with('owner/repo', 5, $this->stringContains('Merged via Telegram'));

        $this->githubService->expects($this->once())
            ->method('closeIssue')
            ->with('owner/repo', 100);

        $this->assertTrue($this->handler->handle($update));
    }
}
