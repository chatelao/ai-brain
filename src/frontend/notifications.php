<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;
use App\Auth;
use App\User;
use App\NotificationService;
use App\Task;

$auth = new Auth();
$db = new Database();
$userModel = new User($db);
$taskModel = new Task($db);
$notificationService = new NotificationService($db);

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$userId = $auth->getUserId();
$user = $userModel->findById($userId);

// Handle Mark All as Read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    if (!$auth->validateCsrfToken($_POST['csrf_token'] ?? null)) {
        die("CSRF token validation failed.");
    }
    $notificationService->markAllAsRead($userId);
    header('Location: notifications.php?success=all_read');
    exit;
}

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$notifications = $notificationService->getNotifications($userId, $limit, $offset);
$totalNotifications = $notificationService->getTotalCount($userId);
$totalPages = ceil($totalNotifications / $limit);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Agent Control</title>
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
                                    <span class="text-gray-400 ml-1 md:ml-2 font-medium">Notifications</span>
                                </div>
                            </li>
                        </ol>
                    </nav>

                    <div class="mb-4 flex justify-between items-center">
                        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">All Notifications</h1>
                        <?php if ($totalNotifications > 0): ?>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $auth->getCsrfToken() ?>">
                            <button type="submit" name="mark_all_read" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 focus:outline-none">Mark all as read</button>
                        </form>
                        <?php endif; ?>
                    </div>

                    <?php if (isset($_GET['success']) && $_GET['success'] === 'all_read'): ?>
                        <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50" role="alert">
                            <span class="font-medium">Success!</span> All notifications marked as read.
                        </div>
                    <?php endif; ?>

                    <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
                        <?php if (empty($notifications)): ?>
                            <div class="p-8 text-center text-gray-500 italic">No notifications found.</div>
                        <?php else: ?>
                            <ul class="divide-y divide-gray-200">
                                <?php foreach ($notifications as $n): ?>
                                    <?php $data = json_decode($n['data'] ?? '{}', true); ?>
                                    <li class="p-4 hover:bg-gray-50 transition-colors <?= !$n['is_read'] ? 'bg-blue-50/50' : '' ?>">
                                        <div class="flex justify-between items-start">
                                            <div class="flex-1">
                                                <div class="flex items-center">
                                                    <h4 class="text-sm font-bold text-gray-900"><?= htmlspecialchars($n['title']) ?></h4>
                                                    <?php if (!$n['is_read']): ?>
                                                        <span class="ml-2 w-2 h-2 bg-blue-600 rounded-full"></span>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="text-sm text-gray-600 mt-1"><?= htmlspecialchars($n['message']) ?></p>
                                                <div class="mt-2 flex items-center space-x-4">
                                                    <span class="text-xs text-gray-400 font-mono"><?= htmlspecialchars($n['created_at']) ?></span>
                                                    <?php if (!empty($data['source_url'])): ?>
                                                        <a href="<?= htmlspecialchars($data['source_url']) ?>" target="_blank" class="text-xs text-blue-600 hover:underline flex items-center">
                                                            View Source
                                                            <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>

                            <?php if ($totalPages > 1): ?>
                                <div class="p-4 border-t border-gray-200 flex items-center justify-between bg-gray-50">
                                    <div class="text-sm text-gray-700">
                                        Showing <span class="font-medium"><?= $offset + 1 ?></span> to <span class="font-medium"><?= min($offset + $limit, $totalNotifications) ?></span> of <span class="font-medium"><?= $totalNotifications ?></span> notifications
                                    </div>
                                    <div class="flex-1 flex justify-end space-x-2">
                                        <?php if ($page > 1): ?>
                                            <a href="?page=<?= $page - 1 ?>" class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-100">Previous</a>
                                        <?php endif; ?>
                                        <?php if ($page < $totalPages): ?>
                                            <a href="?page=<?= $page + 1 ?>" class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-100">Next</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
