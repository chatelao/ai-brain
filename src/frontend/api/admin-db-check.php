<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Database;
use App\Auth;
use App\User;
use App\DbCheckService;

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

$dbCheckService = new DbCheckService($db);

$output = [
    'connection_status' => $dbCheckService->checkConnection(),
    'missing_patches' => $dbCheckService->getMissingPatches(),
    'table_status' => $dbCheckService->validateTables(),
    'basic_data_status' => $dbCheckService->validateBasicData(),
];

echo json_encode($output);
