<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Database;
use App\Auth;
use App\User;
use App\Project;
use App\Task;
use App\JulesService;
use App\GitHubService;

header('Content-Type: application/json');

$db = new Database();
$auth = new Auth($db);
$userId = $auth->getAuthenticatedUserId();

if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userModel = new User($db);
$projectModel = new Project($db);
$taskModel = new Task($db);
$user = $userModel->findById($userId);

$julesService = new JulesService(null, $user['jules_api_key'] ?? null);

try {
    $fast = isset($_GET['fast']) && $_GET['fast'] === '1';

    if (!$fast) {
        // Global refresh for dashboard
        $githubAccounts = $userModel->getGitHubAccounts($userId);
        if (!empty($githubAccounts)) {
            // Use the first account's token for refreshing Jules status
            $githubService = new GitHubService(null, $githubAccounts[0]['github_token']);
            $taskModel->refreshJulesStatus($userId, $githubService, $julesService, null);
        } else {
            $githubService = new GitHubService();
            $taskModel->refreshJulesStatus($userId, $githubService, $julesService, null);
        }
    }

    // Re-fetch user to get updated quota
    $updatedUser = $userModel->findById($userId);
    // Calculate updated counts
    $counts = $taskModel->getTaskCounts($userId);

    echo json_encode([
        'status' => 'success',
        'quota_usage' => (int)($updatedUser['jules_quota_usage'] ?? 0),
        'quota_limit' => (int)($updatedUser['jules_quota_limit'] ?? 0),
        'total_tasks' => (int)($counts['total'] ?? 0),
        'open_issues' => (int)($counts['open_issues'] ?? 0),
        'completed_tasks' => (int)($counts['completed_tasks'] ?? 0),
        'jules_analyzing' => (int)($counts['jules_analyzing'] ?? 0),
        'jules_executing' => (int)($counts['jules_executing'] ?? 0),
        'jules_failed' => (int)($counts['jules_failed'] ?? 0),
        'github_running' => (int)($counts['github_running'] ?? 0),
        'github_passed' => (int)($counts['github_passed'] ?? 0),
        'github_failed' => (int)($counts['github_failed'] ?? 0),
        'telegram_connected' => (bool)$userModel->getTelegramChatId($userId)
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
