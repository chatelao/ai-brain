<?php

namespace App;

class TelegramChannelHandler implements NotificationChannelInterface
{
    public function __construct(
        private User $userModel,
        private TelegramService $telegramService
    ) {
    }

    public function send(array $notification): bool
    {
        $userId = $notification['user_id'];
        $user = $this->userModel->findById($userId);
        if (!$user) {
            return false;
        }

        $chatId = $this->userModel->getTelegramChatId($userId);
        $botToken = $user['telegram_bot_token'] ?? '';

        if (!$chatId || !$botToken) {
            return false;
        }

        $taskModel = new Task($this->userModel->getDb());
        $title = $taskModel->convertImagesToLinks($notification['title']);
        $message = $taskModel->convertImagesToLinks($notification['message']);
        $sourceUrl = $notification['data']['source_url'] ?? null;

        $text = "<b>" . $title . "</b>\n\n";
        $text .= $message;

        if ($sourceUrl) {
            $text .= "\n\n<a href=\"" . htmlspecialchars($sourceUrl) . "\">View Source</a>";
        }

        try {
            return $this->telegramService->withToken($botToken)->sendMessage($chatId, $text);
        } catch (\Exception $e) {
            // Silently fail for now, or log if needed
            return false;
        }
    }
}
