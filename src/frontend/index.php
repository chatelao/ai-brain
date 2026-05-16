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
$projectModel = new Project($db);

$user = $auth->isLoggedIn() ? $userModel->findById($auth->getUserId()) : null;

// Handle Project Creation
if ($user && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['github_repo']) && isset($_POST['github_account_id'])) {
    if (!$auth->validateCsrfToken($_POST['csrf_token'] ?? null)) {
        die("CSRF token validation failed.");
    }
    $repo = trim($_POST['github_repo']);
    $accountId = (int)$_POST['github_account_id'];
    if (!empty($repo) && $accountId > 0) {
        try {
            $projectModel->create($user['user_id'], $accountId, $repo);
            header('Location: index.php?success=project_created');
            exit;
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
        }
    }
}

// Handle Project Deletion
if ($user && isset($_GET['delete_project'])) {
    if (!$auth->validateCsrfToken($_GET['csrf_token'] ?? null)) {
        die("CSRF token validation failed.");
    }
    $projectModel->delete((int)$_GET['delete_project'], $user['user_id']);
    header('Location: index.php?success=project_deleted');
    exit;
}

$projects = $user ? $projectModel->findByUserId($user['user_id']) : [];
$githubAccounts = $user ? $userModel->getGitHubAccounts($user['user_id']) : [];
$taskModel = new Task($db);
$autorepeatTasks = $user ? $taskModel->getRunningAutorepeatTasks($user['user_id']) : [];

$projectTasks = [];
if ($user) {
    $allTasks = $taskModel->findByUserProjects($user['user_id']);
    foreach ($allTasks as $task) {
        $projectTasks[$task['project_id']][] = $task;
    }
}

