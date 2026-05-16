<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;
use App\Auth;
use App\User;
use App\Project;
use App\Task;
use App\JulesService;
use App\GitHubService;

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = new Database();
$userModel = new User($db);
$projectModel = new Project($db);
$taskModel = new Task($db);
$userId = $auth->getUserId();
$user = $userModel->findById($userId);

$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$julesService = new JulesService(null, $user['jules_api_key'] ?? null);

try {
    if ($projectId > 0) {
        $project = $projectModel->findById($projectId);
        if ($project && $project['user_id'] === $userId) {
            $githubToken = $project['github_token'] ?? null;
            if ($githubToken) {
                $githubService = new GitHubService(null, $githubToken);
                $taskModel->syncIssues($userId, $projectId, $project['github_repo'], $githubService);
                $taskModel->refreshJulesStatus($userId, $githubService, $julesService);
            }
        }
    } else {
        // Global refresh for dashboard
        $githubAccounts = $userModel->getGitHubAccounts($userId);
        if (!empty($githubAccounts)) {
            // Use the first account's token for refreshing Jules status
            $githubService = new GitHubService(null, $githubAccounts[0]['github_token']);
            $taskModel->refreshJulesStatus($userId, $githubService, $julesService);
        } else {
            // Fallback without token
            $githubService = new GitHubService();
            $taskModel->refreshJulesStatus($userId, $githubService, $julesService);
        }
    }

    // Re-fetch user to get updated quota
    $updatedUser = $userModel->findById($userId);

    // Calculate updated counts
    $allUserTasks = $taskModel->findByUserProjects($userId);
    $totalTasks = count($allUserTasks);
    $openIssues = 0;
    $completedTasks = 0;

    foreach ($allUserTasks as $t) {
        $ghData = json_decode($t['github_data'] ?? '{}', true);
        if (($ghData['state'] ?? '') === 'open') {
            $openIssues++;
        }
        if (($ghData['state'] ?? '') === 'closed' || ($t['status'] ?? '') === 'completed') {
            $completedTasks++;
        }
    }

    echo json_encode([
        'status' => 'success',
        'quota_usage' => $updatedUser['jules_quota_usage'] ?? 0,
        'quota_limit' => $updatedUser['jules_quota_limit'] ?? 0,
        'total_tasks' => $totalTasks,
        'open_issues' => $openIssues,
        'completed_tasks' => $completedTasks
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
