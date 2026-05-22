<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;
use App\Auth;
use App\User;
use App\Project;
use App\Task;
use App\GitHubService;
use App\NotificationService;

$auth = new Auth();
$db = new Database();
$userModel = new User($db);
$projectModel = new Project($db);
$taskModel = new Task($db);
$notificationService = new NotificationService($db);

if (!$auth->isLoggedIn()) {
    header('Location: google/login.php');
    exit;
}

$user = $userModel->findById($auth->getUserId());

// Initialize Markdown parser
if (!class_exists('\Parsedown')) {
    die("Error: Class 'Parsedown' not found. Please run 'composer install' to install dependencies.");
}
$parsedown = new \Parsedown();
$parsedown->setSafeMode(true);

$taskId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$task = $taskModel->findById($taskId);

if (!$task) {
    die("Task not found.");
}

$project = $projectModel->findById($task['project_id']);

if (!$project || $project['user_id'] !== $user['user_id']) {
    die("Access denied.");
}

// Handle Notification Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_task_notifications'])) {
    if (!$auth->validateCsrfToken($_POST['csrf_token'] ?? null)) {
        die("CSRF token validation failed.");
    }
    $isMuted = isset($_POST['is_muted']);
    if ($notificationService->updateTaskSettings($taskId, $isMuted)) {
        $redirectUrl = basename($_SERVER['PHP_SELF']) . "?id=$taskId&success=notifications_updated";
        header("Location: $redirectUrl");
        exit;
    }
}

// Handle Merge & Close
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['merge_close']) || isset($_POST['merge_close_duplicate']))) {
    if (!$auth->validateCsrfToken($_POST['csrf_token'] ?? null)) {
        die("CSRF token validation failed.");
    }

    try {
        $githubToken = $project['github_token'] ?? null;
        if (!$githubToken) {
            throw new Exception("GitHub token not found for this project.");
        }

        $githubService = new \App\GitHubService(null, $githubToken);

        if (isset($_POST['merge_close_duplicate'])) {
            $githubService->addLabel($project['github_repo'], $task['issue_number'], 'autorepeat');
        }

        $prNumber = $githubService->extractPrNumber($task['pr_url'] ?? '');

        if (!$prNumber) {
            throw new Exception("No pull request associated with this task.");
        }

        // 1. Merge the PR
        $githubService->mergePullRequest($project['github_repo'], $prNumber, "Merged via Agent Control: " . $task['title']);

        // 2. Close the issue (pass 'completed' to trigger auto-repeat)
        $githubService->closeIssue($project['github_repo'], $task['issue_number'], 'completed');

        // 3. Mark as merged in database
        $taskModel->markAsMerged($taskId);

        $successAction = isset($_POST['merge_close_duplicate']) ? 'merged_closed_duplicated' : 'merged_closed';
        header("Location: task.php?id=$taskId&success=$successAction");
        exit;
    } catch (Exception $e) {
        $errorMessage = "Error merging/closing: " . $e->getMessage();
    }
}

$githubData = json_decode($task['github_data'] ?? '{}', true);
$labels = $githubData['labels'] ?? [];
$statusColor = $taskModel->getStatusColor($task);
$logs = $taskModel->getLogs($taskId);

$githubToken = $project['github_token'] ?? null;
$githubService = null;
$prDetails = null;
$julesMessages = [];

$taskNotifSettings = $notificationService->getTaskSettings($taskId);

$cacheTimeout = 15 * 60; // 15 minutes
$cacheUpdatedAt = strtotime($task['github_data_updated_at'] ?? '2000-01-01');
$isCacheValid = (time() - $cacheUpdatedAt) < $cacheTimeout;

if ($githubToken) {
    $githubService = new GitHubService(null, $githubToken);

    if ($isCacheValid && !empty($task['github_pr_data'])) {
        $prDetails = json_decode($task['github_pr_data'], true);
    } elseif (!empty($task['pr_url'])) {
        // Fetch PR details if available
        $prNumber = $githubService->extractPrNumber($task['pr_url']);
        if ($prNumber) {
            try {
                $prDetails = $githubService->getPullRequest($project['github_repo'], $prNumber);
                $taskModel->updateGitHubCache($taskId, $prDetails, null);
            } catch (Exception $e) {
                // Ignore PR fetch errors
            }
        }
    }

    if ($isCacheValid && !empty($task['github_comments_data'])) {
        $comments = json_decode($task['github_comments_data'], true);
    } else {
        // Fetch last comments to find Jules messages
        try {
            $comments = $githubService->getIssueComments($project['github_repo'], $task['issue_number']);
            $taskModel->updateGitHubCache($taskId, null, $comments);
        } catch (Exception $e) {
            $comments = [];
        }
    }

    // Filter for Jules comments
    $julesMessages = array_filter($comments, function($comment) {
        $login = strtolower($comment['user']['login'] ?? '');
        return $login === 'jules' || $login === 'google-labs-jules[bot]';
    });
    // Take the last few
    $julesMessages = array_slice(array_reverse($julesMessages), 0, 3);
}

