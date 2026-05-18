<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;
use App\Auth;
use App\User;
use App\Project;
use App\Task;

$auth = new Auth();
$db = new Database();
$userModel = new User($db);
$taskModel = new Task($db);

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = $userModel->findById($auth->getUserId());
$filter = $_GET['filter'] ?? 'all_open';

$tasks = $taskModel->findByFilter($user['user_id'], $filter);

$filterLabels = [
    'github_running' => 'GitHub: Running Checks',
    'github_passed' => 'GitHub: Checks Passed',
    'github_failed' => 'GitHub: Checks Failed',
    'jules_running' => 'Jules: Sessions Running',
    'jules_failed' => 'Jules: Sessions Failed',
    'open_issues' => 'All Open Issues'
];

$title = $filterLabels[$filter] ?? 'Tasks';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> - Agent Control</title>
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
                        <div class="ml-3 text-sm font-medium text-gray-900"><?= htmlspecialchars($user['name'] ?? '') ?></div>
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
                                    <span class="text-gray-400 ml-1 md:ml-2 font-medium"><?= htmlspecialchars($title) ?></span>
                                </div>
                            </li>
                        </ol>
                    </nav>

                    <div class="mb-6">
                        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl"><?= htmlspecialchars($title) ?></h1>
                    </div>

                    <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left text-gray-500">
                                <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3">Project</th>
                                        <th scope="col" class="px-6 py-3">Issue</th>
                                        <th scope="col" class="px-6 py-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($tasks)) : ?>
                                        <tr class="bg-white border-b">
                                            <td colspan="3" class="px-6 py-4 text-center">No tasks matching this filter.</td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php foreach ($tasks as $task) : ?>
                                        <tr class="bg-white border-b hover:bg-gray-50 transition-colors">
                                            <td class="px-6 py-4 font-medium text-gray-900">
                                                <a href="project.php?id=<?= $task['project_id'] ?>" class="text-blue-600 hover:underline">
                                                    <?= htmlspecialchars($task['github_repo'] ?? '') ?>
                                                </a>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-base text-gray-900 font-normal">
                                                    <a href="task.php?id=<?= $task['task_id'] ?>" class="hover:underline">
                                                        <?= htmlspecialchars($task['issue_number'] ?? '') ?> - <?= htmlspecialchars($task['title'] ?? '') ?>
                                                    </a>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <?php
                                                $statusColor = $taskModel->getStatusColor($task);
                                                $bgClass = 'bg-gray-100 text-gray-800';
                                                if ($statusColor === 'green') {
                                                    $bgClass = 'bg-green-100 text-green-800';
                                                } elseif ($statusColor === 'yellow') {
                                                    $bgClass = 'bg-yellow-100 text-yellow-800';
                                                } elseif ($statusColor === 'blue') {
                                                    $bgClass = 'bg-blue-100 text-blue-800';
                                                } elseif ($statusColor === 'red') {
                                                    $bgClass = 'bg-red-100 text-red-800';
                                                } elseif ($statusColor === 'purple') {
                                                    $bgClass = 'bg-purple-100 text-purple-800';
                                                }
                                                ?>
                                                <span class="px-2 py-1 text-xs font-medium rounded-full whitespace-nowrap <?= $bgClass ?>">
                                                    <?php
                                                    if ($task['status'] === App\Task::STATUS_FINISHED || $task['status'] === App\Task::STATUS_READY) {
                                                        echo '✅ ';
                                                    } elseif ($task['status'] === App\Task::STATUS_CHECKING) {
                                                        echo '🔍 ';
                                                    } elseif ($task['status'] === App\Task::STATUS_FAILED_JULES) {
                                                        echo '❌ Jules ';
                                                    } elseif ($task['status'] === App\Task::STATUS_FAILED_PR) {
                                                        echo '❌ PR ';
                                                    } elseif (in_array($task['status'], [App\Task::STATUS_ANALYZING, App\Task::STATUS_PLANNING, App\Task::STATUS_EXECUTING, App\Task::STATUS_VERIFYING, App\Task::STATUS_IMPLEMENTED])) {
                                                        echo '🚧 ';
                                                    } else {
                                                        echo '⏳ ';
                                                    }
                                                    ?>
                                                    <?= htmlspecialchars(ucwords(str_replace('_', ' ', $task['status'] ?? ''))) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
