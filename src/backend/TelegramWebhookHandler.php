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

    protected function getTaskModel(): Task
    {
        return new Task($this->userModel->getDb());
    }

    protected function getProjectModel(): Project
    {
        return new Project($this->userModel->getDb());
    }

    protected function getNotificationService(): NotificationService
    {
        return new NotificationService($this->userModel->getDb());
    }

    protected function getJulesService(?string $apiKey = null): JulesService
    {
        return new JulesService(null, $apiKey);
    }

    protected function getProjectGitHubService(string $token): GitHubService
    {
        return new GitHubService(null, $token);
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

        if (str_starts_with($text, '/search ')) {
            $query = trim(substr($text, 8));
            return $this->handleSearch($chatId, $query);
        }

        if (str_starts_with($text, '/task ')) {
            $issueNumber = trim(substr($text, 6));
            if (is_numeric($issueNumber)) {
                return $this->handleTaskByNumber($chatId, (int)$issueNumber);
            }
        }

        if ($text === '/settings') {
            return $this->handleSettings($chatId);
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
        $helpText .= "/task &lt;number&gt; - View details of a specific task.\n";
        $helpText .= "/settings - Manage notification preferences.\n";
        $helpText .= "/search &lt;query&gt; - Search for active tasks.\n";
        $helpText .= "/cleanup - Remove read notifications from the chat.\n";
        $helpText .= "/help - Show this help message.";

        $this->telegramService->sendMessage($chatId, $helpText);
        return true;
    }

    private function handleTaskByNumber(int $chatId, int $issueNumber): bool
    {
        $user = $this->userModel->findByTelegramChatId($chatId);
        if (!$user) {
            $this->telegramService->sendMessage($chatId, "Unauthorized. Please link your account.");
            return true;
        }

        $taskModel = $this->getTaskModel();
        $tasks = $taskModel->findActiveBySearch((int)$user['user_id'], (string)$issueNumber);

        // Filter for exact issue number match
        $exactMatch = null;
        foreach ($tasks as $task) {
            if ((int)$task['issue_number'] === $issueNumber) {
                $exactMatch = $task;
                break;
            }
        }

        if (!$exactMatch) {
            $this->telegramService->sendMessage($chatId, "Task #$issueNumber not found among active tasks.");
            return true;
        }

        // Reuse view_task logic by simulating a callback or calling the handler
        return $this->handleViewTask($chatId, (int)$exactMatch['task_id']);
    }

    private function handleViewTask(int $chatId, int $taskId, ?int $editMessageId = null): bool
    {
        $user = $this->userModel->findByTelegramChatId($chatId);
        if (!$user) return false;

        $taskModel = $this->getTaskModel();
        $task = $taskModel->findById($taskId);

        if (!$task || (int)$task['user_id'] !== (int)$user['user_id']) {
            $this->telegramService->sendMessage($chatId, "Task not found or access denied.");
            return true;
        }

        $projectModel = $this->getProjectModel();
        $project = $projectModel->findById($task['project_id']);
        $repo = $project['github_repo'] ?? '';

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
        $actions[] = ['text' => '📋 View Logs', 'callback_data' => "view_logs:$taskId"];

        $inlineKeyboard = array_chunk($actions, 2);
        $inlineKeyboard[] = [['text' => '⬅️ Back to Tasks', 'callback_data' => 'list_tasks:' . $task['project_id']]];

        if ($editMessageId) {
            $this->telegramService->editMessageText($chatId, $editMessageId, $text, [
                'reply_markup' => ['inline_keyboard' => $inlineKeyboard]
            ]);
        } else {
            $this->telegramService->sendMessage($chatId, $text, [
                'reply_markup' => ['inline_keyboard' => $inlineKeyboard]
            ]);
        }
        return true;
    }

    private function handleViewLogs(int $chatId, int $taskId, ?int $editMessageId = null): bool
    {
        $user = $this->userModel->findByTelegramChatId($chatId);
        if (!$user) return false;

        $taskModel = $this->getTaskModel();
        $task = $taskModel->findById($taskId);

        if (!$task || (int)$task['user_id'] !== (int)$user['user_id']) {
            $this->telegramService->sendMessage($chatId, "Task not found or access denied.");
            return true;
        }

        $logs = $taskModel->getLogs($taskId);
        $recentLogs = array_slice($logs, -10);

        $text = "<b>Recent Logs for #" . $task['issue_number'] . ":</b>\n\n";
        if (empty($recentLogs)) {
            $text .= "<i>No logs found for this task.</i>";
        } else {
            foreach ($recentLogs as $log) {
                $time = date('H:i:s', strtotime($log['created_at']));
                $level = strtoupper($log['level']);
                $emoji = match ($log['level']) {
                    'error', 'critical' => '❌',
                    'warning' => '⚠️',
                    default => 'ℹ️'
                };
                $text .= "$emoji <code>[$time]</code> $level: " . htmlspecialchars($log['message']) . "\n";
            }
        }

        $inlineKeyboard = [[
            ['text' => '⬅️ Back to Task', 'callback_data' => "view_task:$taskId"]
        ]];

        if ($editMessageId) {
            $this->telegramService->editMessageText($chatId, $editMessageId, $text, [
                'reply_markup' => ['inline_keyboard' => $inlineKeyboard]
            ]);
        } else {
            $this->telegramService->sendMessage($chatId, $text, [
                'reply_markup' => ['inline_keyboard' => $inlineKeyboard]
            ]);
        }
        return true;
    }

    private function handleProjects(int $chatId): bool
    {
        $user = $this->userModel->findByTelegramChatId($chatId);
        if (!$user) {
            $this->telegramService->sendMessage($chatId, "Unauthorized. Please link your account.");
            return true;
        }

        $projectModel = $this->getProjectModel();
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

    private function handleToggleSetting(string $callbackId, int $chatId, string $channel, ?int $messageId): bool
    {
        $user = $this->userModel->findByTelegramChatId($chatId);
        if (!$user) {
            $this->telegramService->answerCallbackQuery($callbackId, [
                'text' => "Unauthorized.",
                'show_alert' => true
            ]);
            return false;
        }

        $notificationService = $this->getNotificationService();
        $settings = $notificationService->getUserSettings((int)$user['user_id']);

        $newValue = !($settings[$channel] ?? false);
        $notificationService->updateUserSettings((int)$user['user_id'], [$channel => $newValue]);

        $this->telegramService->answerCallbackQuery($callbackId, [
            'text' => "Setting updated."
        ]);

        if ($messageId) {
            $this->handleSettings($chatId, $messageId);
        }

        return true;
    }

    private function handleSearch(int $chatId, string $query, int $page = 1, ?int $editMessageId = null): bool
    {
        $user = $this->userModel->findByTelegramChatId($chatId);
        if (!$user) {
            if (!$editMessageId) {
                $this->telegramService->sendMessage($chatId, "Unauthorized. Please link your account.");
            }
            return true;
        }

        $pageSize = 10;
        $offset = ($page - 1) * $pageSize;

        $taskModel = $this->getTaskModel();
        $tasks = $taskModel->findActiveBySearch((int)$user['user_id'], $query);

        if (empty($tasks)) {
            $msg = "No active tasks found matching \"$query\".";
            if ($editMessageId) {
                $this->telegramService->editMessageText($chatId, $editMessageId, $msg);
            } else {
                $this->telegramService->sendMessage($chatId, $msg);
            }
            return true;
        }

        $totalTasks = count($tasks);
        $tasksPage = array_slice($tasks, $offset, $pageSize);

        $text = "<b>Search Results for \"$query\":</b>\n\n";
        $inlineKeyboard = [];
        foreach ($tasksPage as $task) {
            $statusEmoji = $taskModel->getStatusEmoji($task['status']);
            $text .= "$statusEmoji <b>#" . $task['issue_number'] . "</b>: " . htmlspecialchars($task['title']) . "\n";

            $inlineKeyboard[] = [[
                'text' => "#" . $task['issue_number'] . " Actions",
                'callback_data' => "view_task:" . $task['task_id']
            ]];
        }

        $navButtons = [];
        if ($page > 1) {
            $navButtons[] = [
                'text' => "⬅️ Previous",
                'callback_data' => "search_tasks:" . $query . ":" . ($page - 1)
            ];
        }
        if ($totalTasks > $offset + $pageSize) {
            $navButtons[] = [
                'text' => "Next ➡️",
                'callback_data' => "search_tasks:" . $query . ":" . ($page + 1)
            ];
        }

        if (!empty($navButtons)) {
            $inlineKeyboard[] = $navButtons;
        }

        $start = $offset + 1;
        $end = min($offset + $pageSize, $totalTasks);
        $text .= "\n<i>Showing $start-$end of $totalTasks results.</i>";

        if ($editMessageId) {
            $this->telegramService->editMessageText($chatId, $editMessageId, $text, [
                'reply_markup' => ['inline_keyboard' => $inlineKeyboard]
            ]);
        } else {
            $this->telegramService->sendMessage($chatId, $text, [
                'reply_markup' => ['inline_keyboard' => $inlineKeyboard]
            ]);
        }
        return true;
    }

    private function handleTasks(int $chatId, $projectId = null, int $page = 1, ?int $editMessageId = null): bool
    {
        $user = $this->userModel->findByTelegramChatId($chatId);
        if (!$user) {
            if (!$editMessageId) {
                $this->telegramService->sendMessage($chatId, "Unauthorized. Please link your account.");
            }
            return true;
        }

        $pageSize = 10;
        $offset = ($page - 1) * $pageSize;

        $taskModel = $this->getTaskModel();
        $filter = null;
        if (is_string($projectId) && !is_numeric($projectId)) {
            $filter = $projectId;
            $projectId = null;
        }

        if ($projectId) {
            $tasks = $taskModel->findActiveByProjectId((int)$projectId);
            $projectModel = $this->getProjectModel();
            $project = $projectModel->findById((int)$projectId);
            $repoName = $project['github_repo'] ?? 'Project';
            $text = "<b>Active Tasks for $repoName:</b>\n\n";
        } elseif ($filter) {
            $tasks = $taskModel->findByFilter((int)$user['user_id'], $filter);
            $filterLabel = ucwords(str_replace('_', ' ', $filter));
            $text = "<b>Tasks Filtering by $filterLabel:</b>\n\n";
        } else {
            $tasks = $taskModel->findActiveByUserProjects((int)$user['user_id']);
            $text = "<b>Active Tasks:</b>\n\n";
        }

        if (empty($tasks)) {
            if ($editMessageId) {
                $this->telegramService->editMessageText($chatId, $editMessageId, $projectId ? "No active tasks found for this project." : "No active tasks found.");
            } else {
                $this->telegramService->sendMessage($chatId, $projectId ? "No active tasks found for this project." : "No active tasks found.");
            }
            return true;
        }

        $totalTasks = count($tasks);
        $tasksPage = array_slice($tasks, $offset, $pageSize);

        $inlineKeyboard = [];
        foreach ($tasksPage as $task) {
            $statusEmoji = $taskModel->getStatusEmoji($task['status']);
            $text .= "$statusEmoji <b>#" . $task['issue_number'] . "</b>: " . htmlspecialchars($task['title']) . "\n";

            $inlineKeyboard[] = [[
                'text' => "#" . $task['issue_number'] . " Actions",
                'callback_data' => "view_task:" . $task['task_id']
            ]];
        }

        $navButtons = [];
        if ($page > 1) {
            $navButtons[] = [
                'text' => "⬅️ Previous",
                'callback_data' => "list_tasks:" . ($projectId ?: ($filter ?: 0)) . ":" . ($page - 1)
            ];
        }
        if ($totalTasks > $offset + $pageSize) {
            $navButtons[] = [
                'text' => "Next ➡️",
                'callback_data' => "list_tasks:" . ($projectId ?: ($filter ?: 0)) . ":" . ($page + 1)
            ];
        }

        if (!empty($navButtons)) {
            $inlineKeyboard[] = $navButtons;
        }

        $start = $offset + 1;
        $end = min($offset + $pageSize, $totalTasks);
        $text .= "\n<i>Showing $start-$end of $totalTasks tasks.</i>";

        if ($editMessageId) {
            $this->telegramService->editMessageText($chatId, $editMessageId, $text, [
                'reply_markup' => ['inline_keyboard' => $inlineKeyboard]
            ]);
        } else {
            $this->telegramService->sendMessage($chatId, $text, [
                'reply_markup' => ['inline_keyboard' => $inlineKeyboard]
            ]);
        }
        return true;
    }

    private function handleSettings(int $chatId, ?int $editMessageId = null): bool
    {
        $user = $this->userModel->findByTelegramChatId($chatId);
        if (!$user) {
            if (!$editMessageId) {
                $this->telegramService->sendMessage($chatId, "Unauthorized. Please link your account.");
            }
            return true;
        }

        $notificationService = $this->getNotificationService();
        $settings = $notificationService->getUserSettings((int)$user['user_id']);

        $text = "<b>Notification Settings:</b>\n\nToggle channels to enable/disable notifications:";
        $inlineKeyboard = [];

        $channels = [
            'telegram' => 'Telegram',
            'in_app' => 'In-App Inbox',
            'browser' => 'Browser'
        ];

        foreach ($channels as $key => $label) {
            $enabled = $settings[$key] ?? false;
            $statusEmoji = $enabled ? "✅" : "❌";
            $inlineKeyboard[] = [[
                'text' => "$label: $statusEmoji",
                'callback_data' => "toggle_setting:$key"
            ]];
        }

        if ($editMessageId) {
            $this->telegramService->editMessageText($chatId, $editMessageId, $text, [
                'reply_markup' => ['inline_keyboard' => $inlineKeyboard]
            ]);
        } else {
            $this->telegramService->sendMessage($chatId, $text, [
                'reply_markup' => ['inline_keyboard' => $inlineKeyboard]
            ]);
        }
        return true;
    }

    private function handleStatus(int $chatId, ?int $editMessageId = null): bool
    {
        $user = $this->userModel->findByTelegramChatId($chatId);
        if (!$user) {
            if (!$editMessageId) {
                $this->telegramService->sendMessage($chatId, "Unauthorized. Please link your account.");
            }
            return true;
        }

        $taskModel = $this->getTaskModel();
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

        $inlineKeyboard = [
            [
                ['text' => '📝 Open Issues', 'callback_data' => 'list_tasks:open_issues'],
                ['text' => '🚧 Jules Working', 'callback_data' => 'list_tasks:jules_executing']
            ],
            [
                ['text' => '🔍 GH Running', 'callback_data' => 'list_tasks:github_running'],
                ['text' => '🚀 GH Passed', 'callback_data' => 'list_tasks:github_passed']
            ],
            [
                ['text' => '❌ GH Failed', 'callback_data' => 'list_tasks:github_failed'],
                ['text' => '❌ Jules Failed', 'callback_data' => 'list_tasks:jules_failed']
            ]
        ];

        if ($editMessageId) {
            $this->telegramService->editMessageText($chatId, $editMessageId, $text, [
                'reply_markup' => ['inline_keyboard' => $inlineKeyboard]
            ]);
        } else {
            $this->telegramService->sendMessage($chatId, $text, [
                'reply_markup' => ['inline_keyboard' => $inlineKeyboard]
            ]);
        }
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
        $targetIdRaw = $parts[1] ?? null;
        $targetId = is_numeric($targetIdRaw) ? (int)$targetIdRaw : null;
        $notificationId = isset($parts[2]) ? (int)$parts[2] : null;

        if ($action === 'list_tasks') {
            $this->telegramService->answerCallbackQuery($callbackId);
            $page = isset($parts[2]) ? (int)$parts[2] : 1;
            $target = is_numeric($targetIdRaw) ? (int)$targetIdRaw : $targetIdRaw;
            return $this->handleTasks($chatId, $target ?: null, $page, $callbackQuery['message']['message_id'] ?? null);
        }

        if ($action === 'search_tasks') {
            $this->telegramService->answerCallbackQuery($callbackId);
            $query = $parts[1] ?? '';
            $page = isset($parts[2]) ? (int)$parts[2] : 1;
            return $this->handleSearch($chatId, $query, $page, $callbackQuery['message']['message_id'] ?? null);
        }

        if ($action === 'toggle_setting') {
            $channel = $parts[1] ?? '';
            return $this->handleToggleSetting($callbackId, $chatId, $channel, $callbackQuery['message']['message_id'] ?? null);
        }

        if (!$targetId || !in_array($action, ['retry', 'restart', 'merge', 'acknowledge', 'approve_plan', 'fix_bug', 'view_task', 'refresh_task', 'search_tasks', 'view_logs'])) {
            $this->telegramService->answerCallbackQuery($callbackId, [
                'text' => "Invalid action.",
                'show_alert' => true
            ]);
            return false;
        }

        // 3. Verify project permissions
        $taskModel = $this->getTaskModel();
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
        if ($action !== 'view_task' && $action !== 'view_logs') {
            $this->telegramService->answerCallbackQuery($callbackId, [
                'text' => "Processing " . ucfirst($action) . "..."
            ]);
        } else {
            $this->telegramService->answerCallbackQuery($callbackId);
        }

        if ($notificationId && $action !== 'list_tasks' && $action !== 'search_tasks') {
            $notificationService = $this->getNotificationService();
            $notificationService->markAsRead($notificationId, false);
        }

        try {
            $projectModel = $this->getProjectModel();
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
                return $this->handleViewTask($chatId, $taskId, $callbackQuery['message']['message_id'] ?? null);
            } elseif ($action === 'view_logs') {
                return $this->handleViewLogs($chatId, $taskId, $callbackQuery['message']['message_id'] ?? null);
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
                $julesService = $this->getJulesService($user['jules_api_key'] ?? null);
                $notificationService = $this->getNotificationService();
                $projectGhs = $this->getProjectGitHubService($project['github_token']);
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

        $notificationService = $this->getNotificationService();
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
