<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Database;
use App\Auth;
use App\MigrationService;

header('Content-Type: application/json');

$db = new Database();
$auth = new Auth($db);
$userId = null;

// Support both Session and JWT authentication
if ($auth->isLoggedIn()) {
    $userId = $auth->getUserId();
} else {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['Authorization'] ?? '';
    if (empty($authHeader) && function_exists('getallheaders')) {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $userId = $auth->validateToken($matches[1]);
    }
}

if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!$auth->isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden: Admin privileges required']);
    exit;
}

$migrationService = new MigrationService($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $patch = $input['patch'] ?? 'all';
    $logs = [];

    if ($patch === 'all') {
        $logs = $migrationService->migrate();
    } else {
        $logs = $migrationService->applyPatch($patch);
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Migrations process completed.',
        'logs' => $logs
    ]);
    exit;
}

// GET - return migration status
$status = $migrationService->getMigrationStatus();
echo json_encode($status);
