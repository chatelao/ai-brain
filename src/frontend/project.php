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
use App\NotificationService;

$auth = new Auth();
$db = new Database();
$userModel = new User($db);
$projectModel = new Project($db);
$taskModel = new Task($db);
$telegramService = new TelegramService();
$notificationService = new NotificationService($db);
$logger = new Logger($db);

if (!$auth->isLoggedIn()) {
    header('Location: google/login.php');
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


$templateModel = new IssueTemplate($db);
$templates = $templateModel->findByUserId($user['user_id']);

$showAll = isset($_GET['all']) && $_GET['all'] == '1';

// Initialize Markdown parser
if (!class_exists('\Parsedown')) {
    die("Error: Class 'Parsedown' not found. Please run 'composer install' to install dependencies.");
}
$parsedown = new \Parsedown();
$parsedown->setSafeMode(true);

$tasks = $taskModel->findByProjectId($projectId, $showAll);
$lastAgentResponse = null;
$errorMessage = null;

$triggerAgent = function ($taskId) use ($taskModel, $logger, $user, $project, $julesService, $notificationService, &$lastAgentResponse, &$errorMessage, &$tasks, $projectId, $showAll) {
    $task = $taskModel->findById($taskId);
    if ($task && $task['project_id'] === $project['project_id']) {
        try {
            $logger->log($user['user_id'], $taskId, "Agent triggered by user " . $user['name']);
            $githubToken = $project['github_token'] ?? null;
            $githubService = null;
            if ($githubToken) {
                $githubService = new GitHubService(null, $githubToken);
            }

            // Update status to executing
            $taskModel->updateStatus($taskId, App\Task::STATUS_EXECUTING);
            $logger->log($user['user_id'], $taskId, "Task status updated to executing");

            if ($githubService) {
                $githubService->postComment($project['github_repo'], $task['issue_number'], "🤖 Agent has started processing this issue...");
                $logger->log($user['user_id'], $taskId, "Posted 'started' comment to GitHub");
            }

            $notificationService->notify($user['user_id'], 'agent_event', "🤖 Agent Started: #" . $task['issue_number'], "Agent started processing \"" . $task['title'] . "\" in " . $project['github_repo'], [
                'task_id' => $taskId,
                'project_id' => $project['project_id'],
                'source_url' => $taskModel->getTargetUrl($task),
                'is_system' => false // Human action
            ]);

            $logger->log($user['user_id'], $taskId, "Calling Jules API...");
            $lastAgentResponse = $julesService->triggerAgent($task);
            $logger->log($user['user_id'], $taskId, "Received response from Jules API");

            $taskModel->updateAgentResponse($taskId, $lastAgentResponse, App\Task::STATUS_ANALYZING);
            $logger->log($user['user_id'], $taskId, "Task agent response updated and status set to analyzing");

            if ($githubService) {
                $githubService->postComment($project['github_repo'], $task['issue_number'], "✅ Agent has completed the analysis:\n\n" . $lastAgentResponse);
                $logger->log($user['user_id'], $taskId, "Posted 'completed' comment to GitHub");
            }

            $notificationService->notify($user['user_id'], 'agent_event', "✅ Agent Completed: #" . $task['issue_number'], "Agent completed analysis for \"" . $task['title'] . "\" in " . $project['github_repo'], [
                'task_id' => $taskId,
                'project_id' => $project['project_id'],
                'source_url' => $taskModel->getTargetUrl($task),
                'is_system' => true // The completion itself is system-driven
            ]);

            // Refresh tasks
            $tasks = $taskModel->findByProjectId($projectId, $showAll);
        } catch (\Exception $e) {
            $errorMessage = "Error triggering agent: " . $e->getMessage();
            $logger->log($user['user_id'], $taskId, "Error: " . $e->getMessage(), "error");
            $taskModel->updateStatus($taskId, App\Task::STATUS_FAILED_JULES);
            if (isset($githubService) && $githubService) {
                try {
                    $githubService->postComment($project['github_repo'], $task['issue_number'], "❌ Agent failed to process this issue: " . $e->getMessage());
                    $logger->log($user['user_id'], $taskId, "Posted 'failed' comment to GitHub");
                } catch (\Exception $ge) {
                    $logger->log($user['user_id'], $taskId, "Failed to post error comment to GitHub: " . $ge->getMessage(), "error");
                }
            }

            $notificationService->notify($user['user_id'], 'agent_event', "❌ Agent Failed: #" . $task['issue_number'], "Agent failed processing \"" . $task['title'] . "\": " . $e->getMessage(), [
                'task_id' => $taskId,
                'project_id' => $project['project_id'],
                'source_url' => $taskModel->getTargetUrl($task),
                'is_system' => true // The failure itself is system-driven
            ]);
        }
    }
};

$githubToken = $project['github_token'] ?? null;
$roadmapFiles = [];

$roadmapCacheTimeout = 3600; // 1 hour
$roadmapUpdatedAt = strtotime($project['roadmap_updated_at'] ?? '2000-01-01');
$isRoadmapCacheValid = (time() - $roadmapUpdatedAt) < $roadmapCacheTimeout;

if ($isRoadmapCacheValid && !empty($project['roadmap_data'])) {
    $roadmapFiles = json_decode($project['roadmap_data'], true);
} elseif ($githubToken) {
    try {
        $githubService = new GitHubService(null, $githubToken);
        $roadmapFiles = $githubService->getRoadmapFiles($project['github_repo']);
        $projectModel->updateRoadmapCache($projectId, $roadmapFiles);
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
    $triggerAgent($taskId);
}

// Handle Rerun Task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rerun_task'])) {
    if (!$auth->validateCsrfToken($_POST['csrf_token'] ?? null)) {
        die("CSRF token validation failed.");
    }

    $taskId = (int)$_POST['task_id'];
    $originalTask = $taskModel->findById($taskId);

    if ($originalTask && $originalTask['project_id'] === $project['project_id']) {
        try {
            $githubToken = $project['github_token'] ?? null;
            if (!$githubToken) {
                throw new Exception("GitHub token not found for this project.");
            }

            $githubData = json_decode($originalTask['github_data'] ?? '{}', true);
            $labels = array_map(fn($l) => $l['name'], $githubData['labels'] ?? []);

            $githubService = new GitHubService(null, $githubToken);
            $newIssue = $githubService->createIssue($project['github_repo'], $originalTask['title'], $originalTask['body'], $labels);

            $taskModel->upsert($user['user_id'], $project['project_id'], $newIssue);
            $newTask = $taskModel->findByIssueNumber($project['project_id'], $newIssue['number']);

            if ($newTask) {
                $triggerAgent($newTask['task_id']);
            } else {
                throw new Exception("Failed to find the newly created task in the database.");
            }
        } catch (Exception $e) {
            $errorMessage = "Error rerunning task: " . $e->getMessage();
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

// Handle Merge & Close
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['merge_close']) || isset($_POST['merge_close_duplicate']))) {
    if (!$auth->validateCsrfToken($_POST['csrf_token'] ?? null)) {
        die("CSRF token validation failed.");
    }

    $taskId = (int)$_POST['task_id'];
    $task = $taskModel->findById($taskId);

    if ($task && $task['project_id'] === $project['project_id']) {
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
            header("Location: project.php?id=$projectId&success=$successAction");
            exit;
        } catch (Exception $e) {
            $errorMessage = "Error merging/closing: " . $e->getMessage();
        }
    }
}

