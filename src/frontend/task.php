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
$telegramService = new TelegramService();
$logger = new Logger($db);

if (false && !$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = $userModel->findById($auth->getUserId());
$user = $userModel->findById(1);
$julesService = new JulesService(null, $user['jules_api_key'] ?? null);
$telegramService = new TelegramService(null, $user['telegram_bot_token'] ?? null);
$telegramChatId = $userModel->getTelegramChatId($user['user_id']);

$taskId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$task = $taskModel->findById($taskId);

if (!$task || $task['user_id'] !== $user['user_id']) {
    die("Task not found or access denied.");
}

$project = $projectModel->findById($task['project_id']);
if (!$project) {
    die("Project not found.");
}

$githubData = json_decode($task['github_data'] ?? '{}', true);
$labels = $githubData['labels'] ?? [];

$lastAgentResponse = null;
$errorMessage = null;

// Handle Agent Trigger (Copied from project.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['trigger_agent'])) {
    if (!$auth->validateCsrfToken($_POST['csrf_token'] ?? null)) {
        die("CSRF token validation failed.");
    }

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
            $logger->log($user['user_id'], $taskId, "Posted 'started' comment to GitHub");
        }

        if ($telegramChatId) {
            $telegramService->sendMessage($telegramChatId, "🤖 <b>Agent Started</b>\nProject: {$project['github_repo']}\nIssue: #{$task['issue_number']} {$task['title']}");
        }

        $logger->log($user['user_id'], $taskId, "Calling Jules API...");
        $lastAgentResponse = $julesService->triggerAgent($task);
        $logger->log($user['user_id'], $taskId, "Received response from Jules API");

        $taskModel->updateAgentResponse($taskId, $lastAgentResponse, 'completed');
        $logger->log($user['user_id'], $taskId, "Task agent response updated and status set to completed");

        if ($githubService) {
            $githubService->postComment($project['github_repo'], $task['issue_number'], "✅ Agent has completed the analysis:\n\n" . $lastAgentResponse);
            $logger->log($user['user_id'], $taskId, "Posted 'completed' comment to GitHub");
        }

        if ($telegramChatId) {
            $telegramService->sendMessage($telegramChatId, "✅ <b>Agent Completed</b>\nProject: {$project['github_repo']}\nIssue: #{$task['issue_number']}\n\n" . mb_substr($lastAgentResponse, 0, 1000));
        }

        // Refresh task data
        $task = $taskModel->findById($taskId);
    } catch (\Exception $e) {
        $errorMessage = "Error triggering agent: " . $e->getMessage();
        $logger->log($user['user_id'], $taskId, "Error: " . $e->getMessage(), "error");
        $taskModel->updateStatus($taskId, 'failed');
        if (isset($githubService) && $githubService) {
            try {
                $githubService->postComment($project['github_repo'], $task['issue_number'], "❌ Agent failed to process this issue: " . $e->getMessage());
                $logger->log($user['user_id'], $taskId, "Posted 'failed' comment to GitHub");
            } catch (\Exception $ge) {
                $logger->log($user['user_id'], $taskId, "Failed to post error comment to GitHub: " . $ge->getMessage(), "error");
            }
        }

        if ($telegramChatId) {
            $telegramService->sendMessage($telegramChatId, "❌ <b>Agent Failed</b>\nProject: {$project['github_repo']}\nIssue: #{$task['issue_number']}\nError: " . $e->getMessage());
        }
        $task = $taskModel->findById($taskId);
    }
}

