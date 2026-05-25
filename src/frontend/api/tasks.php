<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Database;
use App\Auth;
use App\Project;
use App\Task;

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

$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$filter = $_GET['filter'] ?? 'all_open';

$db = new Database();
$projectModel = new Project($db);
$taskModel = new Task($db);

if ($projectId > 0) {
    // Verify project ownership
    $project = $projectModel->findById($projectId);
    if (!$project || (int)$project['user_id'] !== $userId) {
        http_response_code(404);
        echo json_encode(['error' => 'Project not found']);
        exit;
    }
    $tasks = $taskModel->findByProjectId($projectId);
} else {
    // Global filter
    $tasks = $taskModel->findByFilter($userId, $filter);
}

// Map internal database fields to OpenAPI schema
$output = array_map(function($task) {
    $githubData = json_decode($task['github_data'] ?? '{}', true);
    $labels = array_map(function($label) {
        return [
            'name' => $label['name'] ?? '',
            'color' => $label['color'] ?? ''
        ];
    }, $githubData['labels'] ?? []);

    $prDetails = !empty($task['github_pr_data']) ? json_decode($task['github_pr_data'], true) : null;

    return [
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
        'github_repo' => $task['github_repo'] ?? '',
        'created_at' => $task['created_at'],
        'last_synced_at' => $task['last_synced_at'],
        'autorepeat_remaining' => (int)($task['autorepeat_remaining'] ?? 0),
        'labels' => $labels,
        'pr_details' => $prDetails ? [
            'title' => $prDetails['title'] ?? '',
            'state' => $prDetails['state'] ?? '',
            'merged' => $prDetails['merged'] ?? false,
            'mergeable_state' => $prDetails['mergeable_state'] ?? null,
            'draft' => $prDetails['draft'] ?? null
        ] : null,
    ];
}, $tasks);

echo json_encode($output);
