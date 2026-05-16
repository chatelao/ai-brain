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

        $title = $notification['title'];
        $message = $notification['message'];
        $sourceUrl = $notification['data']['source_url'] ?? null;

        $text = "<b>" . htmlspecialchars($title) . "</b>\n\n";
        $text .= htmlspecialchars($message);

        if ($sourceUrl) {
            $text .= "\n\n<a href=\"" . htmlspecialchars($sourceUrl) . "\">View Source</a>";
        }

        try {
            return $this->telegramService->sendMessage($chatId, $text);
        } catch (\Exception $e) {
            // Silently fail for now, or log if needed
            return false;
        }
    }
}
