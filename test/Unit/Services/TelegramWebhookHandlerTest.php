<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\TelegramWebhookHandler;
use App\User;
use App\TelegramService;

class TelegramWebhookHandlerTest extends TestCase
{
    private $userModel;
    private $telegramService;
    private $handler;
    private $secret = 'test_secret';

    protected function setUp(): void
    {
        $this->userModel = $this->createMock(User::class);
        $this->telegramService = $this->createMock(TelegramService::class);
        $this->handler = new TelegramWebhookHandler($this->userModel, $this->telegramService, $this->secret);
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
}
