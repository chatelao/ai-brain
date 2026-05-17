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
$projectModel = new Project($db);
$taskModel = new Task($db);
$githubService = new GitHubService();
$julesService = new JulesService();

$users = $userModel->getAllUsersWithProjectCount();

echo "Starting Jules session state refresh...\n";

foreach ($users as $user) {
    $userId = (int)$user['user_id'];
    echo "Refreshing for user: " . $user['name'] . " (ID: $userId)...\n";

    // We need GitHub tokens to fetch comments.
    // Each project might use a different GitHub account.
    $projects = $projectModel->findByUserId($userId);
    $scopedJulesService = new JulesService(null, $user['jules_api_key'] ?? null);

    foreach ($projects as $project) {
        if (!empty($project['github_token'])) {
            $scopedGithubService = new GitHubService(null, $project['github_token']);
            $taskModel->refreshJulesStatus($userId, $scopedGithubService, $scopedJulesService, null, (int)$project['project_id']);
        }
    }
}

echo "Finished Jules session state refresh.\n";
