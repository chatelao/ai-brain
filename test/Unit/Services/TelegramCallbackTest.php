<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\TelegramWebhookHandler;
use App\User;
use App\Task;
use App\Database;
use App\TelegramService;

class TelegramCallbackTest extends TestCase
{
    private $userModel;
    private $telegramService;
    private $handler;
    private $db;

    protected function setUp(): void
    {
        $this->userModel = $this->createMock(User::class);
        $this->telegramService = $this->createMock(TelegramService::class);
        $this->db = $this->createMock(Database::class);
        $githubService = $this->createMock(\App\GitHubService::class);

        $this->userModel->method('getDb')->willReturn($this->db);

        $this->handler = new TelegramWebhookHandler($this->userModel, $this->telegramService, $githubService, 'secret');
    }

    public function testHandleCallbackUnauthorized()
    {
        $update = [
            'callback_query' => [
                'id' => 'cb123',
                'data' => 'retry:456',
                'message' => ['chat' => ['id' => 123]]
            ]
        ];

        $this->userModel->expects($this->once())
            ->method('findByTelegramChatId')
            ->with(123)
            ->willReturn(null);

        $this->telegramService->expects($this->once())
            ->method('answerCallbackQuery')
            ->with('cb123', $this->callback(fn($params) => strpos($params['text'], 'Unauthorized') !== false));

        $this->assertFalse($this->handler->handle($update));
    }

    public function testHandleCallbackInvalidAction()
    {
        $update = [
            'callback_query' => [
                'id' => 'cb123',
                'data' => 'invalid:456',
                'message' => ['chat' => ['id' => 123]]
            ]
        ];

        $this->userModel->method('findByTelegramChatId')->willReturn(['user_id' => 1]);

        $this->telegramService->expects($this->once())
            ->method('answerCallbackQuery')
            ->with('cb123', $this->callback(fn($params) => strpos($params['text'], 'Invalid action') !== false));

        $this->assertFalse($this->handler->handle($update));
    }
}
