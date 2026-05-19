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
$token = $auth->generateToken($userId);

echo json_encode([
    'access_token' => $token,
    'token_type' => 'Bearer',
    'expires_in' => 86400 // 24 hours as per Auth::generateToken implementation
]);