// Handle Create Issue from Roadmap
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_from_roadmap'])) {
    if (!$auth->validateCsrfToken($_POST['csrf_token'] ?? null)) {
        die("CSRF token validation failed.");
    }

    $roadmapName = $_POST['roadmap_name'] ?? '';

    if (!empty($roadmapName)) {
        try {
            $githubToken = $project['github_token'] ?? null;
            if (!$githubToken) {
                throw new Exception("GitHub token not found for this project.");
            }

            $title = "Implement one or more of the next, modest, unsolved, feasible and reasonable steps of \"$roadmapName\"";
            $body = "If none is available, alternativly break down bigger steps to modest ones without implementing anything, just changing the $roadmapName.";

            $githubService = new GitHubService(null, $githubToken);
            $githubService->createIssue($project['github_repo'], $title, $body, ['Jules']);

            header("Location: project.php?id=$projectId&success=issue_created");
            exit;
        } catch (Exception $e) {
            $errorMessage = "Error creating issue from roadmap: " . $e->getMessage();
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

        // Also refresh roadmap cache during sync
        $roadmapFiles = $githubService->getRoadmapFiles($project['github_repo']);
        $projectModel->updateRoadmapCache($projectId, $roadmapFiles);

        header("Location: project.php?id=$projectId&success=synced");
        exit;
    } catch (Exception $e) {
        header("Location: project.php?id=$projectId&error=" . urlencode("Error syncing issues: " . $e->getMessage()));
        exit;
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
                                    <a href="https://github.com/<?= htmlspecialchars($project['github_repo'] ?? '') ?>" target="_blank" rel="noopener noreferrer" class="text-gray-400 hover:text-gray-900 ml-1 md:ml-2 font-medium hover:underline">
                                        <?= htmlspecialchars($project['github_repo'] ?? '') ?>
                                    </a>
                                </div>
                            </li>
                        </ol>
                    </nav>

                    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6 space-y-4 md:space-y-0 md:space-x-4">
                        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">
                            <a href="https://github.com/<?= htmlspecialchars($project['github_repo'] ?? '') ?>" target="_blank" rel="noopener noreferrer" class="hover:underline">
                                <?= htmlspecialchars($project['github_repo'] ?? '') ?>
                            </a>
                        </h1>
                        <div class="flex items-center space-x-2">
                            <form method="POST" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= $auth->getCsrfToken() ?>">
                                <button type="submit" name="sync_issues" class="text-gray-500 hover:text-gray-900 focus:outline-none" title="Sync Issues">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                </button>
                            </form>
                            <a href="project-settings.php?id=<?= $projectId ?>" class="text-gray-500 hover:text-gray-900 focus:outline-none" title="Project Settings">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            </a>
                        </div>
                    </div>

                    <?php if ($errorMessage) : ?>
                        <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50" role="alert">
                            <span class="font-medium">Error!</span> <?= htmlspecialchars($errorMessage ?? '') ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['success']) && $_GET['success'] === 'issue_created') : ?>
                        <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50" role="alert">
                            <span class="font-medium">Success!</span> Issue created from template. It may take a few seconds to appear in the list (synced via webhook).
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




                    <?php if ($lastAgentResponse) : ?>
                        <div class="p-4 mb-4 text-sm text-blue-800 rounded-lg bg-blue-50" role="alert">
                            <span class="font-medium">Agent Response:</span>
                            <div class="mt-2 p-2 bg-white rounded border border-blue-200 whitespace-pre-wrap font-mono text-xs">
                                <?= htmlspecialchars($lastAgentResponse ?? '') ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 mb-4">
                        <div class="lg:col-span-1 order-1 lg:order-2 space-y-4">
                            <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                                <h3 class="text-lg font-bold text-gray-900 mb-4">Project Overview</h3>
                                <?php if (empty($roadmapFiles)) : ?>
                                    <p class="text-sm text-gray-500 italic">No roadmap files found in the repository.</p>
                                <?php else : ?>
                                    <ul class="space-y-2">
                                        <?php foreach ($roadmapFiles as $file) : ?>
                                            <li class="flex flex-col">
                                                <div class="flex items-center justify-between">
                                                    <a href="<?= htmlspecialchars($file['html_url'] ?? '') ?>" target="_blank" rel="noopener noreferrer" class="text-sm text-blue-600 hover:underline flex items-center">
                                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                                        <?= htmlspecialchars($file['name'] ?? '') ?>
                                                    </a>
                                                    <?php if (!empty($file['next_task'])) : ?>
                                                        <form method="POST" class="inline">
                                                            <input type="hidden" name="csrf_token" value="<?= $auth->getCsrfToken() ?>">
                                                            <input type="hidden" name="roadmap_name" value="<?= htmlspecialchars($file['name'] ?? '') ?>">
                                                            <button type="submit" name="create_from_roadmap" class="text-green-600 hover:text-green-800 focus:outline-none" title="Implement next step">
                                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M4.5 2.691l11 6.309-11 6.309V2.691z"/></svg>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (!empty($file['next_task'])) : ?>
                                                    <span class="text-[10px] text-gray-500 ml-6 italic whitespace-nowrap">
                                                        🚧 <?= htmlspecialchars($file['next_task'] ?? '') ?>
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
                                <?php if (empty($templates)) : ?>
                                    <p class="text-sm text-gray-500 italic">No templates available. <a href="templates.php" class="text-blue-600 hover:underline">Create one first.</a></p>
                                <?php else : ?>
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?= $auth->getCsrfToken() ?>">
                                        <div class="mb-4">
                                            <label class="block mb-2 text-sm font-medium text-gray-900">Select Template</label>
                                            <select name="template_id" x-model="selectedTemplateId" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                                                <?php foreach ($templates as $tmpl) : ?>
                                                    <option value="<?= $tmpl['issue_template_id'] ?>"><?= htmlspecialchars($tmpl['name'] ?? '') ?></option>
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

                        <div class="lg:col-span-3 order-2 lg:order-1 p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm text-left text-gray-500">
                                    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3">Issue</th>
                                            <th scope="col" class="px-6 py-3">Status</th>
                                            <th scope="col" class="px-6 py-3">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($tasks)) : ?>
                                            <tr class="bg-white border-b">
                                                <td colspan="3" class="px-6 py-4 text-center">No tasks found. Open an issue on GitHub to see it here.</td>
                                            </tr>
                                        <?php endif; ?>
                                        <?php foreach ($tasks as $task) : ?>
                                            <tr class="bg-white border-b">
                                                <td class="px-6 py-4">
                                                    <div class="text-base text-gray-900 font-normal markdown-body">
                                                        <a href="task.php?id=<?= $task['task_id'] ?>" class="hover:underline">
                                                            <?= htmlspecialchars($task['issue_number'] ?? '') ?> - <?= htmlspecialchars($task['title'] ?? '') ?>
                                                        </a>
                                                    </div>
                                                    <div class="text-xs text-gray-500 markdown-body">
                                                        <?= $parsedown->text($taskModel->processGitHubImages(mb_substr($task['body'] ?? '', 0, 100) . (mb_strlen($task['body'] ?? '') > 100 ? '...' : ''))) ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <?php
                                                    $statusColor = $taskModel->getStatusColor($task);
                                                    $bgClass = 'bg-gray-100 text-gray-800';
                                                    if ($statusColor === 'gray') {
                                                        $bgClass = 'bg-gray-100 text-gray-800';
                                                    } elseif ($statusColor === 'green') {
                                                        $bgClass = 'bg-green-100 text-green-800';
                                                    } elseif ($statusColor === 'yellow') {
                                                        $bgClass = 'bg-yellow-100 text-yellow-800';
                                                    } elseif ($statusColor === 'blue') {
                                                        $bgClass = 'bg-blue-100 text-blue-800';
                                                    } elseif ($statusColor === 'red') {
                                                        $bgClass = 'bg-red-100 text-red-800';
                                                    } elseif ($statusColor === 'purple') {
                                                        $bgClass = 'bg-purple-100 text-purple-800';
                                                    } elseif ($statusColor === 'orange') {
                                                        $bgClass = 'bg-orange-100 text-orange-800';
                                                    }
                                                    ?>
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full whitespace-nowrap <?= $bgClass ?>">
                                                        <?= $taskModel->getStatusEmoji($task['status'] ?? '') ?>
                                                        <?= htmlspecialchars($taskModel->getStatusLabel($task['status'] ?? '')) ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="flex flex-col space-y-2">
                                                        <?php
                                                        $isClosed = $task['status'] === App\Task::STATUS_FINISHED;
                                                        $isReady = $task['status'] === App\Task::STATUS_READY;
                                                        $isImplemented = $task['status'] === App\Task::STATUS_IMPLEMENTED;
                                                        ?>
                                                        <?php if (!$isClosed && !$isReady && !$isImplemented) : ?>
                                                            <form method="POST" class="inline">
                                                                <input type="hidden" name="csrf_token" value="<?= $auth->getCsrfToken() ?>">
                                                                <input type="hidden" name="task_id" value="<?= $task['task_id'] ?>">
                                                                <button type="submit" name="trigger_agent" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-xs px-3 py-2 focus:outline-none w-full">Run Agent</button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <?php if ($isReady || $isImplemented) : ?>
                                                            <form method="POST" class="inline">
                                                                <input type="hidden" name="csrf_token" value="<?= $auth->getCsrfToken() ?>">
                                                                <input type="hidden" name="task_id" value="<?= $task['task_id'] ?>">
                                                                <button type="submit" name="rerun_task" class="text-white bg-indigo-600 hover:bg-indigo-700 focus:ring-4 focus:ring-indigo-300 font-medium rounded-lg text-xs px-3 py-2 focus:outline-none w-full">Rerun</button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <?php
                                                        $prData = json_decode($task['github_pr_data'] ?? '{}', true);
                                                        $isMergeable = (!$isClosed && !empty($task['pr_url']) && ($prData['state'] ?? '') === 'open' && ($prData['mergeable_state'] ?? '') === 'clean' && !($prData['draft'] ?? false));
                                                        if ($isMergeable) : ?>
                                                            <form method="POST" class="inline">
                                                                <input type="hidden" name="csrf_token" value="<?= $auth->getCsrfToken() ?>">
                                                                <input type="hidden" name="task_id" value="<?= $task['task_id'] ?>">
                                                                <button type="submit" name="merge_close" class="text-white bg-purple-600 hover:bg-purple-700 focus:ring-4 focus:ring-purple-300 font-medium rounded-lg text-xs px-3 py-2 focus:outline-none w-full mb-2" onclick="return confirm('Are you sure you want to merge this PR and close the issue?')">Merge & Close</button>
                                                            </form>
                                                            <form method="POST" class="inline">
                                                                <input type="hidden" name="csrf_token" value="<?= $auth->getCsrfToken() ?>">
                                                                <input type="hidden" name="task_id" value="<?= $task['task_id'] ?>">
                                                                <button type="submit" name="merge_close_duplicate" class="text-white bg-indigo-600 hover:bg-indigo-700 focus:ring-4 focus:ring-indigo-300 font-medium rounded-lg text-xs px-3 py-2 focus:outline-none w-full" onclick="return confirm('Are you sure you want to merge this PR, close the issue and create a duplicate?')">Merge, Close & Duplicate</button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
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
