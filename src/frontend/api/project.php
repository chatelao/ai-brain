<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Database;
use App\Auth;
use App\Project;
use App\Task;
use App\GitHubService;

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
$taskModel = new Task($db);

$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$projectId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing project ID']);
    exit;
}

$project = $projectModel->findById($projectId);
if (!$project || (int)$project['user_id'] !== $userId) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied or project not found']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $action = $input['action'] ?? $_GET['action'] ?? '';

    switch ($action) {
        case 'sync_issues':
            try {
                $githubToken = $project['github_token'] ?? null;
                if (!$githubToken) {
                    throw new Exception("GitHub token not found for this project.");
                }

                $githubService = new GitHubService(null, $githubToken);
                $taskModel->syncIssues($userId, $projectId, $project['github_repo'], $githubService);

                // Also refresh roadmap cache during sync
                $roadmapFiles = $githubService->getRoadmapFiles($project['github_repo']);
                $projectModel->updateRoadmapCache($projectId, $roadmapFiles);

                echo json_encode(['status' => 'success', 'message' => 'Issues and roadmap synced successfully']);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to sync: ' . $e->getMessage()]);
            }
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid or missing action']);
            break;
    }
    exit;
}

// Map internal database fields to OpenAPI schema
echo json_encode([
    'id' => (int)$project['project_id'],
    'user_id' => (int)$project['user_id'],
    'github_account_id' => (int)$project['github_account_id'],
    'github_repo' => $project['github_repo'],
    'webhook_secret' => $project['webhook_secret'],
    'created_at' => $project['created_at'],
    'github_username' => $project['github_username'],
    'roadmap_data' => $project['roadmap_data'] ? json_decode($project['roadmap_data'], true) : null,
    'roadmap_updated_at' => $project['roadmap_updated_at']
]);
