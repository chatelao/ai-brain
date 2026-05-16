<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Database;
use App\Auth;
use App\User;
use App\MigrationService;
use App\Task;

$auth = new Auth();
$db = new Database();
$userModel = new User($db);

// Check for secret bypass
$providedSecret = $_REQUEST['secret'] ?? null;
$configuredSecret = getenv('DB_UPGRADE_SECRET');
$isSecretValid = !empty($configuredSecret) && $providedSecret === $configuredSecret;

if (!$isSecretValid) {
    if (!$auth->isLoggedIn()) {
        header('Location: ../login.php');
        exit;
    }

    if (!$auth->isAdmin()) {
        die("Access denied. Admin privileges required.");
    }

    $currentUser = $userModel->findById($auth->getUserId());
    $user = $currentUser;
    $taskModel = new Task($db);
    $allowedUpgradeEmail = getenv('UPGRADE_ALLOWED_EMAIL');

    // Security check: only a specifically allowed admin can trigger upgrades
    if (empty($allowedUpgradeEmail) || $currentUser['email'] !== $allowedUpgradeEmail) {
        die("Access denied. You are not authorized to trigger system upgrades. Please check UPGRADE_ALLOWED_EMAIL configuration.");
    }
} else {
    // For secret valid case, we still might need $currentUser for the template
    // but the template might fail if we don't have it.
    // However, if it's an automated call, the HTML output might not be strictly necessary
    // but we should try to make it work.
    $currentUser = ['name' => 'System (Secret Auth)', 'email' => 'system', 'avatar' => ''];
}

$migrationService = new MigrationService($db);
$status = $migrationService->getMigrationStatus();
$pendingPatches = $status['pending'];

$logs = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['trigger_upgrade']) || $isSecretValid)) {
    if (!$isSecretValid && !$auth->validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $logs[] = "ERROR: Invalid CSRF token.";
    } else {
        $patch = $_POST['patch'] ?? 'all';
        if ($patch === 'all') {
            $logs = $migrationService->migrate();
        } else {
            $logs = $migrationService->applyPatch($patch);
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Upgrade - Agent Control</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
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
                    <?php include '../navbar-icons.php'; ?>
                    <div class="flex items-center ml-3">
                        <img class="w-8 h-8 rounded-full" src="<?= htmlspecialchars($currentUser['avatar'] ?? 'https://www.gravatar.com/avatar/?d=mp') ?>" alt="user photo">
                        <div class="ml-3 text-sm font-medium text-gray-900"><?= htmlspecialchars($currentUser['name']) ?> (Admin)</div>
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
                        <nav class="flex mb-5" aria-label="Breadcrumb">
                            <ol class="inline-flex items-center space-x-1 md:space-x-2">
                                <li class="inline-flex items-center">
                                    <a href="index.php" class="inline-flex items-center text-gray-700 hover:text-gray-900">
                                        <svg class="w-5 h-5 mr-2.5" fill="currentColor" viewBox="0 0 20 20"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path></svg>
                                        Admin Dashboard
                                    </a>
                                </li>
                                <li>
                                    <div class="flex items-center">
                                        <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>
                                        <span class="ml-1 text-sm font-medium text-gray-400 md:ml-2">System Upgrade</span>
                                    </div>
                                </li>
                            </ol>
                        </nav>
                        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">System Upgrade</h1>
                        <p class="text-sm text-gray-500">Trigger database migrations and patches.</p>
                    </div>

                    <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                        <div class="items-center justify-between block sm:flex">
                            <div class="mb-1 w-full">
                                <h3 class="text-base font-normal text-gray-500">Apply Database Patches</h3>
                                <p class="text-sm text-gray-500 mb-4">This will scan the <code>src/sql/patches/</code> directory and apply any missing SQL patches to the database.</p>

                                <form method="POST" class="space-y-4">
                                    <input type="hidden" name="csrf_token" value="<?= $auth->getCsrfToken() ?>">

                                    <div>
                                        <label for="patch" class="block text-sm font-medium text-gray-700">Select Patch</label>
                                        <select id="patch" name="patch" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md border">
                                            <option value="all">Apply All Pending Patches</option>
                                            <?php foreach ($pendingPatches as $patch): ?>
                                                <option value="<?= htmlspecialchars($patch) ?>"><?= htmlspecialchars($patch) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <button type="submit" name="trigger_upgrade" value="1" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 mr-2 mb-2 focus:outline-none">
                                        Run Migrations
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($logs)): ?>
                        <div class="p-4 mb-4 bg-gray-900 border border-gray-700 rounded-lg shadow-sm overflow-hidden">
                            <h3 class="text-base font-semibold text-gray-100 mb-2">Migration Logs:</h3>
                            <pre class="text-xs text-green-400 font-mono whitespace-pre-wrap"><?php
                                foreach ($logs as $log) {
                                    echo htmlspecialchars($log) . "\n";
                                }
                            ?></pre>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
