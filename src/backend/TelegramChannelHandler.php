<?php

namespace App;

use App\User;
use App\Task;
use App\TelegramService;

class TelegramChannelHandler implements NotificationChannelInterface
{
    public function __construct(
        private User $userModel,
        private TelegramService $telegramService
    ) {
    }

    public function send(array $notification, array $actions = []): bool
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
        $title = htmlspecialchars($notification['title']);
        $repo = $notification['data']['github_repo'] ?? '';
        // convertImagesToLinks already performs htmlspecialchars on non-link text
        $message = $taskModel->convertImagesToLinks($notification['message']);
        $sourceUrl = $notification['data']['source_url'] ?? null;

        $text = "<b>" . $title . "</b>\n";
        if ($repo) {
            $text .= "<i>" . htmlspecialchars($repo) . "</i>\n";
        }
        $text .= "\n" . $message;

        if ($sourceUrl) {
            $text .= "\n\n<a href=\"" . htmlspecialchars($sourceUrl) . "\">View Source</a>";
        }

        $extraParams = [];
        if (!empty($actions) && isset($notification['data']['task_id'])) {
            $taskId = $notification['data']['task_id'];
            $notificationId = $notification['notification_id'] ?? '';
            $buttons = [];
            foreach ($actions as $action) {
                $label = ucfirst($action);
                if ($action === 'merge') {
                    $label = 'Merge & Close';
                }
                $buttons[] = [
                    'text' => $label,
                    'callback_data' => "$action:$taskId" . ($notificationId ? ":$notificationId" : "")
                ];
            }
            $extraParams['reply_markup'] = [
                'inline_keyboard' => [$buttons]
            ];
        }

        try {
            return $this->telegramService->withToken($botToken)->sendMessage($chatId, $text, $extraParams);
        } catch (\Exception $e) {
            error_log("Telegram Send Error for user $userId: " . $e->getMessage());
            return false;
        }
    }
}
