<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;
use App\Auth;
use App\User;
use App\Project;
use App\Task;
use App\JulesService;
use App\GitHubService;
use App\TelegramService;
use App\Logger;

$auth = new Auth();
$db = new Database();
$userModel = new User($db);
$projectModel = new Project($db);
$taskModel = new Task($db);
$logger = new Logger($db);

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = $userModel->findById($auth->getUserId());
$julesService = new JulesService(null, $user['jules_api_key'] ?? null);
$telegramService = new TelegramService(null, $user['telegram_bot_token'] ?? null);
$telegramChatId = $userModel->getTelegramChatId($user['user_id']);

$taskId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$task = $taskModel->findById($taskId);

if (!$task) {
    die("Task not found.");
}

$project = $projectModel->findById($task['project_id']);

if (!$project || $project['user_id'] !== $user['user_id']) {
    die("Access denied.");
}

$errorMessage = null;
$successMessage = null;

$triggerAgent = function($taskId) use ($taskModel, $logger, $user, $project, $julesService, $telegramService, $telegramChatId, &$errorMessage, &$task) {
    try {
        $logger->log($user['user_id'], $taskId, "Agent triggered by user " . $user['name']);
        $githubToken = $project['github_token'] ?? null;
        $githubService = null;
        if ($githubToken) {
            $githubService = new GitHubService(null, $githubToken);
        }

        // Update status to in_progress
        $taskModel->updateStatus($taskId, 'in_progress');
        $logger->log($user['user_id'], $taskId, "Task status updated to in_progress");

        if ($githubService) {
            $githubService->postComment($project['github_repo'], $task['issue_number'], "🤖 Agent has started processing this issue...");
        }

        if ($telegramChatId) {
            $telegramService->sendMessage($telegramChatId, "🤖 <b>Agent Started</b>\nProject: {$project['github_repo']}\nIssue: #{$task['issue_number']} {$task['title']}");
        }

        $lastAgentResponse = $julesService->triggerAgent($task);
        $taskModel->updateAgentResponse($taskId, $lastAgentResponse, 'analyzed');

        if ($githubService) {
            $githubService->postComment($project['github_repo'], $task['issue_number'], "✅ Agent has completed the analysis:\n\n" . $lastAgentResponse);
        }

        if ($telegramChatId) {
            $telegramService->sendMessage($telegramChatId, "✅ <b>Agent Completed</b>\nProject: {$project['github_repo']}\nIssue: #{$task['issue_number']}\n\n" . mb_substr($lastAgentResponse, 0, 1000));
        }

        // Refresh task
        $task = $taskModel->findById($taskId);
    } catch (\Exception $e) {
        $errorMessage = "Error triggering agent: " . $e->getMessage();
        $logger->log($user['user_id'], $taskId, "Error: " . $e->getMessage(), "error");
        $taskModel->updateStatus($taskId, 'failed');
        // Refresh task
        $task = $taskModel->findById($taskId);
    }
};

// Handle Agent Trigger
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['trigger_agent'])) {
    if (!$auth->validateCsrfToken($_POST['csrf_token'] ?? null)) {
        die("CSRF token validation failed.");
    }
    $triggerAgent($taskId);
}

// Handle Rerun Task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rerun_task'])) {
    if (!$auth->validateCsrfToken($_POST['csrf_token'] ?? null)) {
        die("CSRF token validation failed.");
    }

    try {
        $githubToken = $project['github_token'] ?? null;
        if (!$githubToken) {
            throw new Exception("GitHub token not found for this project.");
        }

        $githubData = json_decode($task['github_data'] ?? '{}', true);
        $labels = array_map(fn($l) => $l['name'], $githubData['labels'] ?? []);

        $githubService = new GitHubService(null, $githubToken);
        $newIssue = $githubService->createIssue($project['github_repo'], $task['title'], $task['body'], $labels);

        $taskModel->upsert($user['user_id'], $project['project_id'], $newIssue);
        $newTask = $taskModel->findByIssueNumber($project['project_id'], $newIssue['number']);

        if ($newTask) {
            header("Location: task.php?id=" . $newTask['task_id'] . "&success=rerun_started");
            exit;
        } else {
            throw new Exception("Failed to find the newly created task in the database.");
        }
    } catch (Exception $e) {
        $errorMessage = "Error rerunning task: " . $e->getMessage();
    }
}

