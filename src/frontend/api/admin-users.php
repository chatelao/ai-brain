<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Database;
use App\Auth;
use App\User;

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

$userModel = new User($db);
$users = $userModel->getAllUsersWithProjectCount();

$output = array_map(function($user) {
    return [
        'id' => (int)$user['user_id'],
        'google_id' => $user['google_id'] ?? null,
        'github_id' => $user['github_id'] ?? null,
        'name' => $user['name'],
        'email' => $user['email'],
        'avatar' => $user['avatar'] ?? null,
        'role' => $user['role'],
        'created_at' => $user['created_at'],
        'project_count' => (int)$user['project_count']
    ];
}, $users);

echo json_encode($output);
