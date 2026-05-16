<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;
use App\User;
use App\TelegramService;
use App\TelegramWebhookHandler;
use App\WebhookLogger;

$db = new Database();
$userModel = new User($db);
$logger = new WebhookLogger($db);

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$headers = getallheaders();
$headersStr = json_encode($headers);
$input = file_get_contents('php://input');
$botToken = getenv('TELEGRAM_BOT_TOKEN') ?: '';
$webhookSecret = getenv('TELEGRAM_WEBHOOK_SECRET') ?: '';

if ($userId) {
    $user = $userModel->findById($userId);
    if ($user) {
        if (!empty($user['telegram_bot_token'])) {
            $botToken = $user['telegram_bot_token'];
        }
        if (!empty($user['telegram_webhook_secret'])) {
            $webhookSecret = $user['telegram_webhook_secret'];
        }
    }
}

$telegramService = new TelegramService(null, $botToken);
$handler = new TelegramWebhookHandler(
    $userModel,
    $telegramService,
    $webhookSecret
);

$providedSecret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';

if (!$handler->verifySecret($providedSecret)) {
    if ($userId) {
        $logger->log($userId, 'telegram', $input, $headersStr, 401, "Unauthorized");
    }
    http_response_code(401);
    echo "Unauthorized";
    exit;
}

$update = json_decode($input, true);

if (!$update) {
    if ($userId) {
        $logger->log($userId, 'telegram', $input, $headersStr, 400, "Invalid Request");
    }
    http_response_code(400);
    echo "Invalid Request";
    exit;
}

if ($userId) {
    $logger->log($userId, 'telegram', $input, $headersStr, 200);
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