$errorMessage = $errorMessage ?? null;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Control Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        .status-square {
            width: 24px;
            height: 24px;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .status-square:hover {
            transform: scale(1.15);
            box-shadow: 0 0 8px rgba(0, 0, 0, 0.1);
            z-index: 10;
        }
        .status-square.green { background-color: #238636; }
        .status-square.yellow { background-color: #d29922; }
        .status-square.blue { background-color: #0366d6; }
        .status-square.red { background-color: #f85149; }
        .status-square.purple { background-color: #8957e5; }
        .status-square.grey { background-color: #8b949e; }
        .auto-repeat-tag {
            border: 2px solid #e82dce !important;
            box-sizing: border-box;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal">
    <nav class="bg-white border-b border-gray-200 fixed w-full z-30 top-0">
        <div class="px-3 py-3 lg:px-5 lg:pl-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center justify-start">
                    <span class="self-center text-xl font-semibold sm:text-2xl whitespace-nowrap">Agent Control</span>
                </div>
                <div class="flex items-center">
                    <?php if ($user): ?>
                        <div class="flex items-center ml-3">
                            <div>
                                <button type="button" class="flex text-sm bg-gray-800 rounded-full focus:ring-4 focus:ring-gray-300">
                                    <img class="w-8 h-8 rounded-full" src="<?= htmlspecialchars($user['avatar'] ?? 'https://www.gravatar.com/avatar/?d=mp') ?>" alt="user photo">
                                </button>
                            </div>
                            <div class="ml-3 text-sm font-medium text-gray-900"><?= htmlspecialchars($user['name']) ?></div>
                            <a href="templates.php" class="ml-4 text-sm font-medium text-blue-600 hover:underline">Templates</a>
                            <a href="settings.php" class="ml-4 text-sm font-medium text-gray-600 hover:underline">Settings</a>
                            <a href="logout.php" class="ml-4 text-sm font-medium text-red-600 hover:underline">Logout</a>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 mr-2 mb-2 focus:outline-none">Login with Google</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex pt-16 overflow-hidden bg-gray-50">
        <div id="main-content" class="relative w-full h-full overflow-y-auto bg-gray-50">
            <main>
                <div class="px-4 pt-6">
                    <?php if ($errorMessage): ?>
                        <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50" role="alert">
                            <span class="font-medium">Error!</span> <?= htmlspecialchars($errorMessage) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($user && !empty($autorepeatTasks)): ?>
                        <div class="mb-4 p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                            <h3 class="text-lg font-bold text-gray-900 mb-4">Running Autorepeat Tasks</h3>
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
                                        <?php foreach ($autorepeatTasks as $task): ?>
                                            <tr class="bg-white border-b">
                                                <td class="px-6 py-4 font-medium text-gray-900">
                                                    <a href="https://github.com/<?= htmlspecialchars($task['github_repo']) ?>" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">
                                                        <?= htmlspecialchars($task['github_repo']) ?>
                                                    </a>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <a href="project.php?id=<?= $task['project_id'] ?>" class="text-blue-600 hover:underline font-semibold">
                                                        #<?= htmlspecialchars($task['issue_number']) ?> <?= htmlspecialchars($task['title']) ?>
                                                    </a>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">
                                                        Active
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="grid w-full grid-cols-1 gap-4 mt-4">
                        <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm sm:p-6">
                            <h3 class="text-base font-normal text-gray-500">Welcome to Agent Control</h3>
                            <?php if ($user): ?>
                                <p class="text-2xl font-bold leading-none text-gray-900 sm:text-3xl">Dashboard</p>
                                <div class="mt-4">
                                    <p class="text-gray-600">You are logged in as <strong><?= htmlspecialchars($user['email']) ?></strong>.</p>

                                    <div class="mt-4">
                                        <p class="text-sm text-gray-500">To manage your Jules API Key, Telegram bot, or GitHub accounts, visit <a href="settings.php" class="text-blue-600 hover:underline">Account Settings</a>.</p>
                                    </div>
                                </div>

                                <div class="mt-8">
                                    <h4 class="text-xl font-bold text-gray-900 mb-4">Your Projects</h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                        <?php foreach ($projects as $project): ?>
                                            <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                                                <div class="flex justify-between items-start">
                                                    <h5 class="text-lg font-bold text-gray-900 truncate">
                                                        <a href="https://github.com/<?= htmlspecialchars($project['github_repo']) ?>" target="_blank" rel="noopener noreferrer" class="hover:underline">
                                                            <?= htmlspecialchars($project['github_repo']) ?>
                                                        </a>
                                                    </h5>
                                                    <a href="?delete_project=<?= $project['project_id'] ?>&csrf_token=<?= $auth->getCsrfToken() ?>" class="text-red-600 hover:text-red-800" onclick="return confirm('Are you sure?')">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                    </a>
                                                </div>
                                                <p class="text-sm text-gray-500 mt-1">Linked as&nbsp;<?= htmlspecialchars($project['github_username']) ?></p>

                                                <div class="flex flex-wrap gap-1 mt-3">
                                                    <?php
                                                    $tasks = $projectTasks[$project['project_id']] ?? [];
                                                    foreach ($tasks as $task):
                                                        $color = $taskModel->getStatusColor($task);
                                                        $isAutorepeat = $taskModel->hasAutorepeatLabel($task);
                                                    ?>
                                                        <div class="relative group">
                                                            <a href="project.php?id=<?= $project['project_id'] ?>"
                                                               class="status-square <?= $color ?> <?= $isAutorepeat ? 'auto-repeat-tag' : '' ?>">
                                                            </a>
                                                            <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-[10px] rounded opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none whitespace-nowrap z-50 shadow-lg">
                                                                #<?= htmlspecialchars($task['issue_number']) ?>: <?= htmlspecialchars(mb_substr($task['title'], 0, 30)) ?><?= mb_strlen($task['title']) > 30 ? '...' : '' ?>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>

                                                <div class="mt-4">
                                                    <a href="project.php?id=<?= $project['project_id'] ?>" class="text-blue-600 hover:underline text-sm font-medium">View Project Details &rarr;</a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>

                                        <!-- Add Project Card -->
                                        <div class="p-4 bg-gray-50 border-2 border-dashed border-gray-300 rounded-lg flex flex-col items-center justify-center">
                                            <?php if (empty($githubAccounts)): ?>
                                                <p class="text-sm text-gray-500 text-center">Please link a GitHub account first.</p>
                                            <?php else: ?>
                                                <form method="POST" class="w-full">
                                                    <input type="hidden" name="csrf_token" value="<?= $auth->getCsrfToken() ?>">
                                                    <div class="mb-2">
                                                        <label class="block mb-1 text-xs font-medium text-gray-900">GitHub Account</label>
                                                        <select name="github_account_id" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                                                            <?php foreach ($githubAccounts as $account): ?>
                                                                <option value="<?= $account['github_account_id'] ?>"><?= htmlspecialchars($account['github_username']) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="mb-2">
                                                        <label class="block mb-1 text-xs font-medium text-gray-900">Repository (owner/repo)</label>
                                                        <input type="text" name="github_repo" placeholder="owner/repo" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                                                    </div>
                                                    <button type="submit" class="w-full text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 focus:outline-none">Link New Repository</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <p class="text-2xl font-bold leading-none text-gray-900 sm:text-3xl">Please Login</p>
                                <div class="mt-4">
                                    <p class="text-gray-600">Manage your Google Jules agents and GitHub issues seamlessly.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
