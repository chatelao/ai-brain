<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;
use App\User;
use App\Task;
use App\GitHubService;
use App\JulesService;

// Basic security check
$cronSecret = getenv('CRON_SECRET');
if ($cronSecret && ($_GET['key'] ?? '') !== $cronSecret) {
    http_response_code(403);
    exit('Unauthorized');
}

$db = new Database();
$userModel = new User($db);
$taskModel = new Task($db);
$githubService = new GitHubService();
$julesService = new JulesService();

$users = $userModel->getAllUsersWithProjectCount();

echo "Starting Jules session state refresh...\n";

foreach ($users as $user) {
    $userId = (int)$user['user_id'];
    echo "Refreshing for user: " . $user['name'] . " (ID: $userId)...\n";

    // We need a GitHub token to fetch comments.
    // refreshJulesStatus currently takes one GitHubService.
    // If a user has multiple GitHub accounts, we might need to iterate.
    $githubAccounts = $userModel->getGitHubAccounts($userId);

    foreach ($githubAccounts as $account) {
        $scopedGithubService = new GitHubService(null, $account['github_token']);
        $scopedJulesService = new JulesService(null, $user['jules_api_key'] ?? null);

        $taskModel->refreshJulesStatus($userId, $scopedGithubService, $scopedJulesService);
    }
}

echo "Finished Jules session state refresh.\n";
