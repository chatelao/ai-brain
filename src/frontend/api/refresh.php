<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Auth;

header('Content-Type: application/json');

$auth = new Auth();

$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
$refreshToken = null;

if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    $refreshToken = $matches[1];
}

if (!$refreshToken) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing refresh token']);
    exit;
}

$userId = $auth->validateRefreshToken($refreshToken);

if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid or expired refresh token']);
    exit;
}

// Revoke old refresh token (optional, but recommended for security)
$auth->revokeRefreshToken($refreshToken);

// Generate new tokens
$newAccessToken = $auth->generateToken($userId, 3600);
$newRefreshToken = $auth->generateRefreshToken($userId);

echo json_encode([
    'access_token' => $newAccessToken,
    'refresh_token' => $newRefreshToken,
    'token_type' => 'Bearer',
    'expires_in' => 3600
]);
