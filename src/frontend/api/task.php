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

$db = new Database();
$taskModel = new Task($db);
$projectModel = new App\Project($db);

$taskId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $action = $input['action'] ?? $_GET['action'] ?? '';

    $task = $taskModel->findById($taskId);
    if (!$task) {
        http_response_code(404);
        echo json_encode(['error' => 'Task not found']);
        exit;
    }

    $project = $projectModel->findById($task['project_id']);
    if (!$project || (int)$project['user_id'] !== $userId) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    $userModel = new App\User($db);
    $user = $userModel->findById($userId);

    switch ($action) {
        case 'trigger_agent':
            try {
                $julesService = new App\JulesService(null, $user['jules_api_key'] ?? null);
                $logger = new App\Logger($db);
                $notificationService = new App\NotificationService($db);

                $logger->log($userId, $taskId, "Agent triggered via API by user " . $user['name']);

                $githubToken = $project['github_token'] ?? null;
                $githubService = $githubToken ? new App\GitHubService(null, $githubToken) : null;

                $taskModel->updateStatus($taskId, App\Task::STATUS_EXECUTING);

                if ($githubService) {
                    $githubService->postComment($project['github_repo'], $task['issue_number'], "🤖 Agent has started processing this issue via API...");
                }

                $notificationService->notify($userId, 'agent_event', "🤖 Agent Started: #" . $task['issue_number'], "Agent started processing \"" . $task['title'] . "\" via API", [
                    'task_id' => $taskId,
                    'project_id' => $project['project_id'],
                    'source_url' => $taskModel->getTargetUrl($task),
                    'is_system' => false
                ]);

                $responseBody = $julesService->triggerAgent($task);
                $taskModel->updateAgentResponse($taskId, $responseBody, App\Task::STATUS_ANALYZING);

                if ($githubService) {
                    $githubService->postComment($project['github_repo'], $task['issue_number'], "✅ Agent has completed the analysis via API:\n\n" . $responseBody);
                }

                $notificationService->notify($userId, 'agent_event', "✅ Agent Completed: #" . $task['issue_number'], "Agent completed analysis for \"" . $task['title'] . "\" via API", [
                    'task_id' => $taskId,
                    'project_id' => $project['project_id'],
                    'source_url' => $taskModel->getTargetUrl($task),
                    'is_system' => true
                ]);

                echo json_encode(['status' => 'success', 'message' => 'Agent triggered successfully', 'agent_response' => $responseBody]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to trigger agent: ' . $e->getMessage()]);
            }
            break;

        case 'merge_close':
        case 'merge_close_duplicate':
            try {
                $githubToken = $project['github_token'] ?? null;
                if (!$githubToken) {
                    throw new Exception("GitHub token not found for this project.");
                }

                $githubService = new App\GitHubService(null, $githubToken);
                $prNumber = $githubService->extractPrNumber($task['pr_url'] ?? '');

                if (!$prNumber) {
                    throw new Exception("No pull request associated with this task.");
                }

                if ($action === 'merge_close_duplicate') {
                    $githubService->addLabel($project['github_repo'], $task['issue_number'], 'autorepeat');
                }

                $githubService->mergePullRequest($project['github_repo'], $prNumber, "Merged via Agent Control API: " . $task['title']);
                $githubService->closeIssue($project['github_repo'], $task['issue_number'], 'completed');
                $taskModel->markAsMerged($taskId);

                $msg = $action === 'merge_close_duplicate' ? 'PR merged, issue closed and duplicated' : 'PR merged and issue closed';
                echo json_encode(['status' => 'success', 'message' => $msg]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to perform ' . $action . ': ' . $e->getMessage()]);
            }
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid or missing action']);
            break;
    }
    exit;
}

if (!$taskId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing task ID']);
    exit;
}

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

$githubData = json_decode($task['github_data'] ?? '{}', true);
$labels = array_map(function($label) {
    return [
        'name' => $label['name'] ?? '',
        'color' => $label['color'] ?? ''
    ];
}, $githubData['labels'] ?? []);

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
    'last_synced_at' => $task['last_synced_at'],
    'labels' => $labels
]);
