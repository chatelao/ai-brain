<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;
use App\Auth;
use App\User;
use App\Task;
use App\WebhookLogger;
use App\NotificationService;

$auth = new Auth();
$db = new Database();
$userModel = new User($db);
$taskModel = new Task($db);
$webhookLogger = new WebhookLogger($db);
$notificationService = new NotificationService($db);

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = $userModel->findById($auth->getUserId());

// Handle Jules API Key Update
if ($user && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_jules_key'])) {
    if (!$auth->validateCsrfToken($_POST['csrf_token'] ?? null)) {
        die("CSRF token validation failed.");
    }
    $apiKey = trim($_POST['jules_api_key']);
    if ($userModel->updateJulesApiKey($user['user_id'], $apiKey)) {
        header('Location: settings.php?success=key_updated');
        exit;
    } else {
        $errorMessage = "Failed to update Jules API Key.";
    }
}

// Handle Telegram Config Update
if ($user && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_telegram_config'])) {
    if (!$auth->validateCsrfToken($_POST['csrf_token'] ?? null)) {
        die("CSRF token validation failed.");
    }
    $botToken = trim($_POST['telegram_bot_token']);
    $webhookSecret = trim($_POST['telegram_webhook_secret']);
    if ($userModel->updateTelegramConfig($user['user_id'], $botToken, $webhookSecret)) {
        header('Location: settings.php?success=telegram_updated');
        exit;
    } else {
        $errorMessage = "Failed to update Telegram configuration.";
    }
}

// Handle Notification Settings Update
if ($user && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_notifications'])) {
    if (!$auth->validateCsrfToken($_POST['csrf_token'] ?? null)) {
        die("CSRF token validation failed.");
    }
    $settings = [
        'in_app' => isset($_POST['notify_in_app']),
        'telegram' => isset($_POST['notify_telegram'])
    ];
    if ($notificationService->updateUserSettings($user['user_id'], $settings)) {
        header('Location: settings.php?tab=notifications&success=notifications_updated');
        exit;
    } else {
        $errorMessage = "Failed to update notification settings.";
    }
}