// Status logic for the right sidebar overview
$githubIssueStatus = ucfirst($task['github_state'] ?? 'open');
$githubIssueColor = $githubIssueStatus === 'Closed' ? 'purple' : 'green';

$julesStatus = $task['jules_status'] ?? 'Pending';
$julesDisplayStatus = ucfirst(str_replace(['-', '_'], ' ', $julesStatus));
$julesColor = 'gray';
if (in_array($julesStatus, ['coding', 'testing', 'researching', 'planning', 'in-progress', 'in_progress'])) {
    $julesColor = 'yellow';
} elseif (in_array($julesStatus, ['completed', 'finished'])) {
    $julesColor = 'green';
} elseif (in_array($julesStatus, ['failed', 'error']) || $task['status'] === App\Task::STATUS_FAILED_JULES) {
    $julesColor = 'red';
}

$prStatus = !empty($task['pr_url']) ? 'Open' : 'None';
$prColor = !empty($task['pr_url']) ? 'green' : 'gray';
if ($task['github_state'] === 'closed' && !empty($task['pr_url'])) {
    $prStatus = 'Closed';
    $prColor = 'purple';
} elseif ($task['status'] === App\Task::STATUS_FAILED_PR) {
    $prStatus = 'Failed';
    $prColor = 'red';
} elseif ($task['status'] === App\Task::STATUS_READY && !empty($task['pr_url'])) {
    $prStatus = 'Passed';
}

