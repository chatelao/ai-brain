<?php

namespace App;

use Exception;

class JulesWebhookHandler
{
    public function __construct(
        private Database $db,
        private ?GitHubService $githubService = null,
        private ?TelegramService $telegramService = null
    ) {
    }

    public function handle(array $payload): bool
    {
        $token = $payload['jules_token'] ?? null;
        $status = $payload['status'] ?? null;
        $response = $payload['response'] ?? null;
        $error = $payload['error'] ?? null;

        if (!$token || !$status) {
            return false;
        }

        $taskModel = new Task($this->db);
        $task = $taskModel->findByJulesToken($token);

        if (!$task) {
            return false;
        }

        $userModel = new User($this->db);
        $user = $userModel->findById($task['user_id']);

        $projectModel = new Project($this->db);
        $project = $projectModel->findById($task['project_id']);

        if (!$project) {
            return false;
        }

        $githubService = $this->githubService;
        if (!$githubService && !empty($project['github_token'])) {
            $githubService = new GitHubService(null, $project['github_token']);
        }

        $telegramService = $this->telegramService;
        if (!$telegramService && $user && !empty($user['telegram_bot_token'])) {
            $telegramService = new TelegramService(null, $user['telegram_bot_token']);
        }

        $logger = new Logger($this->db);

        if ($status === 'completed') {
            $taskModel->updateAgentResponse($task['task_id'], $response, 'completed');
            $logger->log($task['user_id'], $task['task_id'], "Agent completed via webhook");

            if ($githubService) {
                $githubService->postComment($project['github_repo'], $task['issue_number'], "✅ Agent has completed the analysis via webhook:\n\n" . $response);
            }

            if ($telegramService && $user) {
                $telegramChatId = $userModel->getTelegramChatId($user['user_id']);
                if ($telegramChatId) {
                    $telegramService->sendMessage($telegramChatId, "✅ <b>Agent Completed (Webhook)</b>\nProject: {$project['github_repo']}\nIssue: #{$task['issue_number']}\n\n" . mb_substr($response, 0, 1000));
                }
            }
        } elseif ($status === 'failed') {
            $taskModel->updateStatus($task['task_id'], 'failed');
            $logger->log($task['user_id'], $task['task_id'], "Agent failed via webhook: " . ($error ?? 'Unknown error'), "error");

            if ($githubService) {
                $githubService->postComment($project['github_repo'], $task['issue_number'], "❌ Agent failed to process this issue (Webhook): " . ($error ?? 'Unknown error'));
            }

            if ($telegramService && $user) {
                $telegramChatId = $userModel->getTelegramChatId($user['user_id']);
                if ($telegramChatId) {
                    $telegramService->sendMessage($telegramChatId, "❌ <b>Agent Failed (Webhook)</b>\nProject: {$project['github_repo']}\nIssue: #{$task['issue_number']}\nError: " . ($error ?? 'Unknown error'));
                }
            }
        }

        return true;
    }
}
