<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;
use App\Auth;
use App\User;
use App\Project;
use App\Task;
use App\JulesService;
use App\GitHubService;
use App\NotificationService;

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Release session lock to prevent blocking other requests
session_write_close();

$db = new Database();
$userModel = new User($db);
$projectModel = new Project($db);
$taskModel = new Task($db);
$userId = $auth->getUserId();
$user = $userModel->findById($userId);

$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$julesService = new JulesService(null, $user['jules_api_key'] ?? null);
$notificationService = new NotificationService($db);

try {
    // 1. Return current state immediately if fast=1 is NOT requested (wait, logic check)
    // Actually, if fast=1 we SKIP the external API calls.
    $fast = isset($_GET['fast']) && $_GET['fast'] === '1';

    if (!$fast) {
        // If we have fastcgi_finish_request, we can return current state and then refresh in background
        if (function_exists('fastcgi_finish_request')) {
            $counts = $taskModel->getTaskCounts($userId);
            echo json_encode([
                'status' => 'success',
                'quota_usage' => $user['jules_quota_usage'] ?? 0,
                'quota_limit' => $user['jules_quota_limit'] ?? 0,
                'total_tasks' => $counts['total'] ?? 0,
                'open_issues' => $counts['open_issues'] ?? 0,
                'completed_tasks' => $counts['completed_tasks'] ?? 0,
                'jules_running' => $counts['jules_running'] ?? 0,
                'jules_failed' => $counts['jules_failed'] ?? 0,
                'github_running' => $counts['github_running'] ?? 0,
                'github_passed' => $counts['github_passed'] ?? 0,
                'github_failed' => $counts['github_failed'] ?? 0
            ]);
            fastcgi_finish_request();
            ignore_user_abort(true);
        }
    }

    if (!$fast) {
        if ($projectId > 0) {
            $project = $projectModel->findById($projectId);
            if ($project && $project['user_id'] === $userId) {
                $githubToken = $project['github_token'] ?? null;
                if ($githubToken) {
                    $githubService = new GitHubService(null, $githubToken);
                    $taskModel->refreshJulesStatus($userId, $githubService, $julesService, $notificationService, null, $projectId);
                }
            }
        } else {
            // Global refresh for dashboard
            $githubAccounts = $userModel->getGitHubAccounts($userId);
            if (!empty($githubAccounts)) {
                // Use the first account's token for refreshing Jules status
                $githubService = new GitHubService(null, $githubAccounts[0]['github_token']);
                $taskModel->refreshJulesStatus($userId, $githubService, $julesService, $notificationService);
            } else {
                // Fallback without token
                $githubService = new GitHubService();
                $taskModel->refreshJulesStatus($userId, $githubService, $julesService, $notificationService);
            }
        }
    }

    // Only output JSON if it hasn't been sent yet (i.e. no fastcgi_finish_request or fast=1)
    if (!headers_sent()) {
        // Re-fetch user to get updated quota
        $updatedUser = $userModel->findById($userId);

        // Calculate updated counts
        $counts = $taskModel->getTaskCounts($userId);

        echo json_encode([
            'status' => 'success',
            'quota_usage' => $updatedUser['jules_quota_usage'] ?? 0,
            'quota_limit' => $updatedUser['jules_quota_limit'] ?? 0,
            'total_tasks' => $counts['total'] ?? 0,
            'open_issues' => $counts['open_issues'] ?? 0,
            'completed_tasks' => $counts['completed_tasks'] ?? 0,
            'jules_running' => $counts['jules_running'] ?? 0,
            'jules_failed' => $counts['jules_failed'] ?? 0,
            'github_running' => $counts['github_running'] ?? 0,
            'github_passed' => $counts['github_passed'] ?? 0,
            'github_failed' => $counts['github_failed'] ?? 0
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
