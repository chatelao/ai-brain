<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Database;
use App\Auth;
use App\WebhookLogger;

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
$webhookLogger = new WebhookLogger($db);

// Determine if the user is an admin
$isAdmin = $auth->isAdmin();

// Fetch logs based on user role
if ($isAdmin) {
    $webhookLogs = $webhookLogger->getAllLogs(100);
} else {
    $webhookLogs = $webhookLogger->getLogsByUser($userId);
}

// Map internal database fields to OpenAPI schema
$output = array_map(function($log) {
    return [
        'id' => (int)$log['log_id'],
        'user_id' => (int)$log['user_id'],
        'user_email' => $log['user_email'] ?? null,
        'endpoint' => $log['endpoint'],
        'payload' => $log['payload'],
        'headers' => $log['headers'],
        'status_code' => (int)$log['status_code'],
        'error_message' => $log['error_message'],
        'created_at' => $log['created_at']
    ];
}, $webhookLogs);

echo json_encode($output);
