<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Database;
use App\Auth;
use App\User;
use App\Project;
use App\Task;
use App\SandboxService;
use App\GitHubService;
use App\NotificationService;
use App\JulesService;

header('Content-Type: application/json');

$auth = new Auth();
$userId = $auth->getAuthenticatedUserId();

if (!$userId || !$auth->isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$db = new Database();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $type = $_GET['type'] ?? 'logs';

    if ($type === 'logs') {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $stmt = $db->getConnection()->prepare(
            "SELECT tl.*, t.issue_number, p.github_repo, u.email as user_email
             FROM task_logs tl
             JOIN tasks t ON tl.task_id = t.task_id
             JOIN projects p ON t.project_id = p.project_id
             JOIN users u ON tl.user_id = u.user_id
             WHERE tl.message LIKE '%Blockly%'
             ORDER BY tl.created_at DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if ($type === 'summary') {
        $summary = [];

        // Projects
        $stmt = $db->getConnection()->prepare("SELECT project_id, github_repo, blockly_config FROM projects WHERE blockly_config IS NOT NULL");
        $stmt->execute();
        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($projects as $p) {
            $config = json_decode($p['blockly_config'] ?? '', true);
            $js = $config['js'] ?? '';
            $events = extractEvents($js);
            if (!empty($events)) {
                $summary[] = [
                    'source' => 'Project',
                    'id' => $p['project_id'],
                    'name' => $p['github_repo'],
                    'events' => $events
                ];
            }
        }

        // Users (Global)
        $stmt = $db->getConnection()->prepare("SELECT user_id, email, blockly_config FROM users WHERE blockly_config IS NOT NULL");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($users as $u) {
            $config = json_decode($u['blockly_config'] ?? '', true);
            $js = $config['js'] ?? '';
            $events = extractEvents($js);
            if (!empty($events)) {
                $summary[] = [
                    'source' => 'Global',
                    'id' => $u['user_id'],
                    'name' => $u['email'],
                    'events' => $events
                ];
            }
        }

        echo json_encode($summary);
        exit;
    }
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'fire_event') {
        $taskId = (int)($input['task_id'] ?? 0);
        $eventType = $input['event_type'] ?? 'DUMMY_EVENT';

        if (!$taskId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing task_id']);
            exit;
        }

        $taskModel = new Task($db);
        $task = $taskModel->findById($taskId);
        if (!$task) {
            http_response_code(404);
            echo json_encode(['error' => 'Task not found']);
            exit;
        }

        $projectModel = new Project($db);
        $project = $projectModel->findById((int)$task['project_id']);
        if (!$project) {
            http_response_code(404);
            echo json_encode(['error' => 'Project not found']);
            exit;
        }

        $userModel = new User($db);
        $user = $userModel->findById((int)$task['user_id']);

        $githubService = new GitHubService(null, $project['github_token'] ?? '');
        $notificationService = new NotificationService($db);
        $julesService = new JulesService(null, $user['jules_api_key'] ?? '');

        $sandboxService = new SandboxService($db, $githubService, $notificationService, $julesService);

        $eventContext = [
            'type' => $eventType,
            'is_dummy' => true,
            'payload' => $input['payload'] ?? []
        ];

        // We run it as a normal automation sequence (local then global)
        $userId = (int)$task['user_id'];
        $handledEvents = [];

        $results = [];

        if (!empty($project['blockly_config'])) {
            $config = json_decode($project['blockly_config'], true);
            if (!empty($config['js'])) {
                $res = $sandboxService->execute($userId, $taskId, $config['js'], $eventContext, 'Local (Manual)');
                $handledEvents = $res['handledEvents'] ?? [];
                $results['local'] = $res;
            }
        }

        if (!empty($user['blockly_config'])) {
            $config = json_decode($user['blockly_config'], true);
            if (!empty($config['js'])) {
                $res = $sandboxService->execute($userId, $taskId, $config['js'], $eventContext, 'Global (Manual)', false, $handledEvents);
                $results['global'] = $res;
            }
        }

        echo json_encode([
            'success' => true,
            'results' => $results
        ]);
        exit;
    }
}

function extractEvents($js) {
    if (empty($js)) return [];
    // Match onEvent("EVENT_NAME", ...
    preg_match_all('/onEvent\s*\(\s*["\']([^"\']+)["\']/', $js, $matches);
    return array_unique($matches[1] ?? []);
}
