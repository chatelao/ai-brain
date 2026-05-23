<?php

namespace App;

use App\Task;

class TelegramWebhookHandler
{
    public function __construct(
        private User $userModel,
        private TelegramService $telegramService,
        private GitHubService $githubService,
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
        // Expected format: "action:taskId" or "action:taskId:notificationId"
        $parts = explode(':', $data);
        $action = $parts[0] ?? '';
        $taskId = isset($parts[1]) ? (int)$parts[1] : null;
        $notificationId = isset($parts[2]) ? (int)$parts[2] : null;

        if (!$taskId || !in_array($action, ['retry', 'restart', 'merge', 'acknowledge', 'approve_plan', 'fix_bug'])) {
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

        if ($notificationId) {
            $notificationService = new NotificationService($this->userModel->getDb());
            $notificationService->markAsRead($notificationId);
        }

        try {
            $projectModel = new Project($this->userModel->getDb());
            $project = $projectModel->findById($task['project_id']);
            if (!$project || !$project['github_token']) {
                throw new \Exception("Project or GitHub token not found.");
            }

            $ghs = $this->githubService;
            $repo = $project['github_repo'];
            $issueNumber = (int)$task['issue_number'];

            $messageId = $callbackQuery['message']['message_id'] ?? null;
            $originalText = $callbackQuery['message']['text'] ?? '';

            if ($action === 'merge') {
                $prNumber = $ghs->extractPrNumber($task['pr_url'] ?? '');
                if (!$prNumber) {
                    throw new \Exception("No pull request associated with this task.");
                }

                $ghs->mergePullRequest($repo, $prNumber, "Merged via Telegram: " . $task['title']);
                $ghs->closeIssue($repo, $issueNumber);
                $taskModel->markAsMerged($taskId);

                $statusText = "✅ PR #$prNumber merged and Issue #$issueNumber closed.";
            } elseif ($action === 'restart') {
                $ghs->removeLabel($repo, $issueNumber, 'Jules');
                $ghs->addLabel($repo, $issueNumber, 'Jules');

                $statusText = "🔄 Jules session restarted for Issue #$issueNumber.";
            } elseif ($action === 'retry') {
                $ghs->postComment($repo, $issueNumber, "retry");

                $statusText = "🚀 Retry signal sent to Issue #$issueNumber.";
            } elseif ($action === 'approve_plan') {
                $ghs->postComment($repo, $issueNumber, "approve plan");

                $statusText = "✅ Plan approved for Issue #$issueNumber.";
            } elseif ($action === 'fix_bug') {
                $ghs->addLabel($repo, $issueNumber, 'bug');
                $ghs->addLabel($repo, $issueNumber, 'Jules');

                $statusText = "🐛 Bug label added and Jules triggered for Issue #$issueNumber.";
            } elseif ($action === 'acknowledge') {
                $statusText = "✅ Acknowledged.";
            } else {
                $statusText = "Infrastructure for <b>$action</b> is ready. Actual execution will be implemented soon.";
            }

            if ($messageId) {
                $this->telegramService->editMessageText($chatId, $messageId, $originalText . "\n\n" . $statusText, [
                    'reply_markup' => ['inline_keyboard' => []]
                ]);
            } else {
                $this->telegramService->sendMessage($chatId, $statusText);
            }

        } catch (\Exception $e) {
            $this->telegramService->sendMessage($chatId, "❌ Error: " . $e->getMessage());
        }

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
