<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;
use App\JulesWebhookHandler;
use App\GitHubService;
use App\TelegramService;
use App\RateLimiter;
use App\Task;
use App\Project;
use App\User;

$db = new Database();
$rateLimiter = new RateLimiter($db);
$ip = $rateLimiter->getIpAddress();

if (!$rateLimiter->check("jules_webhook_$ip", 100, 60)) {
    http_response_code(429);
    exit('Too many webhook requests.');
}

$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

if (!$data || !isset($data['jules_token'])) {
    http_response_code(400);
    exit('Invalid payload');
}

$taskModel = new Task($db);
$task = $taskModel->findByJulesToken($data['jules_token']);

if (!$task) {
    http_response_code(401);
    exit('Invalid jules_token');
}

$projectModel = new Project($db);
$project = $projectModel->findById($task['project_id']);

$userModel = new User($db);
$user = $userModel->findById($task['user_id']);

$githubService = null;
if ($project && !empty($project['github_token'])) {
    $githubService = new GitHubService(null, $project['github_token']);
}

$telegramService = null;
if ($user && !empty($user['telegram_bot_token'])) {
    $telegramService = new TelegramService(null, $user['telegram_bot_token']);
}

$handler = new JulesWebhookHandler($db, $githubService, $telegramService);

if ($handler->handle($data)) {
    http_response_code(200);
    echo 'OK';
} else {
    http_response_code(500);
    echo 'Failed to process webhook';
}
