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

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = $userModel->findById($auth->getUserId());
$julesService = new JulesService(null, $user['jules_api_key'] ?? null);
$telegramService = new TelegramService(null, $user['telegram_bot_token'] ?? null);
$telegramChatId = $userModel->getTelegramChatId($user['user_id']);
$logger = new Logger($db);

$taskId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$task = $taskModel->findById($taskId);

if (!$task) {
    die("Task not found.");
}

$project = $projectModel->findById($task['project_id']);

if (!$project || $project['user_id'] !== $user['user_id']) {
    die("Access denied.");
}

$lastAgentResponse = null;
$errorMessage = null;

$triggerAgent = function($taskId) use ($taskModel, $logger, $user, $project, $julesService, $telegramService, $telegramChatId, &$lastAgentResponse, &$errorMessage, &$task) {
    $t = $taskModel->findById($taskId);
    if ($t && $t['project_id'] === $project['project_id']) {
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
                $githubService->postComment($project['github_repo'], $t['issue_number'], "🤖 Agent has started processing this issue...");
                $logger->log($user['user_id'], $taskId, "Posted 'started' comment to GitHub");
            }

            if ($telegramChatId) {
                $telegramService->sendMessage($telegramChatId, "🤖 <b>Agent Started</b>\nProject: {$project['github_repo']}\nIssue: #{$t['issue_number']} {$t['title']}");
            }

            $logger->log($user['user_id'], $taskId, "Calling Jules API...");
            $lastAgentResponse = $julesService->triggerAgent($t);
            $logger->log($user['user_id'], $taskId, "Received response from Jules API");

            $taskModel->updateAgentResponse($taskId, $lastAgentResponse, 'analyzed');
            $logger->log($user['user_id'], $taskId, "Task agent response updated and status set to analyzed");

            if ($githubService) {
                $githubService->postComment($project['github_repo'], $t['issue_number'], "✅ Agent has completed the analysis:\n\n" . $lastAgentResponse);
                $logger->log($user['user_id'], $taskId, "Posted 'completed' comment to GitHub");
            }

            if ($telegramChatId) {
                $telegramService->sendMessage($telegramChatId, "✅ <b>Agent Completed</b>\nProject: {$project['github_repo']}\nIssue: #{$t['issue_number']}\n\n" . mb_substr($lastAgentResponse, 0, 1000));
            }

            // Refresh task
            $task = $taskModel->findById($taskId);
        } catch (\Exception $e) {
            $errorMessage = "Error triggering agent: " . $e->getMessage();
            $logger->log($user['user_id'], $taskId, "Error: " . $e->getMessage(), "error");
            $taskModel->updateStatus($taskId, 'failed');
            if (isset($githubService) && $githubService) {
                try {
                    $githubService->postComment($project['github_repo'], $t['issue_number'], "❌ Agent failed to process this issue: " . $e->getMessage());
                    $logger->log($user['user_id'], $taskId, "Posted 'failed' comment to GitHub");
                } catch (\Exception $ge) {
                    $logger->log($user['user_id'], $taskId, "Failed to post error comment to GitHub: " . $ge->getMessage(), "error");
                }
            }

            if ($telegramChatId) {
                $telegramService->sendMessage($telegramChatId, "❌ <b>Agent Failed</b>\nProject: {$project['github_repo']}\nIssue: #{$t['issue_number']}\nError: " . $e->getMessage());
            }
            // Refresh task
            $task = $taskModel->findById($taskId);
        }
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

    if ($task && $task['project_id'] === $project['project_id']) {
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
                header("Location: task.php?id=" . $newTask['task_id'] . "&trigger_on_load=1");
                exit;
            } else {
                throw new Exception("Failed to find the newly created task in the database.");
            }
        } catch (Exception $e) {
            $errorMessage = "Error rerunning task: " . $e->getMessage();
        }
    }
}

