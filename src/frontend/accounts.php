<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;
use App\Auth;
use App\User;

$auth = new Auth();
$db = new Database();
$userModel = new User($db);

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = $userModel->findById($auth->getUserId());
$errorMessage = null;
$successMessage = null;
$telegramToken = null;

// Handle Jules API Key Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_jules_key'])) {
    if (!$auth->validateCsrfToken($_POST['csrf_token'] ?? null)) {
        die("CSRF token validation failed.");
    }
    $apiKey = trim($_POST['jules_api_key']);
    if ($userModel->updateJulesApiKey($user['id'], $apiKey)) {
        $successMessage = "Jules API Key updated successfully.";
        $user = $userModel->findById($user['id']); // Refresh user data
    } else {
        $errorMessage = "Failed to update Jules API Key.";
    }
}

// Handle Telegram Token Generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_telegram_token'])) {
    if (!$auth->validateCsrfToken($_POST['csrf_token'] ?? null)) {
        die("CSRF token validation failed.");
    }
    $telegramToken = $userModel->generateTelegramLinkToken($user['id']);
}

$githubAccounts = $userModel->getGitHubAccounts($user['id']);
$telegramChatId = $userModel->getTelegramChatId($user['id']);

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
                    <?php include 'navbar-icons.php'; ?>
                    <div class="flex items-center ml-3">
                        <img class="w-8 h-8 rounded-full" src="<?= htmlspecialchars($user['avatar'] ?? 'https://www.gravatar.com/avatar/?d=mp') ?>" alt="user photo">
                        <div class="ml-3 text-sm font-medium text-gray-900"><?= htmlspecialchars($user['name']) ?></div>
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
                    <h1 class="text-2xl font-bold text-gray-900 mb-6">Account Settings</h1>

                    <?php if ($successMessage): ?>
                        <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50" role="alert">
                            <?= htmlspecialchars($successMessage) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($errorMessage): ?>
                        <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50" role="alert">
                            <?= htmlspecialchars($errorMessage) ?>
                        </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Google Account -->
                        <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-black" fill="currentColor" viewBox="0 0 24 24"><path d="M12.48 10.92v3.28h7.84c-.24 1.84-.908 3.152-1.928 4.176-1.288 1.288-3.312 2.688-6.88 2.688-5.544 0-10.016-4.504-10.016-10.016s4.472-10.016 10.016-10.016c3.12 0 5.392 1.224 7.064 2.816l2.304-2.304C18.816 1.152 16.032 0 12.48 0 5.864 0 .424 5.44.424 12s5.44 12 12.056 12c3.576 0 6.264-1.176 8.36-3.344 2.16-2.16 2.84-5.216 2.84-7.664 0-.736-.064-1.424-.184-2.08H12.48z"/></svg>
                                Google Account
                            </h2>
                            <p class="text-sm text-gray-600 mb-2">Connected as:</p>
                            <p class="font-medium text-gray-900"><?= htmlspecialchars($user['email']) ?></p>
                            <div class="mt-4 p-2 bg-green-50 text-green-700 text-xs font-medium rounded border border-green-200 inline-block">
                                Active Connection
                            </div>
                        </div>

                        <!-- Jules API Key -->
                        <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4">Jules API Key (Private)</h2>
                            <p class="text-sm text-gray-600 mb-4">Your personal Google AI (Gemini) API key from <a href="https://aistudio.google.com/" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">AI Studio</a>.</p>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= $auth->getCsrfToken() ?>">
                                <div class="flex gap-2">
                                    <input type="password" name="jules_api_key" value="<?= htmlspecialchars($user['jules_api_key'] ?? '') ?>" placeholder="AI Studio API Key" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                    <button type="submit" name="update_jules_key" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 focus:outline-none">Save</button>
                                </div>
                            </form>
                        </div>

                        <!-- GitHub Accounts -->
                        <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-black" fill="currentColor" viewBox="0 0 24 24"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.43.372.823 1.102.823 2.222 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>
                                GitHub Accounts
                            </h2>
                            <div class="space-y-3">
                                <?php if (empty($githubAccounts)): ?>
                                    <p class="text-sm text-gray-500 italic">No GitHub accounts linked yet.</p>
                                <?php else: ?>
                                    <?php foreach ($githubAccounts as $account): ?>
                                        <div class="flex items-center justify-between p-2 bg-gray-50 rounded border border-gray-100">
                                            <span class="text-sm font-medium text-gray-900"><?= htmlspecialchars($account['github_username']) ?></span>
                                            <span class="text-xs text-green-600 font-semibold uppercase">Linked</span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <a href="github-login.php" class="mt-2 inline-flex items-center px-4 py-2 text-sm font-medium text-center text-white bg-gray-800 rounded-lg hover:bg-gray-900 focus:ring-4 focus:outline-none focus:ring-gray-300 w-full justify-center">
                                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 24 24"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.43.372.823 1.102.823 2.222 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>
                                    Link <?= empty($githubAccounts) ? 'GitHub Account' : 'Another GitHub Account' ?>
                                </a>
                            </div>
                        </div>

                        <!-- Telegram Connection -->
                        <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-black" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221l-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.446 1.394c-.14.14-.257.257-.527.257l.214-3.053 5.57-5.032c.242-.214-.053-.332-.375-.118l-6.88 4.33-2.954-.924c-.642-.204-.654-.642.134-.948l11.54-4.448c.534-.194 1.001.124.832.943z"/></svg>
                                Telegram Connection
                            </h2>
                            <?php if ($telegramChatId): ?>
                                <p class="text-sm text-gray-600 mb-2">Status:</p>
                                <div class="p-2 bg-green-50 text-green-700 text-xs font-medium rounded border border-green-200 inline-block">
                                    Connected
                                </div>
                                <p class="text-xs text-gray-500 mt-4 italic">Chat ID: <?= htmlspecialchars($telegramChatId) ?></p>
                            <?php else: ?>
                                <p class="text-sm text-gray-600 mb-4">Link your Telegram account to receive notifications and control agents on the go.</p>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= $auth->getCsrfToken() ?>">
                                    <button type="submit" name="generate_telegram_token" class="w-full text-white bg-blue-600 hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 focus:outline-none">Generate Linking Token</button>
                                </form>

                                <?php if ($telegramToken): ?>
                                    <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                        <p class="text-sm text-blue-800 mb-2 font-semibold">Your Token:</p>
                                        <code class="block p-2 bg-white border border-blue-100 rounded text-center text-lg font-bold tracking-widest mb-4"><?= htmlspecialchars($telegramToken) ?></code>
                                        <p class="text-xs text-blue-700">To link your account, send the following message to your Telegram bot:</p>
                                        <code class="block p-2 mt-1 bg-gray-800 text-white rounded text-xs">/start <?= htmlspecialchars($telegramToken) ?></code>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
