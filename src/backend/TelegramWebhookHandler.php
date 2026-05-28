<?php

namespace App;

use App\Task;
use App\Project;
use App\GitHubService;
use App\JulesService;
use App\NotificationService;

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

        if ($text === '/cleanup') {
            return $this->handleCleanup($chatId);
        }

        if ($text === '/status') {
            return $this->handleStatus($chatId);
        }

        if ($text === '/projects') {
            return $this->handleProjects($chatId);
        }

        if ($text === '/tasks') {
            return $this->handleTasks($chatId);
        }

        if ($text === '/help') {
            return $this->handleHelp($chatId);
        }

        return false;
    }

    private function handleHelp(int $chatId): bool
    {
        $helpText = "<b>Available Commands:</b>\n\n";
        $helpText .= "/status - Get a summary of task counts.\n";
        $helpText .= "/projects - List your active projects.\n";
        $helpText .= "/tasks - List active tasks with details.\n";
        $helpText .= "/cleanup - Remove read notifications from the chat.\n";
        $helpText .= "/help - Show this help message.";

        $this->telegramService->sendMessage($chatId, $helpText);
        return true;
    }

    private function handleProjects(int $chatId): bool
    {
        $user = $this->userModel->findByTelegramChatId($chatId);
        if (!$user) {
            $this->telegramService->sendMessage($chatId, "Unauthorized. Please link your account.");
            return true;
        }

        $projectModel = new Project($this->userModel->getDb());
        $projects = $projectModel->findByUserId((int)$user['user_id']);

        if (empty($projects)) {
            $this->telegramService->sendMessage($chatId, "No projects found.");
            return true;
        }

        $text = "<b>Your Projects:</b>\n\nSelect a project to view its active tasks:";
        $inlineKeyboard = [];
        foreach ($projects as $project) {
            $inlineKeyboard[] = [[
                'text' => "📁 " . $project['github_repo'],
                'callback_data' => "list_tasks:" . $project['project_id']
            ]];
        }

        $this->telegramService->sendMessage($chatId, $text, [
            'reply_markup' => ['inline_keyboard' => $inlineKeyboard]
        ]);
        return true;
    }

    private function handleTasks(int $chatId, ?int $projectId = null): bool
    {
        $user = $this->userModel->findByTelegramChatId($chatId);
        if (!$user) {
            $this->telegramService->sendMessage($chatId, "Unauthorized. Please link your account.");
            return true;
        }

        $taskModel = new Task($this->userModel->getDb());
        if ($projectId) {
            $tasks = $taskModel->findActiveByProjectId($projectId);
            $projectModel = new Project($this->userModel->getDb());
            $project = $projectModel->findById($projectId);
            $repoName = $project['github_repo'] ?? 'Project';
            $text = "<b>Active Tasks for $repoName:</b>\n\n";
        } else {
            $tasks = $taskModel->findActiveByUserProjects((int)$user['user_id']);
            $text = "<b>Active Tasks:</b>\n\n";
        }

        if (empty($tasks)) {
            $this->telegramService->sendMessage($chatId, $projectId ? "No active tasks found for this project." : "No active tasks found.");
            return true;
        }

        $inlineKeyboard = [];
        foreach (array_slice($tasks, 0, 10) as $task) {
            $statusEmoji = $taskModel->getStatusEmoji($task['status']);
            $text .= "$statusEmoji <b>#" . $task['issue_number'] . "</b>: " . htmlspecialchars($task['title']) . "\n";

            $inlineKeyboard[] = [[
                'text' => "#" . $task['issue_number'] . " Actions",
                'callback_data' => "view_task:" . $task['task_id']
            ]];
        }

        if (count($tasks) > 10) {
            $text .= "\n<i>Showing 10 of " . count($tasks) . " tasks.</i>";
        }

        $this->telegramService->sendMessage($chatId, $text, [
            'reply_markup' => ['inline_keyboard' => $inlineKeyboard]
        ]);
        return true;
    }

    private function handleStatus(int $chatId): bool
    {
        $user = $this->userModel->findByTelegramChatId($chatId);
        if (!$user) {
            $this->telegramService->sendMessage($chatId, "Unauthorized. Please link your account.");
            return true;
        }

        $taskModel = new Task($this->userModel->getDb());
        $counts = $taskModel->getTaskCounts((int)$user['user_id']);

        $text = "<b>Task Status Summary</b>\n\n";
        $text .= "📝 <b>Open Issues:</b> " . ($counts['open_issues'] ?? 0) . "\n";
        $text .= "✅ <b>Completed Tasks:</b> " . ($counts['completed_tasks'] ?? 0) . "\n\n";

        $text .= "<b>Jules Status:</b>\n";
        $text .= "🚧 Processing: " . (($counts['jules_analyzing'] ?? 0) + ($counts['jules_executing'] ?? 0)) . "\n";
        $text .= "❌ Failed: " . ($counts['jules_failed'] ?? 0) . "\n\n";

        $text .= "<b>GitHub Status:</b>\n";
        $text .= "🔍 Running: " . ($counts['github_running'] ?? 0) . "\n";
        $text .= "🚀 Passed: " . ($counts['github_passed'] ?? 0) . "\n";
        $text .= "❌ Failed: " . ($counts['github_failed'] ?? 0) . "\n";

        $this->telegramService->sendMessage($chatId, $text);
        return true;
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

        // 2. Parse action and targetId
        // Expected format: "action:targetId" or "action:targetId:notificationId"
        $parts = explode(':', $data);
        $action = $parts[0] ?? '';
        $targetId = isset($parts[1]) ? (int)$parts[1] : null;
        $notificationId = isset($parts[2]) ? (int)$parts[2] : null;

        if ($action === 'list_tasks') {
            $this->telegramService->answerCallbackQuery($callbackId);
            return $this->handleTasks($chatId, $targetId ?: null);
        }

        if (!$targetId || !in_array($action, ['retry', 'restart', 'merge', 'acknowledge', 'approve_plan', 'fix_bug', 'view_task', 'refresh_task'])) {
            $this->telegramService->answerCallbackQuery($callbackId, [
                'text' => "Invalid action.",
                'show_alert' => true
            ]);
            return false;
        }

        // 3. Verify project permissions
        $taskModel = new Task($this->userModel->getDb());
        $taskId = $targetId;
        $task = $taskModel->findById($taskId);

        if (!$task || (int)$task['user_id'] !== (int)$user['user_id']) {
            $this->telegramService->answerCallbackQuery($callbackId, [
                'text' => "Task not found or access denied.",
                'show_alert' => true
            ]);
            return false;
        }

        // 4. Acknowledge the query
        if ($action !== 'view_task') {
            $this->telegramService->answerCallbackQuery($callbackId, [
                'text' => "Processing " . ucfirst($action) . "..."
            ]);
        } else {
            $this->telegramService->answerCallbackQuery($callbackId);
        }

        if ($notificationId) {
            $notificationService = new NotificationService($this->userModel->getDb());
            $notificationService->markAsRead($notificationId, false);
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

            if ($action === 'view_task') {
                $statusEmoji = $taskModel->getStatusEmoji($task['status'] ?? Task::STATUS_CREATED);
                $statusLabel = $taskModel->getStatusLabel($task['status'] ?? Task::STATUS_CREATED);
                $targetUrl = $taskModel->getTargetUrl($task, $repo);

                $text = "$statusEmoji <b>#" . $task['issue_number'] . "</b>: <a href=\"" . htmlspecialchars($targetUrl) . "\">" . htmlspecialchars($task['title']) . "</a>\n";
                $text .= "<i>Status: $statusLabel</i>\n\n";
                $text .= "Choose an action:";

                $actions = [];
                $actions[] = ['text' => '🚀 Retry', 'callback_data' => "retry:$taskId"];
                $actions[] = ['text' => '🔄 Restart', 'callback_data' => "restart:$taskId"];

                if (!empty($task['pr_url'])) {
                    $actions[] = ['text' => '✅ Merge', 'callback_data' => "merge:$taskId"];
                }

                $actions[] = ['text' => '🆗 Acknowledge', 'callback_data' => "acknowledge:$taskId"];
                $actions[] = ['text' => '🔄 Sync Status', 'callback_data' => "refresh_task:$taskId"];

                $inlineKeyboard = array_chunk($actions, 2);
                $inlineKeyboard[] = [['text' => '⬅️ Back to Tasks', 'callback_data' => 'list_tasks:' . $task['project_id']]];

                $this->telegramService->sendMessage($chatId, $text, [
                    'reply_markup' => ['inline_keyboard' => $inlineKeyboard]
                ]);
                return true;
            } elseif ($action === 'merge') {
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
            } elseif ($action === 'refresh_task') {
                $julesService = new JulesService(null, $user['jules_api_key'] ?? null);
                $notificationService = new NotificationService($this->userModel->getDb());
                $projectGhs = new GitHubService(null, $project['github_token']);
                $taskModel->refreshJulesStatus((int)$user['user_id'], $projectGhs, $julesService, $notificationService, $taskId);
                $statusText = "🔄 Status refreshed for Issue #$issueNumber.";
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

    private function handleCleanup(int $chatId): bool
    {
        $user = $this->userModel->findByTelegramChatId($chatId);
        if (!$user) {
            $this->telegramService->sendMessage($chatId, "Unauthorized. Please link your account.");
            return true;
        }

        $notificationService = new NotificationService($this->userModel->getDb());
        $notificationService->cleanupReadNotifications((int)$user['user_id']);

        $this->telegramService->sendMessage($chatId, "✅ Cleanup complete. Read notifications have been removed from Telegram.");
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
