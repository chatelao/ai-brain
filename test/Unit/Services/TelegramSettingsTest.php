<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\TelegramWebhookHandler;
use App\User;
use App\TelegramService;
use App\NotificationService;
use App\Database;
use PDO;
use PDOStatement;

class TelegramSettingsTest extends TestCase
{
    private $userModel;
    private $telegramService;
    private $handler;
    private $db;
    private $pdo;

    protected function setUp(): void
    {
        $this->userModel = $this->createMock(User::class);
        $this->telegramService = $this->createMock(TelegramService::class);
        $githubService = $this->createMock(\App\GitHubService::class);
        $this->db = $this->createMock(Database::class);
        $this->pdo = $this->createMock(PDO::class);

        $this->db->method('getConnection')->willReturn($this->pdo);
        $this->userModel->method('getDb')->willReturn($this->db);

        $this->handler = new TelegramWebhookHandler($this->userModel, $this->telegramService, $githubService, 'secret');
    }

    public function testHandleSettingsCommand()
    {
        $chatId = 12345;
        $userId = 1;

        $this->userModel->expects($this->once())
            ->method('findByTelegramChatId')
            ->with($chatId)
            ->willReturn(['user_id' => $userId]);

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([]);

        $this->pdo->method('prepare')->willReturn($stmt);

        $this->telegramService->expects($this->once())
            ->method('sendMessage')
            ->with(
                $chatId,
                $this->stringContains('Notification Settings'),
                $this->callback(function($params) {
                    $keyboard = $params['reply_markup']['inline_keyboard'];
                    $foundTelegram = false;
                    foreach ($keyboard as $row) {
                        if ($row[0]['callback_data'] === 'toggle_setting:telegram') {
                            $foundTelegram = true;
                        }
                    }
                    return $foundTelegram;
                })
            );

        $update = [
            'message' => [
                'chat' => ['id' => $chatId],
                'text' => '/settings'
            ]
        ];

        $this->assertTrue($this->handler->handle($update));
    }

    public function testHandleToggleSettingCallback()
    {
        $chatId = 12345;
        $userId = 1;
        $callbackId = 'cb123';
        $messageId = 6789;

        $this->userModel->expects($this->atLeastOnce())
            ->method('findByTelegramChatId')
            ->with($chatId)
            ->willReturn(['user_id' => $userId]);

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([]);

        $this->pdo->method('prepare')->willReturn($stmt);
        $this->pdo->method('getAttribute')->willReturn('sqlite');
        $this->pdo->method('beginTransaction')->willReturn(true);
        $this->pdo->method('commit')->willReturn(true);

        $this->telegramService->expects($this->once())
            ->method('answerCallbackQuery')
            ->with($callbackId, $this->callback(function($params) {
                return isset($params['text']) && str_contains($params['text'], 'Setting updated');
            }));

        $this->telegramService->expects($this->once())
            ->method('editMessageText')
            ->with($chatId, $messageId, $this->stringContains('Notification Settings'));

        $update = [
            'callback_query' => [
                'id' => $callbackId,
                'data' => 'toggle_setting:telegram',
                'message' => [
                    'chat' => ['id' => $chatId],
                    'message_id' => $messageId,
                    'text' => 'Some old text'
                ]
            ]
        ];

        $this->assertTrue($this->handler->handle($update));
    }
}
