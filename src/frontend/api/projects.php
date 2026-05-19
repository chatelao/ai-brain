<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Database;
use App\Auth;
use App\Project;

header('Content-Type: application/json');

$auth = new Auth();
$userId = null;

// Support both Session and JWT authentication
if ($auth->isLoggedIn()) {
    $userId = $auth->getUserId();
} else {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $userId = $auth->validateToken($matches[1]);
    }
}

if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = new Database();
$projectModel = new Project($db);

$projects = $projectModel->findByUserId($userId);

// Map internal database fields to OpenAPI schema if necessary
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
