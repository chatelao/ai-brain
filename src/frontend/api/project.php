<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Database;
use App\Auth;
use App\Project;
use App\Task;
use App\GitHubService;
use App\IssueTemplate;
use App\NotificationService;

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

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if ($projectModel->delete($projectId, $userId)) {
        echo json_encode(['status' => 'success', 'message' => 'Project deleted']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete project']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $action = $input['action'] ?? $_GET['action'] ?? '';

    switch ($action) {
        case 'update_settings':
            $repo = trim($input['github_repo'] ?? '');
            $accountId = (int)($input['github_account_id'] ?? 0);
            if (empty($repo) || $accountId <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Repository and GitHub Account ID are required']);
                exit;
            }
            try {
                // Fetch GitHub token for validation
                $stmt = $db->getConnection()->prepare(
                    "SELECT github_token FROM user_github_accounts WHERE github_account_id = ? AND user_id = ?"
                );
                $stmt->execute([$accountId, $userId]);
                $account = $stmt->fetch();

                $ghService = null;
                if ($account) {
                    $ghService = new GitHubService(null, $account['github_token']);
                }

                if ($projectModel->update($projectId, $userId, $accountId, $repo, $ghService)) {
                    if (isset($input['blockly_config'])) {
                        $projectModel->updateBlocklyConfig($projectId, $input['blockly_config']);
                    }
                    echo json_encode(['status' => 'success', 'message' => 'Project settings updated']);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to update settings']);
                }
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => $e->getMessage()]);
            }
            break;

        case 'update_notifications':
            $statusSettings = $input['status_settings'] ?? [];
            $notificationService = new NotificationService($db);
            if ($notificationService->updateStatusSettings($projectId, $statusSettings)) {
                echo json_encode(['status' => 'success', 'message' => 'Notification settings updated']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update notification settings']);
            }
            break;

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

        case 'create_from_template':
            $templateId = (int)($input['template_id'] ?? 0);
            $params = $input['params'] ?? [];
            $templateModel = new IssueTemplate($db);
            $template = $templateModel->findById($templateId);
            if ($template && (int)$template['user_id'] === $userId) {
                try {
                    $title = strtr($template['title_template'], $params);
                    $body = strtr($template['body_template'], $params);

                    $githubToken = $project['github_token'] ?? null;
                    if (!$githubToken) {
                        throw new Exception("GitHub token not found for this project.");
                    }

                    $labels = ['Jules']; // Default to Jules label for API created issues

                    $githubService = new GitHubService(null, $githubToken);
                    $githubService->createIssue($project['github_repo'], $title, $body, $labels);

                    echo json_encode(['status' => 'success', 'message' => 'Issue created from template']);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Error creating issue: ' . $e->getMessage()]);
                }
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Template not found']);
            }
            break;

        case 'create_from_roadmap':
            $roadmapName = $input['roadmap_name'] ?? '';
            if (!empty($roadmapName)) {
                try {
                    $githubToken = $project['github_token'] ?? null;
                    if (!$githubToken) {
                        throw new Exception("GitHub token not found for this project.");
                    }

                    $title = "Implement one or more of the next, modest, unsolved, feasible and reasonable steps of \"$roadmapName\"";
                    $body = "If none is available, alternativly break down bigger steps to modest ones without implementing anything, just changing the $roadmapName.";

                    $githubService = new GitHubService(null, $githubToken);
                    $githubService->createIssue($project['github_repo'], $title, $body, ['Jules']);

                    echo json_encode(['status' => 'success', 'message' => 'Issue created from roadmap']);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Error creating issue from roadmap: ' . $e->getMessage()]);
                }
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Missing roadmap name']);
            }
            break;

        case 'create_github_issue':
            $title = trim($input['title'] ?? '');
            $body = trim($input['body'] ?? '');
            $labels = $input['labels'] ?? ['Jules'];
            if (empty($title)) {
                http_response_code(400);
                echo json_encode(['error' => 'Title is required']);
                exit;
            }
            try {
                $githubToken = $project['github_token'] ?? null;
                if (!$githubToken) {
                    throw new Exception("GitHub token not found for this project.");
                }

                $githubService = new GitHubService(null, $githubToken);
                $githubService->createIssue($project['github_repo'], $title, $body, (array)$labels);

                echo json_encode(['status' => 'success', 'message' => 'GitHub issue created']);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Error creating issue: ' . $e->getMessage()]);
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
    'blockly_config' => !empty($project['blockly_config']) ? json_decode($project['blockly_config'], true) : null,
    'roadmap_data' => $project['roadmap_data'] ? json_decode($project['roadmap_data'], true) : null,
    'roadmap_updated_at' => $project['roadmap_updated_at']
]);
