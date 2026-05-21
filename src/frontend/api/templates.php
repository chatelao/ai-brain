<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Database;
use App\Auth;
use App\IssueTemplate;

header('Content-Type: application/json');

$db = new Database();
$auth = new Auth($db);
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

$templateModel = new IssueTemplate($db);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $templates = $templateModel->findByUserId($userId);
    $output = array_map(function($t) {
        return [
            'id' => (int)$t['issue_template_id'],
            'name' => $t['name'],
            'title_template' => $t['title_template'],
            'body_template' => $t['body_template'],
            'parameter_config' => $t['parameter_config'],
            'created_at' => $t['created_at']
        ];
    }, $templates);
    echo json_encode($output);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON input']);
        exit;
    }

    $id = isset($input['id']) ? (int)$input['id'] : null;
    $name = trim($input['name'] ?? '');
    $title = trim($input['title_template'] ?? '');
    $body = $input['body_template'] ?? null;
    $parameterConfig = isset($input['parameter_config']) ? json_encode($input['parameter_config']) : null;

    if (empty($name) || empty($title)) {
        http_response_code(400);
        echo json_encode(['error' => 'Name and Title Template are required']);
        exit;
    }

    try {
        if ($id) {
            $success = $templateModel->update($id, $userId, $name, $title, $body, $parameterConfig);
        } else {
            $success = $templateModel->create($userId, $name, $title, $body, $parameterConfig);
        }

        if ($success) {
            echo json_encode(['status' => 'success']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save template']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing template ID']);
        exit;
    }

    if ($templateModel->delete($id, $userId)) {
        echo json_encode(['status' => 'success']);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Template not found or access denied']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method Not Allowed']);
