<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Database;
use App\Auth;
use App\Project;
use App\GitHubService;

header('Content-Type: application/json');

$auth = new Auth();
$userId = $auth->getAuthenticatedUserId();

if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = new Database();
$projectModel = new Project($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $repo = trim($input['github_repo'] ?? '');
    $accountId = (int)($input['github_account_id'] ?? 0);

    if (empty($repo) || $accountId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Repository and GitHub Account ID are required']);
        exit;
    }

    try {
        // Fetch GitHub token for the selected account
        $stmt = $db->getConnection()->prepare(
            "SELECT github_token FROM user_github_accounts WHERE github_account_id = ? AND user_id = ?"
        );
        $stmt->execute([$accountId, $userId]);
        $account = $stmt->fetch();

        if (!$account) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid GitHub account']);
            exit;
        }

        $ghService = new GitHubService(null, $account['github_token']);
        $result = $projectModel->create($userId, $accountId, $repo, $ghService);

        // Register Webhook
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            $protocol = "https";
        }
        $host = $_SERVER['HTTP_HOST'];
        $webhookUrl = "$protocol://$host/github/webhook.php?project_id=" . $result['project_id'];

        try {
            $ghService->createWebhook($repo, $webhookUrl, $result['webhook_secret']);
        } catch (Exception $webhookException) {
            // Log warning but proceed
            error_log("Failed to create GitHub webhook for $repo: " . $webhookException->getMessage());
        }

        echo json_encode([
            'status' => 'success',
            'project_id' => $result['project_id'],
            'message' => 'Project created successfully'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

$projects = $projectModel->findByUserId($userId);

// Map internal database fields to OpenAPI schema
$output = array_map(function($project) {
    return [
        'id' => (int)$project['project_id'],
        'user_id' => (int)$project['user_id'],
        'github_account_id' => (int)$project['github_account_id'],
        'github_repo' => $project['github_repo'],
        'webhook_secret' => $project['webhook_secret'],
        'created_at' => $project['created_at'],
        'github_username' => $project['github_username']
    ];
}, $projects);

echo json_encode($output);
