<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Database;
use App\Auth;
use App\User;
use App\DbCheckService;

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
$dbCheckService = new DbCheckService($db);

$connectionStatus = $dbCheckService->checkConnection();
$missingPatches = $dbCheckService->getMissingPatches();
$tableStatus = $dbCheckService->validateTables();
$basicDataStatus = $dbCheckService->validateBasicData();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Check - Agent Control</title>
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
                                        <span class="ml-1 text-sm font-medium text-gray-400 md:ml-2">Database Check</span>
                                    </div>
                                </li>
                            </ol>
                        </nav>
                        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Database Health Check</h1>
                        <p class="text-sm text-gray-500">Comprehensive validation of database schema, patches, and basic data.</p>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <!-- Connection Status -->
                        <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                Connection Status
                            </h3>
                            <?php if ($connectionStatus['status'] === 'OK') : ?>
                                <div class="flex items-center p-4 text-green-800 border-t-4 border-green-300 bg-green-50" role="alert">
                                    <svg class="flex-shrink-0 w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                    <div class="ml-3 text-sm font-medium">
                                        Connected to <?= htmlspecialchars($connectionStatus['driver']) ?> (v<?= htmlspecialchars($connectionStatus['version']) ?>)
                                    </div>
                                </div>
                            <?php else : ?>
                                <div class="flex items-center p-4 text-red-800 border-t-4 border-red-300 bg-red-50" role="alert">
                                    <svg class="flex-shrink-0 w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>
                                    <div class="ml-3 text-sm font-medium">
                                        Connection Error: <?= htmlspecialchars($connectionStatus['message']) ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Missing Patches -->
                        <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                Migration Status
                            </h3>
                            <?php if (empty($missingPatches)) : ?>
                                <div class="flex items-center p-4 text-green-800 border-t-4 border-green-300 bg-green-50" role="alert">
                                    <svg class="flex-shrink-0 w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                    <div class="ml-3 text-sm font-medium">Database is up to date.</div>
                                </div>
                            <?php else : ?>
                                <div class="p-4 text-orange-800 border-t-4 border-orange-300 bg-orange-50" role="alert">
                                    <div class="flex items-center mb-2">
                                        <svg class="flex-shrink-0 w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                        <div class="text-sm font-bold"><?= count($missingPatches) ?> Patches Pending</div>
                                    </div>
                                    <ul class="text-xs list-disc list-inside mb-4">
                                        <?php foreach ($missingPatches as $patch) : ?>
                                            <li><?= htmlspecialchars($patch) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <a href="upgrade.php" class="text-white bg-orange-700 hover:bg-orange-800 focus:ring-4 focus:ring-orange-300 font-medium rounded-lg text-xs px-3 py-1.5 inline-flex items-center">
                                        Go to Upgrade Page
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Basic Data Validation -->
                        <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm lg:col-span-2">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                Basic Data Availability
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php foreach ($basicDataStatus as $check) : ?>
                                    <div class="p-3 border rounded-lg <?= $check['status'] === 'OK' ? 'bg-green-50 border-green-200' : ($check['status'] === 'WARNING' ? 'bg-yellow-50 border-yellow-200' : 'bg-red-50 border-red-200') ?>">
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-sm font-semibold"><?= htmlspecialchars($check['name']) ?></span>
                                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-full uppercase <?= $check['status'] === 'OK' ? 'bg-green-100 text-green-800' : ($check['status'] === 'WARNING' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                                                <?= $check['status'] ?>
                                            </span>
                                        </div>
                                        <p class="text-xs text-gray-600"><?= htmlspecialchars($check['message']) ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Table Status -->
                        <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm lg:col-span-2">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                Table Validation
                            </h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Table Name</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Row Count</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($tableStatus as $table) : ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($table['table']) ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                    <?php if ($table['exists']) : ?>
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Exists</span>
                                                    <?php else : ?>
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Missing</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $table['exists'] ? (int)$table['rows'] : '-' ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php if ($table['error']) : ?>
                                                        <span class="text-red-600 font-mono text-[10px]"><?= htmlspecialchars($table['error']) ?></span>
                                                    <?php else : ?>
                                                        <span class="text-green-600">No issues</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
