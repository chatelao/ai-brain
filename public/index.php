<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;
use App\Auth;
use App\User;

$auth = new Auth();
$db = new Database();
$userModel = new User($db);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Control Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal">
    <nav class="bg-white border-b border-gray-200 fixed w-full z-30 top-0">
        <div class="px-3 py-3 lg:px-5 lg:pl-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center justify-start">
                    <span class="self-center text-xl font-semibold sm:text-2xl whitespace-nowrap">Agent Control</span>
                </div>
                <div class="flex items-center">
                    <?php
                    $user = $auth->isLoggedIn() ? $userModel->findById($auth->getUserId()) : null;
                    if ($user): ?>
                        <div class="flex items-center ml-3">
                            <div>
                                <button type="button" class="flex text-sm bg-gray-800 rounded-full focus:ring-4 focus:ring-gray-300" id="user-menu-button-2" aria-expanded="false" data-dropdown-toggle="dropdown-2">
                                    <span class="sr-only">Open user menu</span>
                                    <img class="w-8 h-8 rounded-full" src="<?= htmlspecialchars($user['avatar'] ?? 'https://www.gravatar.com/avatar/?d=mp') ?>" alt="user photo">
                                </button>
                            </div>
                            <div class="ml-3 text-sm font-medium text-gray-900"><?= htmlspecialchars($user['name']) ?></div>
                            <a href="logout.php" class="ml-4 text-sm font-medium text-red-600 hover:underline">Logout</a>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 mr-2 mb-2 focus:outline-none">Login with Google</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex pt-16 overflow-hidden bg-gray-50">
        <div id="main-content" class="relative w-full h-full overflow-y-auto bg-gray-50">
            <main>
                <div class="px-4 pt-6">
                    <div class="grid w-full grid-cols-1 gap-4 mt-4">
                        <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm sm:p-6">
                            <h3 class="text-base font-normal text-gray-500">Welcome to Agent Control</h3>
                            <?php if ($user): ?>
                                <p class="text-2xl font-bold leading-none text-gray-900 sm:text-3xl">Dashboard</p>
                                <div class="mt-4">
                                    <p class="text-gray-600">You are logged in as <strong><?= htmlspecialchars($user['email']) ?></strong>.</p>
                                    <p class="mt-2 text-gray-500">Next step: Link your GitHub repositories.</p>
                                </div>
                            <?php else: ?>
                                <p class="text-2xl font-bold leading-none text-gray-900 sm:text-3xl">Please Login</p>
                                <div class="mt-4">
                                    <p class="text-gray-600">Manage your Google Jules agents and GitHub issues seamlessly.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