$githubAccounts = $user ? $userModel->getGitHubAccounts($user['user_id']) : [];
$webhookLogs = $user ? $webhookLogger->getLogsByUser($user['user_id']) : [];
$errorMessage = $errorMessage ?? null;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings - Agent Control</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal">
    <nav class="bg-white border-b border-gray-200 fixed w-full z-30 top-0">
        <div class="px-3 py-3 lg:px-5 lg:pl-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center justify-start">
                    <a href="index.php" class="text-xl font-bold flex items-center lg:ml-2.5">
                        <span class="self-center whitespace-nowrap">Agent Control</span>
                    </a>
                </div>
                <div class="flex items-center">
                    <?php if ($user): ?>
                        <?php include 'navbar-icons.php'; ?>
                    <?php endif; ?>
                    <div class="flex items-center ml-3">
                        <img class="w-8 h-8 rounded-full" src="<?= htmlspecialchars($user['avatar'] ?? 'https://www.gravatar.com/avatar/?d=mp') ?>" alt="user photo">
                        <div class="ml-3 text-sm font-medium text-gray-900"><?= htmlspecialchars($user['name']) ?></div>
                        <a href="templates.php" class="ml-4 text-sm font-medium text-blue-600 hover:underline">Templates</a>
                        <a href="settings.php" class="ml-4 text-sm font-medium text-blue-600 hover:underline">Settings</a>
                        <a href="logout.php" class="ml-4 text-sm font-medium text-red-600 hover:underline">Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex pt-16 overflow-hidden bg-gray-50">
        <div id="main-content" class="relative w-full h-full overflow-y-auto bg-gray-50">
            <main>
                <div class="px-4 pt-6">
                    <nav class="flex mb-5" aria-label="Breadcrumb">
                        <ol class="inline-flex items-center space-x-1 md:space-x-2">
                            <li class="inline-flex items-center">
                                <a href="index.php" class="text-gray-700 hover:text-gray-900 inline-flex items-center">
                                    <svg class="w-5 h-5 mr-2.5" fill="currentColor" viewBox="0 0 20 20"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path></svg>
                                    Dashboard
                                </a>
                            </li>
                            <li>
                                <div class="flex items-center">
                                    <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>
                                    <span class="text-gray-400 ml-1 md:ml-2 font-medium">Account Settings</span>
                                </div>
                            </li>
                        </ol>
                    </nav>

                    <div class="mb-4 flex justify-between items-center">
                        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Account Settings</h1>
                    </div>

                    <div class="mb-4 border-b border-gray-200" x-data="{ activeTab: '<?= htmlspecialchars($_GET['tab'] ?? 'general', ENT_QUOTES, 'UTF-8') ?>' }">
                        <ul class="flex flex-wrap -mb-px text-sm font-medium text-center">
                            <li class="mr-2">
                                <button @click="activeTab = 'general'" :class="activeTab === 'general' ? 'text-blue-600 border-blue-600' : 'text-gray-500 border-transparent hover:text-gray-600 hover:border-gray-300'" class="inline-block p-4 border-b-2 rounded-t-lg">General</button>
                            </li>
                            <li class="mr-2">
                                <button @click="activeTab = 'notifications'" :class="activeTab === 'notifications' ? 'text-blue-600 border-blue-600' : 'text-gray-500 border-transparent hover:text-gray-600 hover:border-gray-300'" class="inline-block p-4 border-b-2 rounded-t-lg">Notifications</button>
                            </li>
                            <li class="mr-2">
                                <button @click="activeTab = 'logging'" :class="activeTab === 'logging' ? 'text-blue-600 border-blue-600' : 'text-gray-500 border-transparent hover:text-gray-600 hover:border-gray-300'" class="inline-block p-4 border-b-2 rounded-t-lg">Logging</button>
                            </li>
                        </ul>

                        <div x-show="activeTab === 'general'" class="pt-4">
                    <?php if (isset($_GET['success']) && $_GET['success'] === 'key_updated'): ?>
                        <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50" role="alert">
                            <span class="font-medium">Success!</span> Jules API Key updated successfully.
                        </div>
                    <?php endif; ?>
                    <?php if (isset($_GET['success']) && $_GET['success'] === 'telegram_updated'): ?>
                        <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50" role="alert">
                            <span class="font-medium">Success!</span> Telegram configuration updated successfully.
                        </div>
                    <?php endif; ?>

                    <?php if ($errorMessage): ?>
                        <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50" role="alert">
                            <span class="font-medium">Error!</span> <?= htmlspecialchars($errorMessage) ?>
                        </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 gap-4">
                        <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm sm:p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                    <h4 class="text-lg font-semibold text-blue-900 mb-2">Jules API Key (Private)</h4>
                                    <p class="text-sm text-blue-700 mb-4">Your personal Google AI (Gemini) API key. This is required for agent features and is not shared with other users.</p>
                                    <form method="POST" class="flex gap-2">
                                        <input type="hidden" name="csrf_token" value="<?= $auth->getCsrfToken() ?>">
                                        <input type="password" name="jules_api_key" value="<?= htmlspecialchars($user['jules_api_key'] ?? '') ?>" placeholder="AI Studio API Key" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                        <button type="submit" name="update_jules_key" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 focus:outline-none">Save</button>
                                    </form>
                                </div>

                                <div class="p-4 bg-purple-50 border border-purple-200 rounded-lg">
                                    <h4 class="text-lg font-semibold text-purple-900 mb-2">Telegram Custom Bot</h4>
                                    <p class="text-sm text-purple-700 mb-4">Optional: Use your own Telegram Bot. Configure the webhook to:<br>
                                        <code class="bg-white px-1 rounded text-xs font-mono">https://<?= $_SERVER['HTTP_HOST'] ?? 'your-domain.com' ?>/telegram-webhook.php?user_id=<?= $user['user_id'] ?></code>
                                    </p>
                                    <form method="POST" class="space-y-2">
                                        <input type="hidden" name="csrf_token" value="<?= $auth->getCsrfToken() ?>">
                                        <input type="password" name="telegram_bot_token" value="<?= htmlspecialchars($user['telegram_bot_token'] ?? '') ?>" placeholder="Custom Bot Token" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block w-full p-2.5">
                                        <div class="flex gap-2">
                                            <input type="password" name="telegram_webhook_secret" value="<?= htmlspecialchars($user['telegram_webhook_secret'] ?? '') ?>" placeholder="Webhook Secret (X-Telegram-Bot-Api-Secret-Token)" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block w-full p-2.5">
                                            <button type="submit" name="update_telegram_config" class="text-white bg-purple-700 hover:bg-purple-800 focus:ring-4 focus:ring-purple-300 font-medium rounded-lg text-sm px-5 py-2.5 focus:outline-none">Save</button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <div class="mt-6">
                                <h4 class="text-lg font-semibold text-gray-900">Linked GitHub Accounts</h4>
                                <div class="mt-2 space-y-2">
                                    <?php if (empty($githubAccounts)): ?>
                                        <p class="text-sm text-gray-500 italic">No GitHub accounts linked yet.</p>
                                    <?php else: ?>
                                        <?php foreach ($githubAccounts as $account): ?>
                                            <div class="flex items-center text-green-600">
                                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 24 24"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.43.372.823 1.102.823 2.222 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>
                                                Linked as&nbsp;<strong><?= htmlspecialchars($account['github_username']) ?></strong>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    <a href="github-login.php" class="mt-2 inline-flex items-center px-3 py-2 text-sm font-medium text-center text-white bg-gray-800 rounded-lg hover:bg-gray-900 focus:ring-4 focus:outline-none focus:ring-gray-300">
                                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 24 24"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.43.372.823 1.102.823 2.222 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>
                                        Link <?= empty($githubAccounts) ? 'GitHub Account' : 'Another GitHub Account' ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    </div> <!-- End General Tab -->

                    <div x-show="activeTab === 'notifications'" class="pt-4" x-cloak>
                        <?php
                        $notifSettings = $notificationService->getUserSettings($user['user_id']);
                        ?>
                        <?php if (isset($_GET['success']) && $_GET['success'] === 'notifications_updated'): ?>
                            <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50" role="alert">
                                <span class="font-medium">Success!</span> Notification preferences updated.
                            </div>
                        <?php endif; ?>

                        <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm sm:p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Notification Channels</h3>
                            <form method="POST" class="space-y-4">
                                <input type="hidden" name="csrf_token" value="<?= $auth->getCsrfToken() ?>">
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                    <div class="flex items-center">
                                        <div class="p-2 bg-blue-100 rounded-lg mr-4">
                                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-bold text-gray-900">In-App Inbox</h4>
                                            <p class="text-xs text-gray-500">Show notifications in the top navigation bar bell.</p>
                                        </div>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="notify_in_app" class="sr-only peer" <?= ($notifSettings['in_app'] ?? true) ? 'checked' : '' ?>>
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                    </label>
                                </div>

                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                    <div class="flex items-center">
                                        <div class="p-2 bg-purple-100 rounded-lg mr-4">
                                            <svg class="w-6 h-6 text-purple-600" fill="currentColor" viewBox="0 0 24 24"><path d="M20.665 3.717l-17.73 6.837c-1.21.486-1.203 1.161-.222 1.462l4.552 1.42l10.532-6.645c.498-.303.953-.14.579.192l-8.533 7.701l-.333 4.981c.488 0 .704-.224.977-.488l2.347-2.284l4.882 3.606c.899.496 1.542.24 1.766-.83l3.201-15.084c.328-1.315-.502-1.912-1.362-1.523z"/></svg>
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-bold text-gray-900">Telegram</h4>
                                            <p class="text-xs text-gray-500">Send notifications to your linked Telegram account.</p>
                                        </div>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="notify_telegram" class="sr-only peer" <?= ($notifSettings['telegram'] ?? false) ? 'checked' : '' ?>>
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                    </label>
                                </div>

                                <div class="flex justify-end">
                                    <button type="submit" name="update_notifications" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 focus:outline-none">Save Preferences</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div x-show="activeTab === 'logging'" class="pt-4" x-cloak>
                        <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm sm:p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Webhook Call Logs (Last 5 per endpoint)</h3>
                            <?php if (empty($webhookLogs)): ?>
                                <p class="text-sm text-gray-500 italic">No webhook calls logged yet.</p>
                            <?php else: ?>
                                <div class="relative overflow-x-auto">
                                    <table class="w-full text-sm text-left text-gray-500">
                                        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-6 py-3">Time</th>
                                                <th scope="col" class="px-6 py-3">Endpoint</th>
                                                <th scope="col" class="px-6 py-3">Status</th>
                                                <th scope="col" class="px-6 py-3">Details</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($webhookLogs as $log): ?>
                                                <tr class="bg-white border-b hover:bg-gray-50" x-data="{ open: false }">
                                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($log['created_at']) ?></td>
                                                    <td class="px-6 py-4 uppercase font-mono text-xs"><?= htmlspecialchars($log['endpoint']) ?></td>
                                                    <td class="px-6 py-4">
                                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium <?= $log['status_code'] >= 200 && $log['status_code'] < 300 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                            <?= (int)$log['status_code'] ?>
                                                        </span>
                                                        <?php if ($log['error_message']): ?>
                                                            <div class="text-xs text-red-600 mt-1"><?= htmlspecialchars($log['error_message']) ?></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4">
                                                        <button @click="open = !open" class="text-blue-600 hover:underline">View Payload</button>
                                                        <div x-show="open" class="mt-2 p-2 bg-gray-900 text-green-400 rounded text-xs overflow-auto max-w-lg max-h-64" @click.away="open = false" x-cloak>
                                                            <div class="mb-2 border-b border-gray-700 pb-1 text-gray-400">Headers:</div>
                                                            <?php
                                                            $headers = json_decode($log['headers'] ?? '{}', true);
                                                            $headersJson = $headers ? json_encode($headers, JSON_PRETTY_PRINT) : ($log['headers'] ?? '');
                                                            ?>
                                                            <pre><?= htmlspecialchars($headersJson) ?></pre>
                                                            <div class="mt-4 mb-2 border-b border-gray-700 pb-1 text-gray-400">Payload:</div>
                                                            <?php
                                                            $payload = json_decode($log['payload'] ?? '{}', true);
                                                            $payloadJson = $payload ? json_encode($payload, JSON_PRETTY_PRINT) : ($log['payload'] ?? '');
                                                            ?>
                                                            <pre><?= htmlspecialchars($payloadJson) ?></pre>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    </div> <!-- End Tab Content Wrapper -->
                </div>
            </main>
        </div>
    </div>
</body>
</html>
