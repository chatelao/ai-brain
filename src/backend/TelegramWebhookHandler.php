<?php

namespace App;

use App\Task;

class TelegramWebhookHandler
{
    public function __construct(
        private User $userModel,
        private TelegramService $telegramService,
        private ?string $webhookSecret
    ) {
    }

    public function setWebhookSecret(?string $webhookSecret): void
    {
        $this->webhookSecret = $webhookSecret;
    }

    public function verifySecret(string $providedSecret): bool
    {
        return !empty($this->webhookSecret) && hash_equals($this->webhookSecret, $providedSecret);
    }

    public function handle(array $update): bool
    {
        if (isset($update['callback_query'])) {
            return $this->handleCallback($update['callback_query']);
        }

        $message = $update['message'] ?? null;
        if (!$message) {
            return false;
        }

        $chatId = $message['chat']['id'] ?? null;
        $text = $message['text'] ?? '';

        if (!$chatId) {
            return false;
        }

        if (str_starts_with($text, '/start ')) {
            $token = substr($text, 7);
            return $this->handleLink($chatId, $token);
        }

        if ($text === '/start') {
            $this->telegramService->sendMessage($chatId, "Welcome! To link your account, please use the link provided in the Agent Control dashboard.");
            return true;
        }

        return false;
    }

    private function handleCallback(array $callbackQuery): bool
    {
        $callbackId = $callbackQuery['id'];
        $data = $callbackQuery['data'] ?? '';
        $chatId = $callbackQuery['message']['chat']['id'] ?? null;

        if (!$chatId) {
            return false;
        }

        // 1. Authenticate user by chatId
        $user = $this->userModel->findByTelegramChatId($chatId);
        if (!$user) {
            $this->telegramService->answerCallbackQuery($callbackId, [
                'text' => "Unauthorized. Please link your account.",
                'show_alert' => true
            ]);
            return false;
        }

        // 2. Parse action and taskId
        // Expected format: "action:taskId"
        $parts = explode(':', $data);
        $action = $parts[0] ?? '';
        $taskId = isset($parts[1]) ? (int)$parts[1] : null;

        if (!$taskId || !in_array($action, ['retry', 'restart', 'merge'])) {
            $this->telegramService->answerCallbackQuery($callbackId, [
                'text' => "Invalid action.",
                'show_alert' => true
            ]);
            return false;
        }

        // 3. Verify project permissions
        $taskModel = new Task($this->userModel->getDb());
        $task = $taskModel->findById($taskId);

        if (!$task || (int)$task['user_id'] !== (int)$user['user_id']) {
            $this->telegramService->answerCallbackQuery($callbackId, [
                'text' => "Task not found or access denied.",
                'show_alert' => true
            ]);
            return false;
        }

        // 4. Acknowledge the query
        $this->telegramService->answerCallbackQuery($callbackId, [
            'text' => "Processing " . ucfirst($action) . "..."
        ]);

        // Note: Actual execution via GHS or JS will be implemented in Phase 3 & 4.
        // For now, we just acknowledge that the infrastructure is ready.
        $this->telegramService->sendMessage($chatId, "Infrastructure for <b>$action</b> is ready. Actual execution will be implemented soon.");

        return true;
    }

    private function handleLink(int $chatId, string $token): bool
    {
        if ($this->userModel->linkTelegramAccount($token, $chatId)) {
            $this->telegramService->sendMessage($chatId, "Success! Your Telegram account has been linked to Agent Control.");
            return true;
        } else {
            $this->telegramService->sendMessage($chatId, "Invalid or expired linking token.");
            return false;
        }
    }
}
