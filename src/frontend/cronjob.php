<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;
use App\User;
use App\Project;
use App\Task;
use App\GitHubService;
use App\JulesService;
use App\NotificationService;

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
$notificationService = new NotificationService($db);

$users = $userModel->getAllUsersWithProjectCount();

echo "Starting synchronization and refresh...\n";

foreach ($users as $user) {
    $userId = (int)$user['user_id'];
    echo "Processing user: " . $user['name'] . " (ID: $userId)...\n";

    $projects = $projectModel->findByUserId($userId);
    foreach ($projects as $project) {
        if (!empty($project['github_token'])) {
            echo "  Syncing project: " . $project['github_repo'] . "...\n";
            $scopedGithubService = new GitHubService(null, $project['github_token']);
            $taskModel->syncIssues($userId, $project['project_id'], $project['github_repo'], $scopedGithubService);

            $scopedJulesService = new JulesService(null, $user['jules_api_key'] ?? null);
            $taskModel->refreshJulesStatus($userId, $scopedGithubService, $scopedJulesService, $notificationService, null, $project['project_id']);
        }
    }
}

echo "Finished synchronization and refresh.\n";
