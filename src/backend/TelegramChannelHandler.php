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
        // convertImagesToLinks already performs htmlspecialchars on non-link text
        $message = $taskModel->convertImagesToLinks($notification['message']);
        $sourceUrl = $notification['data']['source_url'] ?? null;

        $text = "<b>" . $title . "</b>\n\n";
        $text .= $message;

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
            $response = $this->telegramService->withToken($botToken)->sendMessage($chatId, $text, $extraParams);

            // Capture message_id and update notification if available
            $messageId = $response['result']['message_id'] ?? null;
            if ($messageId && isset($notification['notification_id'])) {
                $this->updateNotificationMetadata((int)$notification['notification_id'], (int)$chatId, (int)$messageId);
            }

            return true;
        } catch (\Exception $e) {
            error_log("Telegram Send Error for user $userId: " . $e->getMessage());
            return false;
        }
    }

    private function updateNotificationMetadata(int $notificationId, int $chatId, int $messageId): void
    {
        $db = $this->userModel->getDb()->getConnection();

        $stmt = $db->prepare("SELECT data FROM notifications WHERE notification_id = ?");
        $stmt->execute([$notificationId]);
        $row = $stmt->fetch();

        if (!$row) {
            return;
        }

        $data = json_decode($row['data'], true) ?: [];
        $data['telegram_chat_id'] = $chatId;
        $data['telegram_message_id'] = $messageId;

        $stmt = $db->prepare("UPDATE notifications SET data = ? WHERE notification_id = ?");
        $stmt->execute([json_encode($data), $notificationId]);
    }
}
