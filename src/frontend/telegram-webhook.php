<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;
use App\User;
use App\TelegramService;
use App\TelegramWebhookHandler;

$db = new Database();
$userModel = new User($db);
$telegramService = new TelegramService();
$handler = new TelegramWebhookHandler(
    $userModel,
    $telegramService,
    getenv('TELEGRAM_WEBHOOK_SECRET') ?: ''
);

$providedSecret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';

if (!$handler->verifySecret($providedSecret)) {
    http_response_code(401);
    echo "Unauthorized";
    exit;
}

$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (!$update) {
    http_response_code(400);
    echo "Invalid Request";
    exit;
}

// Acknowledge Telegram immediately
if (function_exists('fastcgi_finish_request')) {
    echo "OK";
    session_write_close();
    fastcgi_finish_request();
} else {
    // If not running under FastCGI, we still need to respond eventually,
    // but we can't "finish" early.
    ob_start();
    echo "OK";
    $size = ob_get_length();
    header("Content-Length: $size");
    header("Connection: close");
    ob_end_flush();
    flush();
}

// Continue processing in the background
$handler->handle($update);
