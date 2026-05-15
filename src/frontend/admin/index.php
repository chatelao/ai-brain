<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Database;
use App\Auth;
use App\User;

$auth = new Auth();
$db = new Database();
$userModel = new User($db);

if (!$auth->isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

if (!$auth->isAdmin()) {
    die("Access denied. Admin privileges required.");
}

$currentUser = $userModel->findById($auth->getUserId());
$githubAccounts = $currentUser ? $userModel->getGitHubAccounts($currentUser['id']) : [];
$telegramChatId = $currentUser ? $userModel->getTelegramChatId($currentUser['id']) : null;
$allUsers = $userModel->getAllUsersWithProjectCount();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Agent Control</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal">
    <nav class="bg-white border-b border-gray-200 fixed w-full z-30 top-0">
        <div class="px-3 py-3 lg:px-5 lg:pl-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center justify-start">
                    <a href="../index.php" class="text-xl font-bold flex items-center lg:ml-2.5">
                        <span class="self-center whitespace-nowrap">Agent Control - Admin</span>
                    </a>
                </div>
                <div class="flex items-center">
                    <div class="flex items-center ml-3">
                        <?php
                        $user = $currentUser;
                        include '../navbar-icons.php';
                        ?>
                        <img class="w-8 h-8 rounded-full" src="<?= htmlspecialchars($currentUser['avatar'] ?? 'https://www.gravatar.com/avatar/?d=mp') ?>" alt="user photo">
                        <div class="ml-3 text-sm font-medium text-gray-900"><?= htmlspecialchars($currentUser['name']) ?> (Admin)</div>
                        <a href="../accounts.php" class="ml-4 text-sm font-medium text-blue-600 hover:underline">Accounts</a>
                        <a href="../templates.php" class="ml-4 text-sm font-medium text-blue-600 hover:underline">Templates</a>
                        <a href="../logout.php" class="ml-4 text-sm font-medium text-red-600 hover:underline">Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex pt-16 overflow-hidden bg-gray-50">
        <div id="main-content" class="relative w-full h-full overflow-y-auto bg-gray-50">
            <main>
                <div class="px-4 pt-6">
                    <div class="mb-4">
                        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">User Management</h1>
                        <p class="text-sm text-gray-500">Manage all users and their projects across the platform.</p>
                    </div>

                    <div class="flex flex-col">
                        <div class="overflow-x-auto">
                            <div class="inline-block min-w-full align-middle">
                                <div class="overflow-hidden shadow">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-100">
                                            <tr>
                                                <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">User</th>
                                                <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Email</th>
                                                <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Role</th>
                                                <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Projects</th>
                                                <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Joined</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($allUsers as $user): ?>
                                                <tr class="hover:bg-gray-50">
                                                    <td class="p-4 flex items-center whitespace-nowrap">
                                                        <img class="w-10 h-10 rounded-full" src="<?= htmlspecialchars($user['avatar'] ?? 'https://www.gravatar.com/avatar/?d=mp') ?>" alt="<?= htmlspecialchars($user['name']) ?> avatar">
                                                        <div class="text-sm font-normal text-gray-500 ml-3">
                                                            <div class="text-base font-semibold text-gray-900"><?= htmlspecialchars($user['name']) ?></div>
                                                            <div class="text-xs font-normal text-gray-500">ID: <?= $user['id'] ?></div>
                                                        </div>
                                                    </td>
                                                    <td class="p-4 text-base font-medium text-gray-900 whitespace-nowrap"><?= htmlspecialchars($user['email']) ?></td>
                                                    <td class="p-4 whitespace-nowrap">
                                                        <span class="px-2 py-1 text-xs font-medium rounded-full <?= $user['role'] === 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-800' ?>">
                                                            <?= htmlspecialchars($user['role']) ?>
                                                        </span>
                                                    </td>
                                                    <td class="p-4 text-base font-medium text-gray-900 whitespace-nowrap"><?= (int)$user['project_count'] ?></td>
                                                    <td class="p-4 text-base font-medium text-gray-900 whitespace-nowrap"><?= date('M j, Y', strtotime($user['created_at'])) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
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
