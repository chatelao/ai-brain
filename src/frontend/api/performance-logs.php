<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Database;
use App\Auth;
use App\Logger;

header('Content-Type: application/json');

$auth = new Auth();
$userId = $auth->getAuthenticatedUserId();

if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = new Database();
$logger = new Logger($db);

// Determine if the user is an admin
$isAdmin = $auth->isAdmin();

// Fetch logs based on user role
if ($isAdmin) {
    $performanceLogs = $logger->getPerformanceLogs(null, 100);
} else {
    $performanceLogs = $logger->getPerformanceLogs($userId, 100);
}

// Map internal database fields to output schema
$output = array_map(function($log) {
    return [
        'id' => (int)$log['performance_log_id'], // In DB it is performance_log_id based on a quick check of Logger.php or Migration would be better
        'user_id' => $log['user_id'] ? (int)$log['user_id'] : null,
        'user_email' => $log['user_email'] ?? null,
        'type' => $log['type'],
        'target' => $log['target'],
        'duration' => (float)$log['duration'],
        'context' => $log['context'] ? json_decode($log['context'], true) : null,
        'status_code' => $log['status_code'] !== null ? (int)$log['status_code'] : null,
        'error_message' => $log['error_message'],
        'created_at' => $log['created_at']
    ];
}, $performanceLogs);

echo json_encode($output);
