<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;
use App\Auth;
use App\User;
use App\Logger;
use App\WebhookLogger;

$auth = new Auth();
$db = new Database();
$userModel = new User($db);
$taskModel = new App\Task($db);
$logger = new Logger($db);
$webhookLogger = new WebhookLogger($db);

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = $userModel->findById($auth->getUserId());

// Determine if the user is the main user (admin)
$allowedUpgradeEmail = getenv('UPGRADE_ALLOWED_EMAIL');
$isAdmin = !empty($allowedUpgradeEmail) && $user['email'] === $allowedUpgradeEmail;

// Fetch logs based on user role
if ($isAdmin) {
    $performanceLogs = $logger->getPerformanceLogs(null, 100);
    $webhookLogs = $webhookLogger->getAllLogs(100);
} else {
    $performanceLogs = $logger->getPerformanceLogs($user['user_id'], 100);
    $webhookLogs = $webhookLogger->getLogsByUser($user['user_id']);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs - Agent Control</title>
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
                        <div class="ml-3 text-sm font-medium text-gray-900"><?= htmlspecialchars($user['name']) ?> <?= $isAdmin ? '(Admin)' : '' ?></div>
                        <a href="templates.php" class="ml-4 text-sm font-medium text-blue-600 hover:underline">Templates</a>
                        <a href="logs.php" class="ml-4 text-sm font-medium text-blue-600 hover:underline">Logs</a>
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
                <div class="px-4 pt-6" x-data="{ activeTab: 'performance' }">
                    <div class="mb-4">
                        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Logs</h1>
                        <p class="text-sm text-gray-500">View performance monitoring and webhook event logs.</p>
                    </div>

                    <!-- Tab Navigation -->
                    <div class="mb-4 border-b border-gray-200">
                        <ul class="flex flex-wrap -mb-px text-sm font-medium text-center">
                            <li class="mr-2">
                                <button @click="activeTab = 'performance'"
                                        :class="activeTab === 'performance' ? 'text-blue-600 border-blue-600' : 'text-gray-500 border-transparent hover:text-gray-600 hover:border-gray-300'"
                                                        class="inline-block p-4 border-b-2 rounded-t-lg">API & Performance Logs</button>
                            </li>
                            <li class="mr-2">
                                <button @click="activeTab = 'webhooks'"
                                        :class="activeTab === 'webhooks' ? 'text-blue-600 border-blue-600' : 'text-gray-500 border-transparent hover:text-gray-600 hover:border-gray-300'"
                                        class="inline-block p-4 border-b-2 rounded-t-lg">Webhook Logs</button>
                            </li>
                        </ul>
                    </div>

                    <!-- Performance Logs -->
                    <div x-show="activeTab === 'performance'">
                        <div class="flex flex-col">
                            <div class="overflow-x-auto">
                                <div class="inline-block min-w-full align-middle">
                                    <div class="overflow-hidden shadow">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-100">
                                                <tr>
                                                    <?php if ($isAdmin) : ?><th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">User</th><?php endif; ?>
                                                    <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Type</th>
                                                    <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Target</th>
                                                    <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Duration</th>
                                                    <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Status</th>
                                                    <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Error</th>
                                                    <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Time</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <?php foreach ($performanceLogs as $log) : ?>
                                                    <tr class="hover:bg-gray-50 <?= (isset($log['status_code']) && $log['status_code'] >= 400) ? 'bg-red-50' : '' ?>">
                                                        <?php if ($isAdmin) : ?>
                                                            <td class="p-4 text-sm font-normal text-gray-900 whitespace-nowrap"><?= htmlspecialchars($log['user_email'] ?? 'System') ?></td>
                                                        <?php endif; ?>
                                                        <td class="p-4 text-sm font-normal text-gray-900 whitespace-nowrap">
                                                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800"><?= htmlspecialchars($log['type']) ?></span>
                                                        </td>
                                                        <td class="p-4 text-sm font-normal text-gray-500 max-w-xs truncate" title="<?= htmlspecialchars($log['target']) ?>"><?= htmlspecialchars($log['target']) ?></td>
                                                        <td class="p-4 text-sm font-semibold whitespace-nowrap <?= $log['duration'] > 1.0 ? 'text-red-600' : 'text-gray-900' ?>"><?= number_format($log['duration'], 3) ?>s</td>
                                                        <td class="p-4 whitespace-nowrap">
                                                            <?php if (isset($log['status_code'])) : ?>
                                                                <span class="px-2 py-1 text-xs font-medium rounded-full <?= $log['status_code'] >= 200 && $log['status_code'] < 300 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                                    <?= $log['status_code'] ?>
                                                                </span>
                                                            <?php else : ?>
                                                                <span class="text-gray-400">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="p-4 text-sm font-normal text-red-500 max-w-xs truncate" title="<?= htmlspecialchars($log['error_message'] ?? '') ?>"><?= htmlspecialchars($log['error_message'] ?? '-') ?></td>
                                                        <td class="p-4 text-sm font-normal text-gray-500 whitespace-nowrap"><?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                <?php if (empty($performanceLogs)) : ?>
                                                    <tr><td colspan="<?= $isAdmin ? 7 : 6 ?>" class="p-4 text-center text-gray-500 italic">No performance logs found.</td></tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Webhook Logs -->
                    <div x-show="activeTab === 'webhooks'" x-cloak>
                        <div class="flex flex-col">
                            <div class="overflow-x-auto">
                                <div class="inline-block min-w-full align-middle">
                                    <div class="overflow-hidden shadow">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-100">
                                                <tr>
                                                    <?php if ($isAdmin) : ?><th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">User</th><?php endif; ?>
                                                    <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Endpoint</th>
                                                    <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Status</th>
                                                    <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Error</th>
                                                    <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Details</th>
                                                    <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Time</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <?php foreach ($webhookLogs as $log) : ?>
                                                    <tr class="hover:bg-gray-50" x-data="{ open: false }">
                                                        <?php if ($isAdmin) : ?>
                                                            <td class="p-4 text-sm font-normal text-gray-900 whitespace-nowrap"><?= htmlspecialchars($log['user_email'] ?? 'Unknown') ?></td>
                                                        <?php endif; ?>
                                                        <td class="p-4 text-sm font-normal text-gray-900 whitespace-nowrap"><?= htmlspecialchars($log['endpoint']) ?></td>
                                                        <td class="p-4 whitespace-nowrap">
                                                            <span class="px-2 py-1 text-xs font-medium rounded-full <?= $log['status_code'] >= 200 && $log['status_code'] < 300 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                                <?= $log['status_code'] ?>
                                                            </span>
                                                        </td>
                                                        <td class="p-4 text-sm font-normal text-red-500 max-w-xs truncate" title="<?= htmlspecialchars($log['error_message'] ?? '') ?>"><?= htmlspecialchars($log['error_message'] ?? '-') ?></td>
                                                        <td class="p-4">
                                                            <button @click="open = !open" class="text-blue-600 hover:underline text-sm">View Payload</button>
                                                            <div x-show="open" class="mt-2 p-2 bg-gray-900 text-green-400 rounded text-xs overflow-auto max-w-lg max-h-64 absolute z-50 shadow-xl" @click.away="open = false" x-cloak>
                                                                <div class="mb-2 border-b border-gray-700 pb-1 text-gray-400">Headers:</div>
                                                                <?php
                                                                $headers = json_decode($log['headers'] ?? '{}', true);
                                                                $headersJson = $headers ? json_encode($headers, JSON_PRETTY_PRINT) : ($log['headers'] ?? '');
                                                                ?>
                                                                <pre><?= htmlspecialchars($headersJson ?? '') ?></pre>
                                                                <div class="mt-4 mb-2 border-b border-gray-700 pb-1 text-gray-400">Payload:</div>
                                                                <?php
                                                                $payload = json_decode($log['payload'] ?? '{}', true);
                                                                $payloadJson = $payload ? json_encode($payload, JSON_PRETTY_PRINT) : ($log['payload'] ?? '');
                                                                ?>
                                                                <pre><?= htmlspecialchars($payloadJson ?? '') ?></pre>
                                                            </div>
                                                        </td>
                                                        <td class="p-4 text-sm font-normal text-gray-500 whitespace-nowrap"><?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                <?php if (empty($webhookLogs)) : ?>
                                                    <tr><td colspan="<?= $isAdmin ? 6 : 5 ?>" class="p-4 text-center text-gray-500 italic">No webhook logs found.</td></tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
