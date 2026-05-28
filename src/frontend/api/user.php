<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Database;
use App\Auth;
use App\User;
use App\NotificationService;
use App\TelegramService;

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

$userModel = new User($db);
$notificationService = new NotificationService($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (isset($input['jules_api_key'])) {
        $userModel->updateJulesApiKey($userId, trim($input['jules_api_key']));
    }

    if (isset($input['blockly_config'])) {
        $userModel->updateBlocklyConfig($userId, $input['blockly_config']);
    }

    if (isset($input['automations_enabled'])) {
        $userModel->updateAutomationsEnabled($userId, (bool)$input['automations_enabled']);
    }

    if (isset($input['telegram_bot_name']) || isset($input['telegram_bot_token']) || isset($input['telegram_webhook_secret'])) {
        $user = $userModel->findById($userId);
        $newBotToken = $input['telegram_bot_token'] ?? $user['telegram_bot_token'] ?? '';
        $newWebhookSecret = $input['telegram_webhook_secret'] ?? $user['telegram_webhook_secret'] ?? '';
        $newBotName = $input['telegram_bot_name'] ?? $user['telegram_bot_name'] ?? '';

        $oldBotToken = $user['telegram_bot_token'] ?? '';

        if (!empty($oldBotToken) && $oldBotToken !== $newBotToken) {
            try {
                $oldTelegramService = new TelegramService(null, $oldBotToken);
                $oldTelegramService->deleteWebhook();
            } catch (\Exception $e) {}
        }

        if ($userModel->updateTelegramConfig($userId, $newBotToken, $newWebhookSecret, $newBotName)) {
            if (!empty($newBotToken)) {
                try {
                    $telegramService = new TelegramService(null, $newBotToken);
                    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
                    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
                        $protocol = "https://";
                    }
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $webhookUrl = $protocol . $host . "/telegram/webhook.php?user_id=" . $userId;
                    $telegramService->setWebhook($webhookUrl, $newWebhookSecret);
                } catch (\Exception $e) {}
            }
        }
    }

    if (isset($input['notification_settings'])) {
        $notificationService->updateUserSettings($userId, $input['notification_settings']);
    }

    if (isset($input['notification_event_settings'])) {
        $notificationService->updateUserEventSettings($userId, $input['notification_event_settings']);
    }
}

$user = $userModel->findById($userId);

if (!$user) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit;
}

$githubAccounts = $userModel->getGitHubAccounts($userId);
$notifSettings = $notificationService->getUserSettings($userId);
$eventSettings = $notificationService->getUserEventSettings($userId);

// Map internal database fields to OpenAPI schema
$output = [
    'id' => (int)$user['user_id'],
    'google_id' => $user['google_id'] ?? null,
    'name' => $user['name'],
    'email' => $user['email'],
    'avatar' => $user['avatar'],
    'role' => $user['role'],
    'created_at' => $user['created_at'],
    'telegram_bot_name' => $user['telegram_bot_name'] ?? null,
    'telegram_bot_token' => !empty($user['telegram_bot_token']) ? '********' : null,
    'telegram_webhook_secret' => !empty($user['telegram_webhook_secret']) ? '********' : null,
    'telegram_chat_id' => $userModel->getTelegramChatId($userId),
    'has_jules_key' => !empty($user['jules_api_key']),
    'automations_enabled' => (bool)($user['automations_enabled'] ?? true),
    'jules_quota_usage' => isset($user['jules_quota_usage']) ? (int)$user['jules_quota_usage'] : null,
    'jules_quota_limit' => isset($user['jules_quota_limit']) ? (int)$user['jules_quota_limit'] : null,
    'blockly_config' => !empty($user['blockly_config']) ? json_decode($user['blockly_config'], true) : null,
    'github_accounts' => array_map(function($acc) {
        return [
            'github_account_id' => (int)$acc['github_account_id'],
            'github_username' => $acc['github_username']
        ];
    }, $githubAccounts),
    'notification_settings' => [
        'in_app' => (bool)($notifSettings['in_app'] ?? true),
        'browser' => (bool)($notifSettings['browser'] ?? false),
        'telegram' => (bool)($notifSettings['telegram'] ?? false),
    ],
    'notification_event_settings' => [
        'created' => (bool)($eventSettings[\App\Task::UNIFIED_CREATED] ?? true),
        'processing' => (bool)($eventSettings[\App\Task::UNIFIED_PROCESSING] ?? true),
        'ready' => (bool)($eventSettings[\App\Task::UNIFIED_READY] ?? true),
        'finished' => (bool)($eventSettings[\App\Task::UNIFIED_FINISHED] ?? true),
        'failed' => (bool)($eventSettings[\App\Task::UNIFIED_FAILED] ?? true),
        // Granular
        'analyzing' => (bool)($eventSettings[\App\Task::STATUS_ANALYZING] ?? true),
        'planning' => (bool)($eventSettings[\App\Task::STATUS_PLANNING] ?? true),
        'executing' => (bool)($eventSettings[\App\Task::STATUS_EXECUTING] ?? true),
        'verifying' => (bool)($eventSettings[\App\Task::STATUS_VERIFYING] ?? true),
        'implemented' => (bool)($eventSettings[\App\Task::STATUS_IMPLEMENTED] ?? true),
        'checking' => (bool)($eventSettings[\App\Task::STATUS_CHECKING] ?? true),
        'failed_jules' => (bool)($eventSettings[\App\Task::STATUS_FAILED_JULES] ?? true),
        'failed_pr' => (bool)($eventSettings[\App\Task::STATUS_FAILED_PR] ?? true),
    ],
];

echo json_encode($output);
