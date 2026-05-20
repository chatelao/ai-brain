<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Database;
use App\Auth;
use App\User;

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
$userModel = new User($db);
$user = $userModel->findById($userId);

if (!$user) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit;
}

echo json_encode([
    'id' => (int)$user['user_id'],
    'google_id' => $user['google_id'],
    'name' => $user['name'],
    'email' => $user['email'],
    'avatar' => $user['avatar'] ?? null,
    'role' => $user['role'],
    'created_at' => $user['created_at']
]);
