<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;
use App\Auth;
use App\User;
use App\TelegramService;

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

// Handle Telegram Token Generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_telegram_token'])) {
    if (!$auth->validateCsrfToken($_POST['csrf_token'] ?? null)) {
        die("CSRF token validation failed.");
    }
    try {
        $token = $userModel->generateTelegramLinkToken($user['id']);
        $successMessage = "Token generated: " . $token;
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

$githubAccounts = $userModel->getGitHubAccounts($user['id']);
$telegramChatId = $userModel->getTelegramChatId($user['id']);
$telegramLinkToken = $user['telegram_link_token'] ?? null;

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
                    <div class="flex items-center ml-3">
                        <?php include 'navbar-icons.php'; ?>
                        <img class="w-8 h-8 rounded-full" src="<?= htmlspecialchars($user['avatar'] ?? 'https://www.gravatar.com/avatar/?d=mp') ?>" alt="user photo">
                        <div class="ml-3 text-sm font-medium text-gray-900"><?= htmlspecialchars($user['name']) ?></div>
                        <a href="templates.php" class="ml-4 text-sm font-medium text-blue-600 hover:underline">Templates</a>
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

                    <div class="mb-4">
                        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Account Settings</h1>
                        <p class="text-sm text-gray-500 mt-1">Manage your linked third-party accounts.</p>
                    </div>

                    <?php if ($errorMessage): ?>
                        <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50" role="alert">
                            <span class="font-medium">Error!</span> <?= htmlspecialchars($errorMessage) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($successMessage): ?>
                        <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50" role="alert">
                            <span class="font-medium">Success!</span> <?= htmlspecialchars($successMessage) ?>
                        </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- GitHub Settings -->
                        <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                            <div class="flex items-center mb-4">
                                <svg class="w-6 h-6 mr-2 text-black" fill="currentColor" viewBox="0 0 24 24"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.43.372.823 1.102.823 2.222 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>
                                <h3 class="text-lg font-bold text-gray-900">GitHub Accounts</h3>
                            </div>
                            <div class="space-y-2">
                                <?php if (empty($githubAccounts)): ?>
                                    <p class="text-sm text-gray-500 italic">No GitHub accounts linked yet.</p>
                                <?php else: ?>
                                    <?php foreach ($githubAccounts as $account): ?>
                                        <div class="flex items-center text-green-600">
                                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
                                            Linked as <strong><?= htmlspecialchars($account['github_username']) ?></strong>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <div class="mt-4">
                                    <a href="github-login.php" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-gray-800 rounded-lg hover:bg-gray-900 focus:ring-4 focus:outline-none focus:ring-gray-300">
                                        Link <?= empty($githubAccounts) ? 'GitHub Account' : 'Another GitHub Account' ?>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Telegram Settings -->
                        <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                            <div class="flex items-center mb-4">
                                <svg class="w-6 h-6 mr-2 text-blue-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.161c-.18.717-.962 4.084-1.362 5.441-.168.565-.337.754-.539.772-.444.041-.78-.291-1.21-.572-.674-.442-1.053-.717-1.707-1.148-.756-.499-.266-.773.165-1.22.113-.117 2.073-1.899 2.111-2.06.005-.021.01-.098-.036-.139-.046-.041-.114-.027-.163-.016-.07.016-1.188.754-3.348 2.211-.316.216-.603.322-.86.316-.284-.006-.829-.161-1.235-.292-.497-.161-.892-.247-.857-.521.018-.142.215-.288.591-.439 2.311-1.006 3.853-1.67 4.628-1.991 2.207-.916 2.665-1.075 2.964-1.08.066-.001.213.016.309.094.08.066.103.155.112.216.009.055.02.191.01.309z"/></svg>
                                <h3 class="text-lg font-bold text-gray-900">Telegram Integration</h3>
                            </div>
                            <div class="space-y-4">
                                <?php if ($telegramChatId): ?>
                                    <div class="flex items-center text-green-600">
                                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
                                        Linked with Chat ID: <strong><?= htmlspecialchars($telegramChatId) ?></strong>
                                    </div>
                                <?php else: ?>
                                    <p class="text-sm text-gray-500">Link your Telegram account to receive notifications and manage tasks via our bot.</p>

                                    <?php if ($telegramLinkToken): ?>
                                        <div class="p-3 bg-blue-50 border border-blue-200 rounded text-sm">
                                            <p class="font-semibold text-blue-800 mb-1">How to link:</p>
                                            <p class="text-blue-700">Send the following command to our bot:</p>
                                            <code class="block mt-2 p-2 bg-white rounded border border-blue-200 text-blue-900">/start <?= htmlspecialchars($telegramLinkToken) ?></code>
                                        </div>
                                    <?php endif; ?>

                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?= $auth->getCsrfToken() ?>">
                                        <button type="submit" name="generate_telegram_token" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:ring-4 focus:outline-none focus:ring-blue-300">
                                            <?= $telegramLinkToken ? 'Regenerate Token' : 'Generate Linking Token' ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
