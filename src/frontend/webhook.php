<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;
use App\Project;
use App\WebhookHandler;
use App\GitHubService;
use App\RateLimiter;
use App\WebhookLogger;

$db = new Database();
$rateLimiter = new RateLimiter($db);
$ip = $rateLimiter->getIpAddress();
$logger = new WebhookLogger($db);

$headers = getallheaders();
$headersStr = json_encode($headers);
$payload = file_get_contents('php://input');

if (!$rateLimiter->check("webhook_$ip", 100, 60)) {
    http_response_code(429);
    exit('Too many webhook requests. Please try again later.');
}
$projectModel = new Project($db);
$handler = new WebhookHandler($db);

$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$event = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? '';

if (empty($payload) || empty($signature) || !in_array($event, ['issues', 'ping'])) {
    http_response_code(400);
    exit('Invalid request');
}

$data = json_decode($payload, true);
$repoFullName = $data['repository']['full_name'] ?? '';

if (empty($repoFullName)) {
    http_response_code(400);
    exit('Repository information missing');
}

$projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

if ($projectId > 0) {
    $project = $projectModel->findById($projectId);
    if (!$project || $project['github_repo'] !== $repoFullName) {
        http_response_code(404);
        exit('Project not found or repository mismatch');
    }
    $projects = [$project];
} else {
    $projects = $projectModel->findByRepo($repoFullName);
}

if (empty($projects)) {
    http_response_code(404);
    exit('Project not found');
}

$verified = false;
$matchingProject = null;

foreach ($projects as $project) {
    if ($handler->verifySignature($payload, $signature, $project['webhook_secret'])) {
        $verified = true;
        $matchingProject = $project;
        break;
    }
}

if (!$verified) {
    // Log failure for the first project found as a fallback if we can't verify any
    if (!empty($projects)) {
        $logger->log($projects[0]['user_id'], 'github', $payload, $headersStr, 401, 'Invalid signature');
    }
    http_response_code(401);
    exit('Invalid signature');
}

if ($event === 'ping') {
    http_response_code(200);
    exit('PONG');
}

$githubService = null;
if (!empty($matchingProject['github_token'])) {
    $githubService = new GitHubService(null, $matchingProject['github_token']);
}

if ($handler->handle($matchingProject, $data, $githubService)) {
    $logger->log($matchingProject['user_id'], 'github', $payload, $headersStr, 200);
    http_response_code(200);
    echo 'OK';
} else {
    $logger->log($matchingProject['user_id'], 'github', $payload, $headersStr, 500, 'Failed to process event');
    http_response_code(500);
    echo 'Failed to process event';
}