$prBadgeClasses = "bg-{$prColor}-100 text-{$prColor}-800";
if ($prDetails && ($prDetails['state'] ?? '') === 'open') {
    $mergeState = $prDetails['mergeable_state'] ?? '';
    if ($mergeState === 'clean') {
        $prBadgeClasses = "bg-green-200 text-green-900";
    } elseif ($mergeState === 'behind') {
        $prBadgeClasses = "bg-green-600 text-white";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task #<?= htmlspecialchars($task['issue_number'] ?? '') ?> - Agent Control</title>
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
                                    <a href="project.php?id=<?= $project['project_id'] ?>" class="text-gray-700 hover:text-gray-900 ml-1 md:ml-2 font-medium">
                                        <?= htmlspecialchars($project['github_repo'] ?? '') ?>
                                    </a>
                                </div>
                            </li>
                            <li aria-current="page">
                                <div class="flex items-center">
                                    <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>
                                    <span class="text-gray-400 ml-1 md:ml-2 font-medium">Task #<?= htmlspecialchars($task['issue_number'] ?? '') ?></span>
                                </div>
                            </li>
                        </ol>
                    </nav>

                    <?php if (isset($_GET['success']) && $_GET['success'] === 'notifications_updated') : ?>
                        <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50" role="alert">
                            <span class="font-medium">Success!</span> Notification settings updated.
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['success']) && $_GET['success'] === 'merged_closed') : ?>
                        <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50" role="alert">
                            <span class="font-medium">Success!</span> Pull Request merged and Issue closed.
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['success']) && $_GET['success'] === 'merged_closed_duplicated') : ?>
                        <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50" role="alert">
                            <span class="font-medium">Success!</span> Pull Request merged, Issue closed and duplicated.
                        </div>
                    <?php endif; ?>

                    <?php if (isset($errorMessage)) : ?>
                        <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50" role="alert">
                            <span class="font-medium">Error!</span> <?= htmlspecialchars($errorMessage) ?>
                        </div>
                    <?php endif; ?>

                    <div class="mb-6">
                        <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                            <h1 class="text-3xl font-bold text-gray-900 flex-1 min-w-[300px]">
                                <?= htmlspecialchars($task['title'] ?? '') ?>
                                <span class="text-gray-400 font-normal ml-2">#<?= htmlspecialchars($task['issue_number'] ?? '') ?></span>
                            </h1>
                            <div class="flex items-center space-x-2">
                                <span class="px-4 py-1.5 text-sm font-semibold rounded-full bg-<?= $statusColor ?>-100 text-<?= $statusColor ?>-800 flex items-center shadow-sm">
                                    <?php
                                    if ($task['status'] === App\Task::STATUS_FINISHED || $task['status'] === App\Task::STATUS_READY || $task['status'] === 'completed') {
                                        echo '✅ ';
                                    } elseif (in_array($task['status'], [App\Task::STATUS_EXECUTING, App\Task::STATUS_VERIFYING, App\Task::STATUS_IMPLEMENTED])) {
                                        echo '🚧 ';
                                    } elseif ($task['status'] === App\Task::STATUS_CHECKING) {
                                        echo '🔍 ';
                                    } elseif ($task['status'] === App\Task::STATUS_FAILED_JULES) {
                                        echo '❌ Jules ';
                                    } elseif ($task['status'] === App\Task::STATUS_FAILED_PR) {
                                        echo '❌ PR ';
                                    } elseif (in_array($task['status'], [App\Task::STATUS_ANALYZING, App\Task::STATUS_PLANNING])) {
                                        echo '🔵 ';
                                    } else {
                                        echo '⏳ ';
                                    }
                                    ?>
                                    <?= htmlspecialchars(ucwords(str_replace(['_', '-'], ' ', $task['status'] ?? ''))) ?>
                                </span>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2 mb-6 border-b border-gray-200 pb-6">
                            <?php foreach ($labels as $label) : ?>
                                <span class="px-2.5 py-1 text-xs font-semibold rounded-full border shadow-sm" style="background-color: #<?= $label['color'] ?>20; border-color: #<?= $label['color'] ?>; color: #<?= $label['color'] ?>; filter: brightness(0.8);">
                                    <?= htmlspecialchars($label['name'] ?? '') ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-4">
                        <div class="lg:col-span-2 space-y-6">
                            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                                <div class="bg-gray-50 px-4 py-2 border-b border-gray-200 flex items-center justify-between">
                                    <div class="flex items-center space-x-2">
                                        <img class="w-6 h-6 rounded-full" src="<?= htmlspecialchars($user['avatar'] ?? 'https://www.gravatar.com/avatar/?d=mp') ?>" alt="user photo">
                                        <span class="text-sm font-bold text-gray-700"><?= htmlspecialchars($user['name'] ?? 'User') ?></span>
                                        <span class="text-sm text-gray-500">commented on <?= htmlspecialchars(date('M d, Y', strtotime($task['created_at']))) ?></span>
                                    </div>
                                    <div class="px-2 py-0.5 text-xs font-medium text-gray-500 border border-gray-300 rounded-md">Author</div>
                                </div>
                                <div class="p-6">
                                    <div class="prose max-w-none text-gray-800 markdown-body">
                                        <?= $parsedown->text($taskModel->processGitHubImages($task['body'] ?? '')) ?>
                                    </div>
                                </div>
                            </div>

                            <?php if ($prDetails) : ?>
                                <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                                    <div class="bg-green-50 px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                                        <div class="flex items-center space-x-2">
                                            <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 24 24"><path d="M11 19.25c0 .414-.336.75-.75.75H8.5a.75.75 0 0 1-.75-.75v-1.5c0-.414.336-.75.75-.75h1.75c.414 0 .75.336.75.75v1.5zM11 14.5c0 .414-.336.75-.75.75H8.5a.75.75 0 0 1-.75-.75v-1.5c0-.414.336-.75.75-.75h1.75c.414 0 .75.336.75.75v1.5zM11 9.75c0 .414-.336.75-.75.75H8.5a.75.75 0 0 1-.75-.75v-1.5c0-.414.336-.75.75-.75h1.75c.414 0 .75.336.75.75v1.5zM16 19.25c0 .414-.336.75-.75.75h-1.75a.75.75 0 0 1-.75-.75v-1.5c0-.414.336-.75.75-.75h1.75c.414 0 .75.336.75.75v1.5zM16 14.5c0 .414-.336.75-.75.75h-1.75a.75.75 0 0 1-.75-.75v-1.5c0-.414.336-.75.75-.75h1.75c.414 0 .75.336.75.75v1.5zM16 9.75c0 .414-.336.75-.75.75h-1.75a.75.75 0 0 1-.75-.75v-1.5c0-.414.336-.75.75-.75h1.75c.414 0 .75.336.75.75v1.5zM20.5 2h-17C2.673 2 2 2.673 2 3.5v17c0 .827.673 1.5 1.5 1.5h17c.827 0 1.5-.673 1.5-1.5v-17C22 2.673 21.327 2 20.5 2zM20 18H4V4h16v14z"/></svg>
                                            <span class="text-sm font-bold text-green-900">Associated Pull Request</span>
                                        </div>
                                        <a href="<?= htmlspecialchars($task['pr_url']) ?>" target="_blank" class="text-xs font-medium text-green-700 hover:underline">View on GitHub &rarr;</a>
                                    </div>
                                    <div class="p-6">
                                        <div class="flex items-start justify-between mb-2">
                                            <h4 class="text-lg font-bold text-gray-900 leading-tight">
                                                <?= htmlspecialchars($prDetails['title'] ?? 'PR Details') ?>
                                            </h4>
                                            <span class="px-2.5 py-0.5 rounded-full text-xs font-medium <?= $prBadgeClasses ?> border border-current shadow-sm">
                                                <?= ucfirst($prDetails['state'] ?? 'unknown') ?>
                                            </span>
                                        </div>
                                        <div class="text-sm text-gray-500 mb-4 flex items-center space-x-4">
                                            <span>Merged: <?= ($prDetails['merged'] ?? false) ? 'Yes' : 'No' ?></span>
                                            <span>Base: <code class="bg-gray-100 px-1 rounded"><?= htmlspecialchars($prDetails['base']['ref'] ?? 'main') ?></code></span>
                                            <span>Head: <code class="bg-gray-100 px-1 rounded"><?= htmlspecialchars($prDetails['head']['ref'] ?? 'feature') ?></code></span>
                                        </div>
                                        <?php if (!empty($prDetails['body'])) : ?>
                                            <div class="prose prose-sm max-w-none text-gray-600 bg-gray-50 p-4 rounded-lg border border-gray-100">
                                                <?= $parsedown->text($taskModel->processGitHubImages(mb_substr($prDetails['body'], 0, 300) . (mb_strlen($prDetails['body']) > 300 ? '...' : ''))) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($julesMessages)) : ?>
                                <div class="space-y-4">
                                    <h3 class="text-lg font-bold text-gray-900 px-1 flex items-center">
                                        <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                                        Last Jules Messages
                                    </h3>
                                    <?php foreach ($julesMessages as $msg) : ?>
                                        <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                                            <div class="bg-blue-50 px-4 py-2 border-b border-gray-200 flex items-center justify-between">
                                                <div class="flex items-center space-x-2">
                                                    <div class="w-6 h-6 rounded-full bg-blue-600 flex items-center justify-center text-[10px] text-white font-bold">J</div>
                                                    <span class="text-sm font-bold text-gray-700"><?= htmlspecialchars($msg['user']['login'] ?? 'Jules') ?></span>
                                                    <span class="text-sm text-gray-500">at <?= htmlspecialchars(date('M d, H:i', strtotime($msg['created_at']))) ?></span>
                                                </div>
                                                <a href="<?= htmlspecialchars($msg['html_url']) ?>" target="_blank" class="text-xs text-blue-600 hover:underline">Link</a>
                                            </div>
                                            <div class="p-4">
                                                <div class="prose prose-sm max-w-none text-gray-700 markdown-body">
                                                    <?= $parsedown->text($taskModel->processGitHubImages($msg['body'] ?? '')) ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                                <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                                    <h3 class="text-sm font-bold text-gray-700">Task Logs</h3>
                                </div>
                                <div class="p-4 space-y-1 bg-gray-900 min-h-[100px] max-h-[400px] overflow-y-auto">
                                    <?php if (empty($logs)) : ?>
                                        <p class="text-sm text-gray-400 italic">No logs available for this task.</p>
                                    <?php else : ?>
                                        <?php foreach ($logs as $log) : ?>
                                            <div class="flex items-start text-xs font-mono p-1 rounded <?= $log['level'] === 'error' ? 'bg-red-900/30 text-red-300' : 'text-gray-300 hover:bg-gray-800' ?>">
                                                <span class="text-gray-500 mr-3 shrink-0">[<?= htmlspecialchars(date('H:i:s', strtotime($log['created_at']))) ?>]</span>
                                                <span class="flex-1"><?= htmlspecialchars($log['message'] ?? '') ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (!empty($task['agent_response'])) : ?>
                                <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                                    <div class="bg-blue-50 px-4 py-3 border-b border-gray-200">
                                        <h3 class="text-sm font-bold text-blue-700">Last Agent Analysis</h3>
                                    </div>
                                    <div class="p-6 bg-blue-50/30">
                                        <div class="prose prose-sm max-w-none text-blue-900 markdown-body">
                                            <?= $parsedown->text($taskModel->processGitHubImages($task['agent_response'] ?? '')) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="lg:col-span-1 space-y-6">
                            <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                                <h3 class="text-lg font-bold text-gray-900 mb-4">Status & Links</h3>
                                <div class="space-y-4">
                                    <!-- GitHub Issue -->
                                    <div class="flex items-center justify-between">
                                        <a href="https://github.com/<?= htmlspecialchars($project['github_repo'] ?? '') ?>/issues/<?= htmlspecialchars($task['issue_number'] ?? '') ?>" target="_blank" rel="noopener noreferrer" class="flex items-center text-sm text-blue-600 hover:underline">
                                            <svg class="w-5 h-5 mr-2 text-black" fill="currentColor" viewBox="0 0 24 24"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.43.372.823 1.102.823 2.222 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>
                                            GitHub Issue
                                        </a>
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-<?= $githubIssueColor ?>-100 text-<?= $githubIssueColor ?>-800">
                                            <?= htmlspecialchars($githubIssueStatus) ?>
                                        </span>
                                    </div>

                                    <!-- Jules Session -->
                                    <div class="flex items-center justify-between">
                                        <?php if (!empty($task['jules_url'])) : ?>
                                            <a href="<?= htmlspecialchars($task['jules_url'] ?? '') ?>" target="_blank" rel="noopener noreferrer" class="flex items-center text-sm text-purple-600 hover:underline">
                                                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24"><path d="M21.61,18.91c-.59,0-1.06.48-1.06,1.06s-.48,1.06-1.06,1.06-1.09-.48-1.09-1.06v-5.41c.13-.27.38-.73.38-1.04v-6.03c0-3.68-3.13-6.66-6.81-6.66s-6.66,2.98-6.66,6.66v6.03c0,.43.16.99.38,1.31v5.14c0,.59-.5,1.06-1.09,1.06s-1.06-.48-1.06-1.06-.48-1.06-1.06-1.06-1.06.48-1.06,1.06c0,1.68,1.32,3.05,2.97,3.17.07.01.14.02.21.02s.15,0,.21-.02c1.66-.11,2.91-1.48,2.91-3.17v-4.25s0-.89.77-.89.75.89.75.89v4.21c0,.59.43,1.06,1.02,1.06s1.01-.48,1.01-1.06v-4.21s-.1-.89.76-.89.76.89.76.89v4.21c0,.59.42,1.06,1.01,1.06s1.03-.48,1.03-1.06v-4.21s-.02-.89.75-.89.78.89.78.89v4.25c0,1.68,1.25,3.05,2.9,3.17.07.01.14.02.21.02s.15,0,.21-.02c1.66-.11,2.98-1.48,2.98-3.17,0-.59-.48-1.06-1.06-1.06ZM8.5,12.89c-.59,0-1.06-.6-1.06-1.33s.48-1.33,1.06-1.33,1.06.6,1.06,1.33-.48,1.33-1.06,1.33ZM15.59,12.89c-.59,0-1.06-.6-1.06-1.33s.48-1.33,1.06-1.33,1.06.6,1.06,1.33-.48,1.33-1.06,1.33Z"/></svg>
                                                Jules Session
                                            </a>
                                        <?php else : ?>
                                            <div class="flex items-center text-sm text-gray-500">
                                                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24"><path d="M21.61,18.91c-.59,0-1.06.48-1.06,1.06s-.48,1.06-1.06,1.06-1.09-.48-1.09-1.06v-5.41c.13-.27.38-.73.38-1.04v-6.03c0-3.68-3.13-6.66-6.81-6.66s-6.66,2.98-6.66,6.66v6.03c0,.43.16.99.38,1.31v5.14c0,.59-.5,1.06-1.09,1.06s-1.06-.48-1.06-1.06-.48-1.06-1.06-1.06-1.06.48-1.06,1.06c0,1.68,1.32,3.05,2.97,3.17.07.01.14.02.21.02s.15,0,.21-.02c1.66-.11,2.91-1.48,2.91-3.17v-4.25s0-.89.77-.89.75.89.75.89v4.21c0,.59.43,1.06,1.02,1.06s1.01-.48,1.01-1.06v-4.21s-.1-.89.76-.89.76.89.76.89v4.21c0,.59.42,1.06,1.01,1.06s1.03-.48,1.03-1.06v-4.21s-.02-.89.75-.89.78.89.78.89v4.25c0,1.68,1.25,3.05,2.9,3.17.07.01.14.02.21.02s.15,0,.21-.02c1.66-.11,2.98-1.48,2.98-3.17,0-.59-.48-1.06-1.06-1.06ZM8.5,12.89c-.59,0-1.06-.6-1.06-1.33s.48-1.33,1.06-1.33,1.06.6,1.06,1.33-.48,1.33-1.06,1.33ZM15.59,12.89c-.59,0-1.06-.6-1.06-1.33s.48-1.33,1.06-1.33,1.06.6,1.06,1.33-.48,1.33-1.06,1.33Z"/></svg>
                                                Jules Session
                                            </div>
                                        <?php endif; ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-<?= $julesColor ?>-100 text-<?= $julesColor ?>-800">
                                            <?= htmlspecialchars($julesDisplayStatus) ?>
                                        </span>
                                    </div>

                                    <!-- Pull Request -->
                                    <div class="flex items-center justify-between">
                                        <?php if (!empty($task['pr_url'])) : ?>
                                            <a href="<?= htmlspecialchars($task['pr_url'] ?? '') ?>" target="_blank" rel="noopener noreferrer" class="flex items-center text-sm text-<?= $prColor ?>-600 hover:underline">
                                                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24"><path d="M11 19.25c0 .414-.336.75-.75.75H8.5a.75.75 0 0 1-.75-.75v-1.5c0-.414.336-.75.75-.75h1.75c.414 0 .75.336.75.75v1.5zM11 14.5c0 .414-.336.75-.75.75H8.5a.75.75 0 0 1-.75-.75v-1.5c0-.414.336-.75.75-.75h1.75c.414 0 .75.336.75.75v1.5zM11 9.75c0 .414-.336.75-.75.75H8.5a.75.75 0 0 1-.75-.75v-1.5c0-.414.336-.75.75-.75h1.75c.414 0 .75.336.75.75v1.5zM16 19.25c0 .414-.336.75-.75.75h-1.75a.75.75 0 0 1-.75-.75v-1.5c0-.414.336-.75.75-.75h1.75c.414 0 .75.336.75.75v1.5zM16 14.5c0 .414-.336.75-.75.75h-1.75a.75.75 0 0 1-.75-.75v-1.5c0-.414.336-.75.75-.75h1.75c.414 0 .75.336.75.75v1.5zM16 9.75c0 .414-.336.75-.75.75h-1.75a.75.75 0 0 1-.75-.75v-1.5c0-.414.336-.75.75-.75h1.75c.414 0 .75.336.75.75v1.5zM20.5 2h-17C2.673 2 2 2.673 2 3.5v17c0 .827.673 1.5 1.5 1.5h17c.827 0 1.5-.673 1.5-1.5v-17C22 2.673 21.327 2 20.5 2zM20 18H4V4h16v14z"/></svg>
                                                Pull Request
                                            </a>
                                        <?php else : ?>
                                            <div class="flex items-center text-sm text-gray-500">
                                                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24"><path d="M11 19.25c0 .414-.336.75-.75.75H8.5a.75.75 0 0 1-.75-.75v-1.5c0-.414.336-.75.75-.75h1.75c.414 0 .75.336.75.75v1.5zM11 14.5c0 .414-.336.75-.75.75H8.5a.75.75 0 0 1-.75-.75v-1.5c0-.414.336-.75.75-.75h1.75c.414 0 .75.336.75.75v1.5zM11 9.75c0 .414-.336.75-.75.75H8.5a.75.75 0 0 1-.75-.75v-1.5c0-.414.336-.75.75-.75h1.75c.414 0 .75.336.75.75v1.5zM16 19.25c0 .414-.336.75-.75.75h-1.75a.75.75 0 0 1-.75-.75v-1.5c0-.414.336-.75.75-.75h1.75c.414 0 .75.336.75.75v1.5zM16 14.5c0 .414-.336.75-.75.75h-1.75a.75.75 0 0 1-.75-.75v-1.5c0-.414.336-.75.75-.75h1.75c.414 0 .75.336.75.75v1.5zM16 9.75c0 .414-.336.75-.75.75h-1.75a.75.75 0 0 1-.75-.75v-1.5c0-.414.336-.75.75-.75h1.75c.414 0 .75.336.75.75v1.5zM20.5 2h-17C2.673 2 2 2.673 2 3.5v17c0 .827.673 1.5 1.5 1.5h17c.827 0 1.5-.673 1.5-1.5v-17C22 2.673 21.327 2 20.5 2zM20 18H4V4h16v14z"/></svg>
                                                Pull Request
                                            </div>
                                        <?php endif; ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-<?= $prColor ?>-100 text-<?= $prColor ?>-800">
                                            <?= htmlspecialchars($prStatus) ?>
                                        </span>
                                    </div>

                                    <?php
                                    $isMergeable = ($prDetails && ($prDetails['state'] ?? '') === 'open' && ($prDetails['mergeable_state'] ?? '') === 'clean' && !($prDetails['draft'] ?? false));
                                    if ($isMergeable) : ?>
                                        <div class="mt-4 pt-4 border-t border-gray-100 space-y-3">
                                            <form method="POST">
                                                <input type="hidden" name="csrf_token" value="<?= $auth->getCsrfToken() ?>">
                                                <button type="submit" name="merge_close" class="w-full text-white bg-purple-600 hover:bg-purple-700 focus:ring-4 focus:ring-purple-300 font-medium rounded-lg text-sm px-5 py-2.5 focus:outline-none" onclick="return confirm('Are you sure you want to merge this PR and close the issue?')">
                                                    Merge & Close
                                                </button>
                                            </form>
                                            <form method="POST">
                                                <input type="hidden" name="csrf_token" value="<?= $auth->getCsrfToken() ?>">
                                                <button type="submit" name="merge_close_duplicate" class="w-full text-white bg-indigo-600 hover:bg-indigo-700 focus:ring-4 focus:ring-indigo-300 font-medium rounded-lg text-sm px-5 py-2.5 focus:outline-none" onclick="return confirm('Are you sure you want to merge this PR, close the issue and create a duplicate?')">
                                                    Merge, Close & Duplicate
                                                </button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="mt-6 pt-6 border-t border-gray-100">
                                    <div class="text-xs text-gray-500 space-y-1">
                                        <p>Created: <?= htmlspecialchars($task['created_at'] ?? '') ?></p>
                                        <p>Last Synced: <?= htmlspecialchars($task['last_synced_at'] ?? 'Never') ?></p>
                                        <?php if (!empty($task['jules_session_id'])) : ?>
                                            <p>Jules Session ID: <span class="font-mono"><?= htmlspecialchars($task['jules_session_id'] ?? '') ?></span></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                                <h3 class="text-lg font-bold text-gray-900 mb-4">Project</h3>
                                <p class="text-sm font-medium text-gray-900 mb-1"><?= htmlspecialchars($project['github_repo'] ?? '') ?></p>
                                <p class="text-xs text-gray-500 mb-4">Linked account: <?= htmlspecialchars($project['github_username'] ?? '') ?></p>
                                <a href="project.php?id=<?= $project['project_id'] ?>" class="text-blue-600 hover:underline text-sm font-medium">Back to Project &rarr;</a>
                            </div>

                            <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                                <h3 class="text-lg font-bold text-gray-900 mb-4">Notification Settings</h3>
                                <form method="POST" class="space-y-4">
                                    <input type="hidden" name="csrf_token" value="<?= $auth->getCsrfToken() ?>">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-medium text-gray-700">Mute Notifications</span>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="is_muted" class="sr-only peer" <?= ($taskNotifSettings['is_muted'] ?? false) ? 'checked' : '' ?> onchange="this.form.submit()">
                                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                        </label>
                                    </div>
                                    <input type="hidden" name="update_task_notifications" value="1">
                                    <p class="text-xs text-gray-500">Silence all notifications for this specific task.</p>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
