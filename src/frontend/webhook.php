<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;
use App\Project;
use App\WebhookHandler;
use App\GitHubService;
use App\RateLimiter;

$db = new Database();
$rateLimiter = new RateLimiter($db);
$ip = $rateLimiter->getIpAddress();

if (!$rateLimiter->check("webhook_$ip", 100, 60)) {
    http_response_code(429);
    exit('Too many webhook requests. Please try again later.');
}
$projectModel = new Project($db);
$handler = new WebhookHandler($db);

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$event = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? '';

if (empty($payload) || empty($signature) || $event !== 'issues') {
    http_response_code(400);
    exit('Invalid request');
}

$data = json_decode($payload, true);
$repoFullName = $data['repository']['full_name'] ?? '';

if (empty($repoFullName)) {
    http_response_code(400);
    exit('Repository information missing');
}

$projects = $projectModel->findByRepo($repoFullName);

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
    http_response_code(401);
    exit('Invalid signature');
}

$githubService = null;
if (!empty($matchingProject['github_token'])) {
    $githubService = new GitHubService(null, $matchingProject['github_token']);
}

if ($handler->handle($matchingProject['id'], $data, $githubService)) {
    http_response_code(200);
    echo 'OK';
} else {
    http_response_code(500);
    echo 'Failed to process event';
}
