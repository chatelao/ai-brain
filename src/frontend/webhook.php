<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;
use App\Project;
use App\WebhookHandler;
use App\GitHubService;
use App\JulesService;
use App\RateLimiter;
use App\WebhookLogger;
use App\NotificationService;
use App\User;

$db = new Database();
$rateLimiter = new RateLimiter($db);
$ip = $rateLimiter->getIpAddress();
$logger = new WebhookLogger($db);

$headers = getallheaders();
$headersStr = json_encode($headers);
$payload = file_get_contents('php://input');

// Robust header retrieval
$githubEvent = $headers['X-GitHub-Event'] ?? $_SERVER['HTTP_X_GITHUB_EVENT'] ?? '';
$githubSignature = $headers['X-Hub-Signature-256'] ?? $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

if (!$rateLimiter->check("webhook_$ip", 100, 60)) {
    $logger->log(0, 'github', $payload, $headersStr, 429, 'Rate limit exceeded');
    http_response_code(429);
    exit('Too many webhook requests. Please try again later.');
}

$projectModel = new Project($db);
$handler = new WebhookHandler($db);
$notificationService = new NotificationService($db);

if (empty($payload)) {
    $logger->log(0, 'github', $payload, $headersStr, 400, 'Empty payload');
    http_response_code(400);
    exit('Empty payload');
}

if (empty($githubSignature)) {
    $logger->log(0, 'github', $payload, $headersStr, 400, 'Missing signature');
    http_response_code(400);
    exit('Missing signature');
}

if (empty($githubEvent)) {
    $logger->log(0, 'github', $payload, $headersStr, 400, 'Missing event header');
    http_response_code(400);
    exit('Missing event header');
}

// Return 200 for unhandled events to keep webhook healthy on GitHub
if (!in_array($githubEvent, ['issues', 'ping', 'check_suite', 'pull_request'])) {
    http_response_code(200);
    exit("Event $githubEvent skipped");
}

try {
    $data = json_decode($payload, true);
    $repoFullName = $data['repository']['full_name'] ?? '';

    if (empty($repoFullName)) {
        $logger->log(0, 'github', $payload, $headersStr, 400, 'Repository information missing');
        http_response_code(400);
        exit('Repository information missing');
    }

    $projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

    if ($projectId > 0) {
        $project = $projectModel->findById($projectId);
        if (!$project || $project['github_repo'] !== $repoFullName) {
            $logger->log(0, 'github', $payload, $headersStr, 404, 'Project not found or repository mismatch');
            http_response_code(404);
            exit('Project not found or repository mismatch');
        }
        $projects = [$project];
    } else {
        $projects = $projectModel->findByRepo($repoFullName);
    }

    if (empty($projects)) {
        $logger->log(0, 'github', $payload, $headersStr, 404, 'Project not found');
        http_response_code(404);
        exit('Project not found');
    }

    $verified = false;
    $matchingProject = null;

    foreach ($projects as $project) {
        if ($handler->verifySignature($payload, $githubSignature, $project['webhook_secret'])) {
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

    if ($githubEvent === 'ping') {
        if (!empty($projects)) {
            $logger->log($projects[0]['user_id'], 'github', $payload, $headersStr, 200, 'PONG');
        }
        http_response_code(200);
        exit('PONG');
    }

    // Optimization: Return 200 OK immediately and continue in background if possible
    if (function_exists('fastcgi_finish_request')) {
        ignore_user_abort(true);
        session_write_close();
        header("Content-Length: 2");
        header("Connection: close");
        echo "OK";
        fastcgi_finish_request();
    }


    $githubService = null;
    if (!empty($matchingProject['github_token'])) {
        $githubService = new GitHubService(null, $matchingProject['github_token']);
    }

    $userModel = new User($db);
    $user = $userModel->findById($matchingProject['user_id']);
    $julesService = new JulesService(null, $user['jules_api_key'] ?? null);

    if ($handler->handle($matchingProject, $data, $githubService, $notificationService, $julesService)) {
        $logger->log($matchingProject['user_id'], 'github', $payload, $headersStr, 200);
        http_response_code(200);
        echo 'OK';
    } else {
        $logger->log($matchingProject['user_id'], 'github', $payload, $headersStr, 500, 'Failed to process event');
        http_response_code(500);
        echo 'Failed to process event';
    }
} catch (\Throwable $e) {
    $userId = $matchingProject ? $matchingProject['user_id'] : 0;
    $logger->log($userId, 'github', $payload, $headersStr, 500, 'Exception: ' . $e->getMessage());
    error_log("Webhook Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo 'Internal Server Error';
}