$taskLogs = $taskModel->getLogs($taskId);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Details - <?= htmlspecialchars($task['title']) ?></title>
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
                                        <?= htmlspecialchars($project['github_repo']) ?>
                                    </a>
                                </div>
                            </li>
                            <li>
                                <div class="flex items-center">
                                    <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>
                                    <span class="text-gray-400 ml-1 md:ml-2 font-medium">Task #<?= htmlspecialchars($task['issue_number']) ?></span>
                                </div>
                            </li>
                        </ol>
                    </nav>

                    <div class="mb-4 flex flex-col md:flex-row md:items-center justify-between">
                        <div>
                            <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">
                                <a href="https://github.com/<?= htmlspecialchars($project['github_repo']) ?>/issues/<?= htmlspecialchars($task['issue_number']) ?>" target="_blank" rel="noopener noreferrer" class="hover:underline">
                                    #<?= htmlspecialchars($task['issue_number']) ?> <?= htmlspecialchars($task['title']) ?>
                                </a>
                            </h1>
                            <div class="flex flex-wrap gap-2 mt-2">
                                <?php foreach ($labels as $label): ?>
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded-full border" style="background-color: #<?= $label['color'] ?>22; border-color: #<?= $label['color'] ?>; color: #<?= $label['color'] ?>;">
                                        <?= htmlspecialchars($label['name']) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="mt-4 md:mt-0 flex space-x-2">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= $auth->getCsrfToken() ?>">
                                <button type="submit" name="trigger_agent" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 focus:outline-none">Run Agent</button>
                            </form>
                            <a href="https://github.com/<?= htmlspecialchars($project['github_repo']) ?>/issues/<?= htmlspecialchars($task['issue_number']) ?>" target="_blank" rel="noopener noreferrer" class="text-gray-900 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-5 py-2.5 focus:outline-none">View on GitHub</a>
                        </div>
                    </div>

                    <?php if ($errorMessage): ?>
                        <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50" role="alert">
                            <span class="font-medium">Error!</span> <?= htmlspecialchars($errorMessage) ?>
                        </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                        <div class="lg:col-span-2 space-y-4">
                            <!-- Task Status & Info -->
                            <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                                <h3 class="text-lg font-bold text-gray-900 mb-4">Task Information</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-sm text-gray-500">Internal Status</p>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $task['status'] === 'completed' ? 'bg-green-100 text-green-800' : ($task['status'] === 'in_progress' ? 'bg-blue-100 text-blue-800' : ($task['status'] === 'failed' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800')) ?>">
                                            <?php
                                            if ($task['status'] === 'completed') echo '✅ ';
                                            elseif ($task['status'] === 'in_progress') echo '🚧 ';
                                            elseif ($task['status'] === 'failed') echo '❌ ';
                                            else echo '⏳ ';
                                            ?>
                                            <?= htmlspecialchars($task['status']) ?>
                                        </span>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Jules Status</p>
                                        <span class="text-sm font-medium text-gray-900"><?= htmlspecialchars($task['jules_status'] ?? 'N/A') ?></span>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Created At</p>
                                        <span class="text-sm font-medium text-gray-900"><?= htmlspecialchars($task['created_at']) ?></span>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Last Synced</p>
                                        <span class="text-sm font-medium text-gray-900"><?= htmlspecialchars($task['last_synced_at'] ?? 'Never') ?></span>
                                    </div>
                                    <?php if ($task['pr_url']): ?>
                                        <div class="md:col-span-2">
                                            <p class="text-sm text-gray-500">Pull Request</p>
                                            <a href="<?= htmlspecialchars($task['pr_url']) ?>" target="_blank" rel="noopener noreferrer" class="text-sm font-medium text-blue-600 hover:underline"><?= htmlspecialchars($task['pr_url']) ?></a>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($task['jules_url']): ?>
                                        <div class="md:col-span-2">
                                            <p class="text-sm text-gray-500">Jules Session</p>
                                            <a href="<?= htmlspecialchars($task['jules_url']) ?>" target="_blank" rel="noopener noreferrer" class="text-sm font-medium text-blue-600 hover:underline"><?= htmlspecialchars($task['jules_url']) ?></a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Issue Body -->
                            <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                                <h3 class="text-lg font-bold text-gray-900 mb-4">Issue Body</h3>
                                <div class="p-4 bg-gray-50 rounded-lg border border-gray-100 whitespace-pre-wrap text-sm text-gray-700 font-sans">
                                    <?= htmlspecialchars($task['body'] ?? 'No description provided.') ?>
                                </div>
                            </div>

                            <!-- Agent Response -->
                            <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                                <h3 class="text-lg font-bold text-gray-900 mb-4">Last Agent Response</h3>
                                <?php if ($task['agent_response']): ?>
                                    <div class="p-4 bg-blue-50 rounded-lg border border-blue-100 whitespace-pre-wrap text-sm text-blue-900 font-mono">
                                        <?= htmlspecialchars($task['agent_response']) ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-sm text-gray-500 italic">No agent response available yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <!-- Task Logs -->
                            <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                                <h3 class="text-lg font-bold text-gray-900 mb-4">Detailed Logs</h3>
                                <div class="space-y-2 max-h-[600px] overflow-y-auto pr-2">
                                    <?php if (empty($taskLogs)): ?>
                                        <p class="text-sm text-gray-500 italic">No logs available.</p>
                                    <?php else: ?>
                                        <?php foreach ($taskLogs as $log): ?>
                                            <div class="text-xs p-2 rounded <?= $log['level'] === 'error' ? 'bg-red-50 border border-red-100' : 'bg-gray-50 border border-gray-100' ?>">
                                                <div class="flex justify-between text-[10px] text-gray-400 mb-1">
                                                    <span><?= htmlspecialchars($log['created_at']) ?></span>
                                                    <span class="uppercase font-bold"><?= htmlspecialchars($log['level']) ?></span>
                                                </div>
                                                <div class="<?= $log['level'] === 'error' ? 'text-red-700 font-medium' : 'text-gray-700' ?>">
                                                    <?= htmlspecialchars($log['message']) ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- GitHub Metadata -->
                            <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                                <h3 class="text-lg font-bold text-gray-900 mb-4">GitHub Metadata</h3>
                                <div class="text-xs font-mono bg-gray-900 text-green-400 p-4 rounded-lg overflow-x-auto">
                                    <pre><?= htmlspecialchars(json_encode($githubData, JSON_PRETTY_PRINT)) ?></pre>
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
