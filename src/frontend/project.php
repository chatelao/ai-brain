<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;
use App\Auth;
use App\User;
use App\Project;
use App\Task;
use App\IssueTemplate;
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

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = $userModel->findById($auth->getUserId());
$julesService = new JulesService(null, $user['jules_api_key'] ?? null);
$telegramService = new TelegramService(null, $user['telegram_bot_token'] ?? null);
$telegramChatId = $userModel->getTelegramChatId($user['user_id']);
$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$project = $projectModel->findById($projectId);

if (!$project || $project['user_id'] !== $user['user_id']) {
    die("Project not found or access denied.");
}

// Automatic Sync on Page Load
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['success']) && !isset($_GET['error'])) {
    $githubToken = $project['github_token'] ?? null;
    if ($githubToken) {
        try {
            $githubService = new GitHubService(null, $githubToken);
            $taskModel->syncIssues($user['user_id'], $project['project_id'], $project['github_repo'], $githubService);
            header("Location: project.php?id=$projectId&success=synced");
            exit;
        } catch (Exception $e) {
            // Silently fail automatic sync to not disrupt the user
        }
    }
}

$templateModel = new IssueTemplate($db);
$templates = $templateModel->findByUserId($user['user_id']);

$tasks = $taskModel->findByProjectId($projectId);
$lastAgentResponse = null;
$errorMessage = null;

$githubToken = $project['github_token'] ?? null;
$roadmapFiles = [];
if ($githubToken) {
    try {
        $githubService = new GitHubService(null, $githubToken);
        $roadmapFiles = $githubService->getRoadmapFiles($project['github_repo']);
    } catch (Exception $e) {
        // Silently fail or log roadmap fetching
    }
}

// Handle Agent Trigger
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['trigger_agent'])) {
    if (!$auth->validateCsrfToken($_POST['csrf_token'] ?? null)) {
        die("CSRF token validation failed.");
    }

    $taskId = (int)$_POST['task_id'];
    $task = $taskModel->findById($taskId);

    if ($task && $task['project_id'] === $project['project_id']) {
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

            // Refresh tasks
            $tasks = $taskModel->findByProjectId($projectId);
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
        }
    }
}

// Handle Create Issue from Template
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_from_template'])) {
    if (!$auth->validateCsrfToken($_POST['csrf_token'] ?? null)) {
        die("CSRF token validation failed.");
    }

    $templateId = (int)$_POST['template_id'];
    $params = $_POST['params'] ?? [];

    $template = $templateModel->findById($templateId);
    if ($template && $template['user_id'] === $user['user_id']) {
        try {
            $title = strtr($template['title_template'], $params);
            $body = strtr($template['body_template'], $params);

            $githubToken = $project['github_token'] ?? null;
            if (!$githubToken) {
                throw new Exception("GitHub token not found for this project.");
            }

            $labels = isset($_POST['add_jules_label']) ? ['Jules'] : [];

            $githubService = new GitHubService(null, $githubToken);
            $githubService->createIssue($project['github_repo'], $title, $body, $labels);

            header("Location: project.php?id=$projectId&success=issue_created");
            exit;
        } catch (Exception $e) {
            $errorMessage = "Error creating issue: " . $e->getMessage();
        }
    }
}

