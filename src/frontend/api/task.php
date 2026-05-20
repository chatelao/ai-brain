<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Database;
use App\Auth;
use App\Task;
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

$taskId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$taskId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing task ID']);
    exit;
}

$db = new Database();
$taskModel = new Task($db);
$projectModel = new Project($db);

$task = $taskModel->findById($taskId);
if (!$task) {
    http_response_code(404);
    echo json_encode(['error' => 'Task not found']);
    exit;
}

// Verify project ownership
$project = $projectModel->findById($task['project_id']);
if (!$project || (int)$project['user_id'] !== $userId) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

// Map internal database fields to OpenAPI schema
echo json_encode([
    'id' => (int)$task['task_id'],
    'project_id' => (int)$task['project_id'],
    'issue_number' => (int)$task['issue_number'],
    'title' => $task['title'],
    'body' => $task['body'],
    'status' => $task['status'],
    'agent_response' => $task['agent_response'],
    'pr_url' => $task['pr_url'],
    'jules_url' => $task['jules_url'],
    'jules_status' => $task['jules_status'],
    'github_state' => $task['github_state'],
    'created_at' => $task['created_at'],
    'last_synced_at' => $task['last_synced_at']
]);
