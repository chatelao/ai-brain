<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Auth;

header('Content-Type: application/json');

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized. Please log in via session first.']);
    exit;
}

$userId = $auth->getUserId();
$accessToken = $auth->generateToken($userId, 3600); // 1 hour
$refreshToken = $auth->generateRefreshToken($userId);

echo json_encode([
    'access_token' => $accessToken,
    'refresh_token' => $refreshToken,
    'token_type' => 'Bearer',
    'expires_in' => 3600
]);
