<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\TelegramChannelHandler;
use App\User;
use App\TelegramService;
use App\Database;
use App\Logger;
use Tests\TestDatabaseTrait;
use PDO;

class TelegramChannelHandlerTest extends TestCase
{
    use TestDatabaseTrait;

    private $userModel;
    private $telegramService;
    private $handler;
    private $pdo;
    private $db;

    protected function setUp(): void
    {
        $this->pdo = $this->getTestPdo();
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $pk = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS task_logs (task_log_id $pk, user_id INTEGER, task_id INTEGER, message TEXT, level TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

        $this->db = $this->createMock(Database::class);
        $this->db->method('getConnection')->willReturn($this->pdo);

        $this->userModel = $this->createMock(User::class);
        $this->userModel->method('getDb')->willReturn($this->db);

        // Reset and initialize Logger for testing
        new Logger($this->db);

        $this->telegramService = $this->createMock(TelegramService::class);
        $this->handler = new TelegramChannelHandler($this->userModel, $this->telegramService);
    }

    public function testSendSuccess()
    {
        $notification = [
            'user_id' => 1,
            'title' => 'Test Notification',
            'message' => 'This is a test message.',
            'data' => [
                'source_url' => 'https://example.com'
            ]
        ];

        $this->userModel->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn(['user_id' => 1, 'telegram_bot_token' => 'fake-token']);

        $this->userModel->expects($this->once())
            ->method('getTelegramChatId')
            ->with(1)
            ->willReturn(123456);

        $mockService = $this->createMock(TelegramService::class);
        $this->telegramService->expects($this->once())
            ->method('withToken')
            ->with('fake-token')
            ->willReturn($mockService);

        $mockService->expects($this->once())
            ->method('sendMessage')
            ->with(123456, $this->stringContains('<b>Test Notification</b>'))
            ->willReturn(true);

        $result = $this->handler->send($notification);
        $this->assertTrue($result);
    }

    public function testSendMissingChatId()
    {
        $notification = [
            'user_id' => 1,
            'title' => 'Test Notification',
            'message' => 'This is a test message.'
        ];

        $this->userModel->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn(['user_id' => 1, 'telegram_bot_token' => 'fake-token']);

        $this->userModel->expects($this->once())
            ->method('getTelegramChatId')
            ->with(1)
            ->willReturn(null);

        $this->telegramService->expects($this->never())
            ->method('withToken');

        $result = $this->handler->send($notification);
        $this->assertFalse($result);
    }

    public function testSendWithException()
    {
        $notification = [
            'user_id' => 1,
            'title' => 'Test Notification',
            'message' => 'This is a test message.'
        ];

        $this->userModel->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn(['user_id' => 1, 'telegram_bot_token' => 'fake-token']);

        $this->userModel->expects($this->once())
            ->method('getTelegramChatId')
            ->with(1)
            ->willReturn(123456);

        $mockService = $this->createMock(TelegramService::class);
        $this->telegramService->expects($this->once())
            ->method('withToken')
            ->with('fake-token')
            ->willReturn($mockService);

        $mockService->expects($this->once())
            ->method('sendMessage')
            ->willThrowException(new \Exception('API Error'));

        $result = $this->handler->send($notification);
        $this->assertFalse($result);
    }

    public function testSendWithCustomUserToken()
    {
        $notification = [
            'user_id' => 1,
            'title' => 'Test Notification',
            'message' => 'This is a test message.'
        ];

        $this->userModel->expects($this->once())
            ->method('getTelegramChatId')
            ->with(1)
            ->willReturn(123456);

        $this->userModel->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn([
                'user_id' => 1,
                'telegram_bot_token' => 'custom-token'
            ]);

        $customService = $this->createMock(TelegramService::class);
        $this->telegramService->expects($this->once())
            ->method('withToken')
            ->with('custom-token')
            ->willReturn($customService);

        $customService->expects($this->once())
            ->method('sendMessage')
            ->with(123456, $this->stringContains('Test Notification'))
            ->willReturn(true);

        $result = $this->handler->send($notification);
        $this->assertTrue($result);
    }
}