// Handle Sync Task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_task'])) {
    if (!$auth->validateCsrfToken($_POST['csrf_token'] ?? null)) {
        die("CSRF token validation failed.");
    }

    try {
        $githubToken = $project['github_token'] ?? null;
        if (!$githubToken) {
            throw new Exception("GitHub token not found for this project.");
        }

        $githubService = new GitHubService(null, $githubToken);
        $issue = $githubService->getIssue($project['github_repo'], $task['issue_number']);
        $taskModel->upsert($user['user_id'], $project['project_id'], $issue);

        // Also refresh Jules status if it's jules related
        $julesService = new JulesService(null, $user['jules_api_key'] ?? null);
        $taskModel->refreshJulesStatus($user['user_id'], $githubService, $julesService);

        header("Location: task.php?id=$taskId&success=synced");
        exit;
    } catch (Exception $e) {
        $errorMessage = "Error syncing task: " . $e->getMessage();
    }
}

if (isset($_GET['success']) && $_GET['success'] === 'synced') {
    $successMessage = "Task synced from GitHub.";
}
if (isset($_GET['success']) && $_GET['success'] === 'rerun_started') {
    $successMessage = "Task rerun started. A new issue was created.";
}

$githubData = json_decode($task['github_data'] ?? '{}', true);
$labels = $githubData['labels'] ?? [];
$statusColor = $taskModel->getStatusColor($task);
$logs = $taskModel->getLogs($taskId);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task #<?= htmlspecialchars($task['issue_number']) ?> - Agent Control</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
                <div class="px-4 pt-6 max-w-7xl mx-auto">
                    <nav class="flex mb-6" aria-label="Breadcrumb">
                        <ol class="inline-flex items-center space-x-1 md:space-x-2">
                            <li class="inline-flex items-center">
                                <a href="index.php" class="text-gray-700 hover:text-gray-900 inline-flex items-center text-sm">
                                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path></svg>
                                    Dashboard
                                </a>
                            </li>
                            <li>
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>
                                    <a href="project.php?id=<?= $project['project_id'] ?>" class="text-gray-700 hover:text-gray-900 ml-1 md:ml-2 font-medium text-sm">
                                        <?= htmlspecialchars($project['github_repo']) ?>
                                    </a>
                                </div>
                            </li>
                            <li aria-current="page">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>
                                    <span class="text-gray-400 ml-1 md:ml-2 font-medium text-sm">Task #<?= htmlspecialchars($task['issue_number']) ?></span>
                                </div>
                            </li>
                        </ol>
                    </nav>

                    <?php if ($errorMessage): ?>
                        <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50 border border-red-100" role="alert">
                            <span class="font-bold">Error:</span> <?= htmlspecialchars($errorMessage) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($successMessage): ?>
                        <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50 border border-green-100" role="alert">
                            <span class="font-bold">Success:</span> <?= htmlspecialchars($successMessage) ?>
                        </div>
                    <?php endif; ?>

                    <div class="mb-8">
                        <div class="flex flex-wrap items-center justify-between gap-4 mb-3">
                            <div class="flex items-center space-x-3">
                                <span class="text-lg font-mono text-gray-500 font-medium">#<?= htmlspecialchars($task['issue_number']) ?></span>
                                <?php
                                $bgClass = 'bg-gray-100 text-gray-800';
                                if ($statusColor === 'green') $bgClass = 'bg-green-100 text-green-800';
                                elseif ($statusColor === 'yellow') $bgClass = 'bg-yellow-100 text-yellow-800';
                                elseif ($statusColor === 'blue') $bgClass = 'bg-blue-100 text-blue-800';
                                elseif ($statusColor === 'red') $bgClass = 'bg-red-100 text-red-800';
                                elseif ($statusColor === 'purple') $bgClass = 'bg-purple-100 text-purple-800';
                                ?>
                                <span class="px-3 py-1 text-sm font-bold rounded-full <?= $bgClass ?> flex items-center shadow-sm border border-black/5">
                                    <?php
                                    $githubData = json_decode($task['github_data'] ?? '{}', true);
                                    if (($githubData['state'] ?? 'open') === 'closed') echo '✅ ';
                                    elseif ($task['status'] === 'completed') echo '✅ ';
                                    elseif ($task['status'] === 'failed') echo '❌ ';
                                    elseif (in_array($task['status'], ['pending', 'analyzed', 'researching', 'planning', 'in_progress', 'coding', 'testing', 'implemented'])) echo '🚧 ';
                                    else echo '⏳ ';
                                    ?>
                                    <?= htmlspecialchars($task['status']) ?>
                                </span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <?php
                                $isClosed = ($githubData['state'] ?? 'open') === 'closed';
                                $isCompleted = ($task['status'] ?? '') === 'completed';
                                $isImplemented = ($task['status'] ?? '') === 'implemented';
                                ?>
                                <?php if (!$isClosed && !$isCompleted && !$isImplemented): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="csrf_token" value="<?= $auth->getCsrfToken() ?>">
                                        <button type="submit" name="trigger_agent" class="text-white bg-blue-600 hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 font-bold rounded-lg text-sm px-5 py-2.5 focus:outline-none transition-all shadow-md hover:shadow-lg active:scale-95">Run Agent</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($isCompleted || $isImplemented): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="csrf_token" value="<?= $auth->getCsrfToken() ?>">
                                        <button type="submit" name="rerun_task" class="text-white bg-indigo-600 hover:bg-indigo-700 focus:ring-4 focus:ring-indigo-300 font-bold rounded-lg text-sm px-5 py-2.5 focus:outline-none transition-all shadow-md hover:shadow-lg active:scale-95">Rerun</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <h1 class="text-4xl font-black text-gray-900 leading-tight tracking-tight">
                            <?= htmlspecialchars($task['title']) ?>
                        </h1>
                        <div class="flex flex-wrap gap-2 mt-4">
                            <?php foreach ($labels as $label): ?>
                                <span class="px-2.5 py-1 text-xs font-bold rounded shadow-sm" style="background-color: #<?= $label['color'] ?>; color: <?= (hexdec($label['color']) > 0xffffff/2) ? 'black' : 'white' ?>">
                                    <?= htmlspecialchars($label['name']) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                        <div class="lg:col-span-2 space-y-6">
                            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                                <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                                    <h3 class="text-lg font-bold text-gray-900">Description</h3>
                                </div>
                                <div class="p-6">
                                    <div class="prose max-w-none text-gray-800 whitespace-pre-wrap leading-relaxed">
                                        <?= htmlspecialchars($task['body']) ?>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($task['agent_response'])): ?>
                                <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                                    <div class="px-6 py-4 border-b border-gray-100 bg-blue-50/50 flex justify-between items-center">
                                        <h3 class="text-lg font-bold text-blue-900">Latest Agent Analysis</h3>
                                        <span class="text-[10px] font-mono text-blue-500 uppercase tracking-widest font-bold">Jules AI</span>
                                    </div>
                                    <div class="p-6 bg-blue-50/30 font-mono text-sm text-blue-950 leading-relaxed overflow-x-auto">
                                        <?= htmlspecialchars($task['agent_response']) ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                                <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                                    <h3 class="text-lg font-bold text-gray-900">Activity Logs</h3>
                                    <span class="px-2 py-0.5 text-[10px] font-bold bg-gray-200 text-gray-600 rounded uppercase">Live</span>
                                </div>
                                <div class="p-4 max-h-[400px] overflow-y-auto space-y-2 bg-gray-900">
                                    <?php if (empty($logs)): ?>
                                        <p class="text-sm text-gray-500 italic text-center py-8">No logs available for this task.</p>
                                    <?php else: ?>
                                        <?php foreach ($logs as $log): ?>
                                            <div class="flex items-start text-[11px] font-mono p-1.5 rounded border border-white/5 <?= $log['level'] === 'error' ? 'bg-red-900/30 text-red-300 border-red-500/20' : 'text-gray-300' ?>">
                                                <span class="text-gray-600 mr-3 shrink-0"><?= htmlspecialchars(date('H:i:s', strtotime($log['created_at']))) ?></span>
                                                <span class="flex-1"><?= htmlspecialchars($log['message']) ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="lg:col-span-1 space-y-6">
                            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                                <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                                    <h3 class="text-lg font-bold text-gray-900">Links & Assets</h3>
                                </div>
                                <div class="p-6 space-y-4">
                                    <a href="https://github.com/<?= htmlspecialchars($project['github_repo']) ?>/issues/<?= htmlspecialchars($task['issue_number']) ?>" target="_blank" rel="noopener noreferrer" class="flex items-center p-3 rounded-lg border border-gray-100 hover:bg-gray-50 transition-colors text-blue-600 font-bold group">
                                        <svg class="w-5 h-5 mr-3 group-hover:scale-110 transition-transform" fill="currentColor" viewBox="0 0 24 24"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.43.372.823 1.102.823 2.222 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>
                                        GitHub Issue
                                    </a>
                                    <?php if (!empty($task['pr_url'])): ?>
                                        <a href="<?= htmlspecialchars($task['pr_url']) ?>" target="_blank" rel="noopener noreferrer" class="flex items-center p-3 rounded-lg border border-green-100 bg-green-50/30 hover:bg-green-50 transition-colors text-green-700 font-bold group">
                                            <svg class="w-5 h-5 mr-3 group-hover:scale-110 transition-transform" fill="currentColor" viewBox="0 0 24 24"><path d="M11 19.25c0 .414-.336.75-.75.75H8.5a.75.75 0 0 1-.75-.75v-1.5c0-.414.336-.75.75-.75h1.75c.414 0 .75.336.75.75v1.5zM11 14.5c0 .414-.336.75-.75.75H8.5a.75.75 0 0 1-.75-.75v-1.5c0-.414.336-.75.75-.75h1.75c.414 0 .75.336.75.75v1.5zM11 9.75c0 .414-.336.75-.75.75H8.5a.75.75 0 0 1-.75-.75v-1.5c0-.414.336-.75.75-.75h1.75c.414 0 .75.336.75.75v1.5zM16 19.25c0 .414-.336.75-.75.75h-1.75a.75.75 0 0 1-.75-.75v-1.5c0-.414.336-.75.75-.75h1.75c.414 0 .75.336.75.75v1.5zM16 14.5c0 .414-.336.75-.75.75h-1.75a.75.75 0 0 1-.75-.75v-1.5c0-.414.336-.75.75-.75h1.75c.414 0 .75.336.75.75v1.5zM16 9.75c0 .414-.336.75-.75.75h-1.75a.75.75 0 0 1-.75-.75v-1.5c0-.414.336-.75.75-.75h1.75c.414 0 .75.336.75.75v1.5zM20.5 2h-17C2.673 2 2 2.673 2 3.5v17c0 .827.673 1.5 1.5 1.5h17c.827 0 1.5-.673 1.5-1.5v-17C22 2.673 21.327 2 20.5 2zM20 18H4V4h16v14z"/></svg>
                                            Pull Request
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($task['jules_url'])): ?>
                                        <a href="<?= htmlspecialchars($task['jules_url']) ?>" target="_blank" rel="noopener noreferrer" class="flex items-center p-3 rounded-lg border border-purple-100 bg-purple-50/30 hover:bg-purple-50 transition-colors text-purple-700 font-bold group">
                                            <svg class="w-5 h-5 mr-3 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5a3 3 0 1 0-5.997.125 4 4 0 0 0-2.526 5.77 4 4 0 0 0 .52 5.586 3.004 3.004 0 0 0 5.193 2.019A4 4 0 0 1 12 18c.35 0 .692.045 1.02.13a3.004 3.004 0 0 0 5.193-2.019 4 4 0 0 0 .52-5.586 4 4 0 0 0-2.526-5.77A3 3 0 1 0 12 5M9 14.5a2.5 2.5 0 0 0 2.46-2.019M15 14.5a2.5 2.5 0 0 1-2.46-2.019"/></svg>
                                            Jules Session
                                        </a>
                                    <?php endif; ?>

                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?= $auth->getCsrfToken() ?>">
                                        <button type="submit" name="sync_task" class="w-full flex items-center justify-center p-3 rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors text-gray-700 font-medium text-sm group">
                                            <svg class="w-4 h-4 mr-2 group-hover:rotate-180 transition-transform duration-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                            Sync from GitHub
                                        </button>
                                    </form>

                                    <div class="pt-4 border-t border-gray-100">
                                        <div class="grid grid-cols-2 gap-4 text-[10px] text-gray-400 font-medium uppercase tracking-wider">
                                            <div>
                                                <p class="mb-1 text-gray-300">Created</p>
                                                <p class="text-gray-600"><?= htmlspecialchars(date('M j, Y H:i', strtotime($task['created_at']))) ?></p>
                                            </div>
                                            <div>
                                                <p class="mb-1 text-gray-300">Last Synced</p>
                                                <p class="text-gray-600"><?= htmlspecialchars($task['last_synced_at'] ? date('M j, Y H:i', strtotime($task['last_synced_at'])) : 'Never') ?></p>
                                            </div>
                                        </div>
                                        <?php if (!empty($task['jules_session_id'])): ?>
                                            <div class="mt-4">
                                                <p class="text-[10px] text-gray-300 font-medium uppercase tracking-wider mb-1">Session ID</p>
                                                <p class="text-[10px] font-mono text-gray-500 bg-gray-50 p-1.5 rounded border border-gray-100 truncate"><?= htmlspecialchars($task['jules_session_id']) ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                                <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                                    <h3 class="text-lg font-bold text-gray-900">Project Context</h3>
                                </div>
                                <div class="p-6">
                                    <div class="flex items-center mb-4">
                                        <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center mr-3 text-gray-400">
                                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.43.372.823 1.102.823 2.222 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>
                                        </div>
                                        <div>
                                            <p class="text-sm font-bold text-gray-900"><?= htmlspecialchars($project['github_repo']) ?></p>
                                            <p class="text-[10px] text-gray-500 uppercase tracking-tight font-medium">Linked as <?= htmlspecialchars($project['github_username']) ?></p>
                                        </div>
                                    </div>
                                    <a href="project.php?id=<?= $project['project_id'] ?>" class="block w-full text-center py-2.5 rounded-lg bg-gray-50 text-gray-700 font-bold text-xs hover:bg-gray-100 transition-colors">
                                        Open Project Dashboard
                                    </a>
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