if (isset($_GET['trigger_on_load']) && $_GET['trigger_on_load'] == '1') {
    $triggerAgent($taskId);
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
                            <li aria-current="page">
                                <div class="flex items-center">
                                    <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>
                                    <span class="text-gray-400 ml-1 md:ml-2 font-medium">Task #<?= htmlspecialchars($task['issue_number']) ?></span>
                                </div>
                            </li>
                        </ol>
                    </nav>

                    <?php if ($errorMessage): ?>
                        <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50" role="alert">
                            <span class="font-medium">Error!</span> <?= htmlspecialchars($errorMessage) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($lastAgentResponse): ?>
                        <div class="p-4 mb-4 text-sm text-blue-800 rounded-lg bg-blue-50" role="alert">
                            <span class="font-medium">Agent Response:</span>
                            <div class="mt-2 p-2 bg-white rounded border border-blue-200 whitespace-pre-wrap font-mono text-xs">
                                <?= htmlspecialchars($lastAgentResponse) ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
                        <div class="lg:col-span-2 space-y-4">
                            <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                                <div class="flex justify-between items-start mb-4">
                                    <h1 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($task['title']) ?></h1>
                                    <span class="px-3 py-1 text-sm font-medium rounded-full bg-<?= $statusColor ?>-100 text-<?= $statusColor ?>-800 flex items-center">
                                        <?php
                                        if ($task['status'] === 'completed') echo '✅ ';
                                        elseif (in_array($task['status'], ['in_progress', 'coding', 'testing'])) echo '🚧 ';
                                        elseif ($task['status'] === 'failed') echo '❌ ';
                                        elseif (in_array($task['status'], ['researching', 'planning', 'awaiting-plan-approval', 'awaiting-user-feedback'])) echo '🔵 ';
                                        else echo '⏳ ';
                                        ?>
                                        <?= htmlspecialchars($task['status']) ?>
                                    </span>
                                </div>

                                <div class="flex flex-wrap gap-2 mb-6">
                                    <?php foreach ($labels as $label): ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded" style="background-color: #<?= $label['color'] ?>; color: <?= (hexdec($label['color']) > 0xffffff/2) ? 'black' : 'white' ?>">
                                            <?= htmlspecialchars($label['name']) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>

                                <div class="prose max-w-none text-gray-700 whitespace-pre-wrap bg-gray-50 p-4 rounded-lg border border-gray-100 mb-6">
                                    <?= htmlspecialchars($task['body']) ?>
                                </div>

                                <?php if (!empty($task['agent_response'])): ?>
                                    <div class="mt-8">
                                        <h3 class="text-lg font-bold text-gray-900 mb-4">Last Agent Response</h3>
                                        <div class="p-4 bg-blue-50 border border-blue-100 rounded-lg whitespace-pre-wrap font-mono text-sm text-blue-900">
                                            <?= htmlspecialchars($task['agent_response']) ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                                <h3 class="text-lg font-bold text-gray-900 mb-4">Task Logs</h3>
                                <div class="space-y-2">
                                    <?php if (empty($logs)): ?>
                                        <p class="text-sm text-gray-500 italic">No logs available for this task.</p>
                                    <?php else: ?>
                                        <?php foreach ($logs as $log): ?>
                                            <div class="flex items-start text-xs font-mono p-2 rounded <?= $log['level'] === 'error' ? 'bg-red-50 text-red-700' : 'bg-gray-50 text-gray-700' ?>">
                                                <span class="text-gray-400 mr-3">[<?= htmlspecialchars($log['created_at']) ?>]</span>
                                                <span class="flex-1"><?= htmlspecialchars($log['message']) ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="lg:col-span-1 space-y-4">
                            <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                                <h3 class="text-lg font-bold text-gray-900 mb-4">Links & Status</h3>
                                <ul class="space-y-3">
                                    <li>
                                        <a href="https://github.com/<?= htmlspecialchars($project['github_repo']) ?>/issues/<?= htmlspecialchars($task['issue_number']) ?>" target="_blank" rel="noopener noreferrer" class="flex items-center text-blue-600 hover:underline">
                                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.43.372.823 1.102.823 2.222 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>
                                            View on GitHub Issue
                                        </a>
                                    </li>
                                    <?php if (!empty($task['pr_url'])): ?>
                                        <li>
                                            <a href="<?= htmlspecialchars($task['pr_url']) ?>" target="_blank" rel="noopener noreferrer" class="flex items-center text-green-600 hover:underline font-bold">
                                                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24"><path d="M11 19.25c0 .414-.336.75-.75.75H8.5a.75.75 0 0 1-.75-.75v-1.5c0-.414.336-.75.75-.75h1.75c.414 0 .75.336.75.75v1.5zM11 14.5c0 .414-.336.75-.75.75H8.5a.75.75 0 0 1-.75-.75v-1.5c0-.414.336-.75.75-.75h1.75c.414 0 .75.336.75.75v1.5zM11 9.75c0 .414-.336.75-.75.75H8.5a.75.75 0 0 1-.75-.75v-1.5c0-.414.336-.75.75-.75h1.75c.414 0 .75.336.75.75v1.5zM16 19.25c0 .414-.336.75-.75.75h-1.75a.75.75 0 0 1-.75-.75v-1.5c0-.414.336-.75.75-.75h1.75c.414 0 .75.336.75.75v1.5zM16 14.5c0 .414-.336.75-.75.75h-1.75a.75.75 0 0 1-.75-.75v-1.5c0-.414.336-.75.75-.75h1.75c.414 0 .75.336.75.75v1.5zM16 9.75c0 .414-.336.75-.75.75h-1.75a.75.75 0 0 1-.75-.75v-1.5c0-.414.336-.75.75-.75h1.75c.414 0 .75.336.75.75v1.5zM20.5 2h-17C2.673 2 2 2.673 2 3.5v17c0 .827.673 1.5 1.5 1.5h17c.827 0 1.5-.673 1.5-1.5v-17C22 2.673 21.327 2 20.5 2zM20 18H4V4h16v14z"/></svg>
                                                View Pull Request
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    <?php if (!empty($task['jules_url'])): ?>
                                        <li>
                                            <a href="<?= htmlspecialchars($task['jules_url']) ?>" target="_blank" rel="noopener noreferrer" class="flex items-center text-purple-600 hover:underline">
                                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5a3 3 0 1 0-5.997.125 4 4 0 0 0-2.526 5.77 4 4 0 0 0 .52 5.586 3.004 3.004 0 0 0 5.193 2.019A4 4 0 0 1 12 18c.35 0 .692.045 1.02.13a3.004 3.004 0 0 0 5.193-2.019 4 4 0 0 0 .52-5.586 4 4 0 0 0-2.526-5.77A3 3 0 1 0 12 5M9 14.5a2.5 2.5 0 0 0 2.46-2.019M15 14.5a2.5 2.5 0 0 1-2.46-2.019"/></svg>
                                                View Jules Session
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>

                                <div class="mt-6 pt-6 border-t border-gray-100">
                                    <div class="flex flex-col space-y-2 mb-4">
                                        <?php
                                        $isClosed = ($githubData['state'] ?? 'open') === 'closed';
                                        $isCompleted = ($task['status'] ?? '') === 'completed';
                                        $isImplemented = ($task['status'] ?? '') === 'implemented';
                                        ?>
                                        <?php if (!$isClosed && !$isCompleted && !$isImplemented): ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?= $auth->getCsrfToken() ?>">
                                                <button type="submit" name="trigger_agent" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 focus:outline-none w-full">Run Agent</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($isCompleted || $isImplemented): ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?= $auth->getCsrfToken() ?>">
                                                <button type="submit" name="rerun_task" class="text-white bg-indigo-600 hover:bg-indigo-700 focus:ring-4 focus:ring-indigo-300 font-medium rounded-lg text-sm px-5 py-2.5 focus:outline-none w-full">Rerun</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>

                                    <div class="text-xs text-gray-500 space-y-1">
                                        <p>Created: <?= htmlspecialchars($task['created_at']) ?></p>
                                        <p>Last Synced: <?= htmlspecialchars($task['last_synced_at'] ?? 'Never') ?></p>
                                        <?php if (!empty($task['jules_session_id'])): ?>
                                            <p>Jules Session ID: <span class="font-mono"><?= htmlspecialchars($task['jules_session_id']) ?></span></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                                <h3 class="text-lg font-bold text-gray-900 mb-4">Project</h3>
                                <p class="text-sm font-medium text-gray-900 mb-1"><?= htmlspecialchars($project['github_repo']) ?></p>
                                <p class="text-xs text-gray-500 mb-4">Linked account: <?= htmlspecialchars($project['github_username']) ?></p>
                                <a href="project.php?id=<?= $project['project_id'] ?>" class="text-blue-600 hover:underline text-sm font-medium">Back to Project &rarr;</a>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
