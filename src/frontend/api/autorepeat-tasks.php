<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Database;
use App\Auth;
use App\Task;

header('Content-Type: application/json');

$auth = new Auth();
$userId = $auth->getAuthenticatedUserId();

if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = new Database();
$taskModel = new Task($db);

$tasks = $taskModel->getRunningAutorepeatTasks($userId);

// Map internal database fields to OpenAPI schema
$output = array_map(function($task) {
    $githubData = json_decode($task['github_data'] ?? '{}', true);
    $labels = array_map(function($label) {
        return [
            'name' => $label['name'] ?? '',
            'color' => $label['color'] ?? ''
        ];
    }, $githubData['labels'] ?? []);

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
        'github_repo' => $task['github_repo'],
        'created_at' => $task['created_at'],
        'last_synced_at' => $task['last_synced_at'],
        'autorepeat_remaining' => (int)($task['autorepeat_remaining'] ?? 0),
        'labels' => $labels
    ];
}, $tasks);

echo json_encode($output);
