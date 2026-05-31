<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Database;
use App\Auth;
use App\User;
use App\DbCheckService;

header('Content-Type: application/json');

$db = new Database();
$auth = new Auth($db);
$userId = $auth->getAuthenticatedUserId();

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