// Handle Sync Issues
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_issues'])) {
    if (!$auth->validateCsrfToken($_POST['csrf_token'] ?? null)) {
        die("CSRF token validation failed.");
    }

    try {
        $githubToken = $project['github_token'] ?? null;
        if (!$githubToken) {
            throw new Exception("GitHub token not found for this project.");
        }

        $githubService = new GitHubService(null, $githubToken);
        $taskModel->syncIssues($user['user_id'], $project['project_id'], $project['github_repo'], $githubService);

        header("Location: project.php?id=$projectId&success=synced");
        exit;
    } catch (Exception $e) {
        $errorMessage = "Error syncing issues: " . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Details - Agent Control</title>
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
                                    <a href="https://github.com/<?= htmlspecialchars($project['github_repo']) ?>" target="_blank" rel="noopener noreferrer" class="text-gray-400 hover:text-gray-900 ml-1 md:ml-2 font-medium hover:underline">
                                        <?= htmlspecialchars($project['github_repo']) ?>
                                    </a>
                                </div>
                            </li>
                        </ol>
                    </nav>

                    <div class="mb-4">
                        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">
                            <a href="https://github.com/<?= htmlspecialchars($project['github_repo']) ?>" target="_blank" rel="noopener noreferrer" class="hover:underline">
                                <?= htmlspecialchars($project['github_repo']) ?>
                            </a>
                        </h1>
                    </div>

                    <?php if ($errorMessage): ?>
                        <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50" role="alert">
                            <span class="font-medium">Error!</span> <?= htmlspecialchars($errorMessage) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['success']) && $_GET['success'] === 'issue_created'): ?>
                        <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50" role="alert">
                            <span class="font-medium">Success!</span> Issue created from template. It may take a few seconds to appear in the list (synced via webhook).
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['success']) && $_GET['success'] === 'synced'): ?>
                        <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50" role="alert">
                            <span class="font-medium">Success!</span> Issues synced from GitHub.
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

                    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 mb-4">
                        <div class="lg:col-span-1 space-y-4">
                            <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                                <h3 class="text-lg font-bold text-gray-900 mb-4">Project Overview</h3>
                                <?php if (empty($roadmapFiles)): ?>
                                    <p class="text-sm text-gray-500 italic">No roadmap files found in the repository.</p>
                                <?php else: ?>
                                    <ul class="space-y-2">
                                        <?php foreach ($roadmapFiles as $file): ?>
                                            <li class="flex flex-col">
                                                <a href="<?= htmlspecialchars($file['html_url']) ?>" target="_blank" rel="noopener noreferrer" class="text-sm text-blue-600 hover:underline flex items-center">
                                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                                    <?= htmlspecialchars($file['name']) ?>
                                                </a>
                                                <?php if (!empty($file['next_task'])): ?>
                                                    <span class="text-[10px] text-gray-500 ml-6 italic">
                                                        🚧 <?= htmlspecialchars($file['next_task']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>

                            <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm" x-data="{
                                templates: <?= htmlspecialchars(json_encode($templates)) ?>,
                                selectedTemplateId: '<?= $templates[0]['issue_template_id'] ?? '' ?>',
                                get selectedTemplate() {
                                    return this.templates.find(t => t.issue_template_id == this.selectedTemplateId);
                                },
                                get placeholders() {
                                    if (!this.selectedTemplate) return [];
                                    const combined = this.selectedTemplate.title_template + ' ' + this.selectedTemplate.body_template;
                                    const matches = combined.match(/%\d+/g) || [];
                                    return [...new Set(matches)].sort((a, b) => parseInt(a.slice(1)) - parseInt(b.slice(1)));
                                }
                            }">
                                <h3 class="text-lg font-bold text-gray-900 mb-4">Create Issue from Template</h3>
                                <?php if (empty($templates)): ?>
                                    <p class="text-sm text-gray-500 italic">No templates available. <a href="templates.php" class="text-blue-600 hover:underline">Create one first.</a></p>
                                <?php else: ?>
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?= $auth->getCsrfToken() ?>">
                                        <div class="mb-4">
                                            <label class="block mb-2 text-sm font-medium text-gray-900">Select Template</label>
                                            <select name="template_id" x-model="selectedTemplateId" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                                                <?php foreach ($templates as $tmpl): ?>
                                                    <option value="<?= $tmpl['issue_template_id'] ?>"><?= htmlspecialchars($tmpl['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <template x-for="p in placeholders" :key="p">
                                            <div class="mb-4">
                                                <label class="block mb-2 text-sm font-medium text-gray-900" x-text="(selectedTemplate.parameter_config[p] || p) + ' value'"></label>
                                                <input type="text" :name="'params[' + p + ']'" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                            </div>
                                        </template>

                                        <div class="flex items-center mb-4">
                                            <input id="add_jules_label" name="add_jules_label" type="checkbox" value="1" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2" checked>
                                            <label for="add_jules_label" class="ms-2 text-sm font-medium text-gray-900">Add "Jules" label</label>
                                        </div>
                                        <button type="submit" name="create_from_template" class="text-white bg-green-600 hover:bg-green-700 focus:ring-4 focus:ring-green-300 font-medium rounded-lg text-sm px-5 py-2.5 w-full focus:outline-none">Create Issue</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="lg:col-span-3 p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-bold text-gray-900">Tasks synced from GitHub</h3>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= $auth->getCsrfToken() ?>">
                                    <button type="submit" name="sync_issues" class="text-white bg-blue-600 hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-xs px-3 py-2 focus:outline-none">Sync Issues</button>
                                </form>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm text-left text-gray-500">
                                    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3">Issue</th>
                                            <th scope="col" class="px-6 py-3">Status</th>
                                            <th scope="col" class="px-6 py-3">Logs</th>
                                            <th scope="col" class="px-6 py-3">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($tasks)): ?>
                                            <tr class="bg-white border-b">
                                                <td colspan="4" class="px-6 py-4 text-center">No tasks found. Open an issue on GitHub to see it here.</td>
                                            </tr>
                                        <?php endif; ?>
                                        <?php foreach ($tasks as $task): ?>
                                            <tr class="bg-white border-b">
                                                <td class="px-6 py-4">
                                                    <div class="text-base font-semibold text-gray-900">#<?= htmlspecialchars($task['issue_number']) ?> <?= htmlspecialchars($task['title']) ?></div>
                                                    <div class="text-xs text-gray-500"><?= htmlspecialchars(mb_substr($task['body'] ?? '', 0, 100)) ?>...</div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full <?= $task['status'] === 'completed' ? 'bg-green-100 text-green-800' : ($task['status'] === 'in_progress' ? 'bg-blue-100 text-blue-800' : ($task['status'] === 'failed' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800')) ?>">
                                                        <?php
                                                        if ($task['status'] === 'completed') echo '✅ ';
                                                        elseif ($task['status'] === 'in_progress') echo '🚧 ';
                                                        elseif ($task['status'] === 'failed') echo '❌ ';
                                                        else echo '⏳ ';
                                                        ?>
                                                        <?= htmlspecialchars($task['status']) ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="max-h-32 overflow-y-auto text-[10px] font-mono bg-gray-50 p-2 rounded border border-gray-100">
                                                        <?php
                                                        $taskLogs = $taskModel->getLogs($task['task_id']);
                                                        if (empty($taskLogs)): ?>
                                                            <span class="text-gray-400 italic">No logs available.</span>
                                                        <?php else: ?>
                                                            <?php foreach ($taskLogs as $log): ?>
                                                                <div class="mb-1">
                                                                    <span class="text-gray-400">[<?= htmlspecialchars(date('H:i:s', strtotime($log['created_at']))) ?>]</span>
                                                                    <span class="<?= $log['level'] === 'error' ? 'text-red-600 font-bold' : 'text-gray-700' ?>"><?= htmlspecialchars($log['message']) ?></span>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <form method="POST" class="inline">
                                                        <input type="hidden" name="csrf_token" value="<?= $auth->getCsrfToken() ?>">
                                                        <input type="hidden" name="task_id" value="<?= $task['task_id'] ?>">
                                                        <button type="submit" name="trigger_agent" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-xs px-3 py-2 focus:outline-none">Run Agent</button>
                                                    </form>
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
