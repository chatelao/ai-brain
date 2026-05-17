<?php

namespace App;

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
            try {
                $this->telegramService->sendMessage($chatId, "Welcome! To link your account, please use the link provided in the Agent Control dashboard.");
                return true;
            } catch (\Exception $e) {
                error_log("Telegram Webhook Error (welcome): " . $e->getMessage());
                return false;
            }
        }

        return false;
    }

    private function handleLink(int $chatId, string $token): bool
    {
        if ($this->userModel->linkTelegramAccount($token, $chatId)) {
            try {
                $this->telegramService->sendMessage($chatId, "Success! Your Telegram account has been linked to Agent Control.");
                return true;
            } catch (\Exception $e) {
                error_log("Telegram Webhook Error (link success): " . $e->getMessage());
                return true; // Token was linked, but message failed
            }
        } else {
            try {
                $this->telegramService->sendMessage($chatId, "Invalid or expired linking token.");
            } catch (\Exception $e) {
                error_log("Telegram Webhook Error (link failure): " . $e->getMessage());
            }
            return false;
        }
    }
}
