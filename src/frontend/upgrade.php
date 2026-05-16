<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;
use App\MigrationService;
use App\Auth;
use App\User;

$auth = new Auth();
$userModel = new User(new Database());

// Security check: Only allow if ENABLE_UPGRADE_PAGE is true
$enabledByEnv = getenv('ENABLE_UPGRADE_PAGE') === 'true';

// Also allow if the user is a logged-in admin authorized for upgrades
$isAdminAuthorized = false;
if ($auth->isLoggedIn() && $auth->isAdmin()) {
    $currentUser = $userModel->findById($auth->getUserId());
    $allowedUpgradeEmail = getenv('UPGRADE_ALLOWED_EMAIL');
    if (!empty($allowedUpgradeEmail) && $currentUser['email'] === $allowedUpgradeEmail) {
        $isAdminAuthorized = true;
    }
}

if (!$enabledByEnv && !$isAdminAuthorized) {
    die("Access denied. Database upgrade page is currently disabled. To enable it, set the environment variable ENABLE_UPGRADE_PAGE=true in your .htaccess or web server configuration, or log in as an authorized administrator.");
}

$db = new Database();
$migrationService = new MigrationService($db);

$logs = [];
$successMessage = null;
$errorMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migrations'])) {
    if (!$auth->validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errorMessage = "Invalid CSRF token.";
    } else {
        $logs = $migrationService->migrate();
        $successMessage = "Migrations process completed.";
    }
}

$status = $migrationService->getMigrationStatus();
$pendingPatches = $status['pending'];
$appliedPatches = $status['applied'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Upgrade - Agent Control</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl w-full space-y-8 bg-white p-10 rounded-xl shadow-lg">
            <div class="text-center">
                <h1 class="text-3xl font-extrabold text-gray-900 mb-2">Database Upgrade</h1>
                <p class="text-sm text-gray-600">Apply pending SQL patches to your database.</p>
            </div>

            <?php if ($successMessage): ?>
                <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50 border border-green-200" role="alert">
                    <span class="font-medium">Success!</span> <?= htmlspecialchars($successMessage) ?>
                </div>
            <?php endif; ?>

            <?php if ($errorMessage): ?>
                <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50 border border-red-200" role="alert">
                    <span class="font-medium">Error!</span> <?= htmlspecialchars($errorMessage) ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <h2 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                        <span class="w-3 h-3 bg-yellow-400 rounded-full mr-2"></span>
                        Pending Patches (<?= count($pendingPatches) ?>)
                    </h2>
                    <div class="bg-gray-50 rounded-lg p-4 max-h-64 overflow-y-auto border border-gray-200">
                        <?php if (empty($pendingPatches)): ?>
                            <p class="text-sm text-gray-500 italic">No pending patches. Your database is up to date.</p>
                        <?php else: ?>
                            <ul class="space-y-2">
                                <?php foreach ($pendingPatches as $patch): ?>
                                    <li class="text-sm font-mono text-gray-700 bg-white p-2 rounded shadow-sm border border-gray-100"><?= htmlspecialchars($patch) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($pendingPatches)): ?>
                        <form method="POST" class="mt-6">
                            <input type="hidden" name="csrf_token" value="<?= $auth->getCsrfToken() ?>">
                            <button type="submit" name="run_migrations" value="1" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Run Migrations
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <div>
                    <h2 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                        <span class="w-3 h-3 bg-green-500 rounded-full mr-2"></span>
                        Applied Patches (<?= count($appliedPatches) ?>)
                    </h2>
                    <div class="bg-gray-50 rounded-lg p-4 max-h-64 overflow-y-auto border border-gray-200">
                        <?php if (empty($appliedPatches)): ?>
                            <p class="text-sm text-gray-500 italic">No patches applied yet.</p>
                        <?php else: ?>
                            <ul class="space-y-2">
                                <?php foreach (array_reverse($appliedPatches) as $patch): ?>
                                    <li class="text-sm font-mono text-gray-400 bg-white p-2 rounded shadow-sm border border-gray-100"><?= htmlspecialchars($patch) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($logs)): ?>
                <div class="mt-8">
                    <h3 class="text-sm font-bold text-gray-700 mb-2 uppercase tracking-wider">Migration Logs</h3>
                    <div class="bg-gray-900 rounded-lg p-4 border border-gray-700">
                        <pre class="text-xs text-green-400 font-mono whitespace-pre-wrap"><?php
                            foreach ($logs as $log) {
                                echo htmlspecialchars($log) . "\n";
                            }
                        ?></pre>
                    </div>
                </div>
            <?php endif; ?>

            <div class="mt-8 pt-6 border-t border-gray-200 flex justify-between items-center text-xs text-gray-500">
                <p>Status: <span class="<?= empty($pendingPatches) ? 'text-green-600 font-bold' : 'text-yellow-600 font-bold' ?> uppercase"><?= empty($pendingPatches) ? 'Up to date' : 'Updates pending' ?></span></p>
                <a href="index.php" class="text-blue-600 hover:underline">Back to Dashboard</a>
            </div>
        </div>
    </div>
</body>
</html>
