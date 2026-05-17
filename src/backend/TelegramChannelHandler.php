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
        $chatId = $this->userModel->getTelegramChatId($userId);

        if (!$chatId) {
            return false;
        }

        $user = $this->userModel->findById($userId);
        $telegramService = $this->telegramService;

        if ($user && !empty($user['telegram_bot_token'])) {
            $telegramService = $this->telegramService->withToken($user['telegram_bot_token']);
        }

        $taskModel = new Task($this->userModel->getDb());
        $title = htmlspecialchars($notification['title']);
        // Task::convertImagesToLinks already handles htmlspecialchars of the non-image parts
        $message = $taskModel->convertImagesToLinks($notification['message']);
        $sourceUrl = $notification['data']['source_url'] ?? null;

        $text = "<b>" . $title . "</b>\n\n";
        $text .= $message;

        if ($sourceUrl) {
            $text .= "\n\n<a href=\"" . htmlspecialchars($sourceUrl) . "\">View Source</a>";
        }

        try {
            return $telegramService->sendMessage($chatId, $text);
        } catch (\Exception $e) {
            error_log("Telegram Send Error for user $userId: " . $e->getMessage());
            return false;
        }
    }
}
