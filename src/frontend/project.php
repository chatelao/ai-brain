<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;
use App\Auth;
use App\User;
use App\Project;
use App\Task;
use App\JulesService;
use App\GitHubService;

$auth = new Auth();
$db = new Database();
$userModel = new User($db);
$projectModel = new Project($db);
$taskModel = new Task($db);
$julesService = new JulesService();

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = $userModel->findById($auth->getUserId());
$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$project = $projectModel->findById($projectId);

if (!$project || $project['user_id'] !== $user['id']) {
    die("Project not found or access denied.");
}

$tasks = $taskModel->findByProjectId($projectId);
$lastAgentResponse = null;
$errorMessage = null;

// Handle Agent Trigger
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['trigger_agent'])) {
    if (!$auth->validateCsrfToken($_POST['csrf_token'] ?? null)) {
        die("CSRF token validation failed.");
    }

    $taskId = (int)$_POST['task_id'];
    $task = $taskModel->findById($taskId);

    if ($task && $task['project_id'] === $project['id']) {
        try {
            $githubToken = $user['github_token'] ?? null;
            $githubService = null;
            if ($githubToken) {
                $githubService = new GitHubService(null, $githubToken);
            }

            // Update status to in_progress
            $taskModel->updateStatus($taskId, 'in_progress');

            if ($githubService) {
                $githubService->postComment($project['github_repo'], $task['issue_number'], "🤖 Agent has started processing this issue...");
            }

            $lastAgentResponse = $julesService->triggerAgent($task);

            $taskModel->updateAgentResponse($taskId, $lastAgentResponse, 'completed');

            if ($githubService) {
                $githubService->postComment($project['github_repo'], $task['issue_number'], "✅ Agent has completed the analysis:\n\n" . $lastAgentResponse);
            }

            // Refresh tasks
            $tasks = $taskModel->findByProjectId($projectId);
        } catch (\Exception $e) {
            $errorMessage = "Error triggering agent: " . $e->getMessage();
            $taskModel->updateStatus($taskId, 'failed');
            if (isset($githubService) && $githubService) {
                try {
                    $githubService->postComment($project['github_repo'], $task['issue_number'], "❌ Agent failed to process this issue: " . $e->getMessage());
                } catch (\Exception $ge) {
                    // Ignore GitHub commenting errors on top of main error
                }
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
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
                    <div class="flex items-center ml-3">
                        <img class="w-8 h-8 rounded-full" src="<?= htmlspecialchars($user['avatar'] ?? 'https://www.gravatar.com/avatar/?d=mp') ?>" alt="user photo">
                        <div class="ml-3 text-sm font-medium text-gray-900"><?= htmlspecialchars($user['name']) ?></div>
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
                                    <span class="text-gray-400 ml-1 md:ml-2 font-medium"><?= htmlspecialchars($project['github_repo']) ?></span>
                                </div>
                            </li>
                        </ol>
                    </nav>

                    <div class="mb-4">
                        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl"><?= htmlspecialchars($project['github_repo']) ?></h1>
                    </div>

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

                    <div class="grid grid-cols-1 gap-4 mb-4">
                        <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                            <h3 class="text-lg font-bold text-gray-900 mb-4">Tasks synced from GitHub</h3>
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
                                        <?php if (empty($tasks)): ?>
                                            <tr class="bg-white border-b">
                                                <td colspan="3" class="px-6 py-4 text-center">No tasks found. Open an issue on GitHub to see it here.</td>
                                            </tr>
                                        <?php endif; ?>
                                        <?php foreach ($tasks as $task): ?>
                                            <tr class="bg-white border-b">
                                                <td class="px-6 py-4">
                                                    <div class="text-base font-semibold text-gray-900">#<?= htmlspecialchars($task['issue_number']) ?> <?= htmlspecialchars($task['title']) ?></div>
                                                    <div class="text-xs text-gray-500"><?= htmlspecialchars(mb_substr($task['body'] ?? '', 0, 100)) ?>...</div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full <?= $task['status'] === 'completed' ? 'bg-green-100 text-green-800' : ($task['status'] === 'in_progress' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800') ?>">
                                                        <?= htmlspecialchars($task['status']) ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <form method="POST" class="inline">
                                                        <input type="hidden" name="csrf_token" value="<?= $auth->getCsrfToken() ?>">
                                                        <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
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
