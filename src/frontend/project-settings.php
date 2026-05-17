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
    header('Location: login.php');
    exit;
}

$user = $userModel->findById($auth->getUserId());
$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$project = $projectModel->findById($projectId);

if (!$project || $project['user_id'] !== $user['user_id']) {
    die("Project not found or access denied.");
}

$githubAccounts = $userModel->getGitHubAccounts($user['user_id']);
$errorMessage = null;
$successMessage = null;

// Handle Project Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_project'])) {
    if (!$auth->validateCsrfToken($_POST['csrf_token'] ?? null)) {
        die("CSRF token validation failed.");
    }

    $repo = trim($_POST['github_repo']);
    $accountId = (int)$_POST['github_account_id'];

    if (!empty($repo) && $accountId > 0) {
        try {
            $projectModel->update($projectId, $user['user_id'], $accountId, $repo);
            header("Location: project-settings.php?id=$projectId&success=project_updated");
            exit;
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
        }
    } else {
        $errorMessage = "Repository and GitHub Account are required.";
    }
}

// Handle Notification Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_notifications'])) {
    if (!$auth->validateCsrfToken($_POST['csrf_token'] ?? null)) {
        die("CSRF token validation failed.");
    }
    $settings = [
        'github_issue' => isset($_POST['notify_github_issue']),
        'task_status' => isset($_POST['notify_task_status']),
        'agent_event' => isset($_POST['notify_agent_event'])
    ];

    $statusSettings = [
        'researching' => isset($_POST['broadcast_researching']),
        'planning' => isset($_POST['broadcast_planning']),
        'coding' => isset($_POST['broadcast_coding']),
        'testing' => isset($_POST['broadcast_testing']),
        'in_progress' => isset($_POST['broadcast_in_progress']),
        'implemented' => isset($_POST['broadcast_implemented']),
        'completed' => isset($_POST['broadcast_completed']),
        'failed_jules' => isset($_POST['broadcast_failed_jules']),
        'failed_pr' => isset($_POST['broadcast_failed_pr'])
    ];

    if ($notificationService->updateProjectSettings($projectId, $settings) &&
        $notificationService->updateStatusSettings($projectId, $statusSettings)) {
        $redirectUrl = basename($_SERVER['PHP_SELF']) . "?id=$projectId&success=notifications_updated";
        header("Location: $redirectUrl");
        exit;
    } else {
        $errorMessage = "Failed to update notification settings.";
    }
}

// Handle Setup Webhook (copied from project.php)
$webhookUrl = ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'https') . '://' . $_SERVER['HTTP_HOST'] . '/webhook.php?project_id=' . $projectId;
$githubToken = $project['github_token'] ?? null;
$webhookStatus = 'unknown';

if ($githubToken) {
    try {
        $githubService = new GitHubService(null, $githubToken);
        $webhooks = $githubService->listWebhooks($project['github_repo']);
        $webhookStatus = 'missing';
        foreach ($webhooks as $wh) {
            if (isset($wh['config']['url']) && str_starts_with($wh['config']['url'], ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'https') . '://' . $_SERVER['HTTP_HOST'] . '/webhook.php')) {
                if ($wh['config']['url'] === $webhookUrl || $wh['config']['url'] === ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'https') . '://' . $_SERVER['HTTP_HOST'] . '/webhook.php') {
                    $webhookStatus = $wh['active'] ? 'active' : 'inactive';
                    break;
                }
            }
        }
    } catch (Exception $e) {
        $webhookStatus = 'error';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_webhook'])) {
    if (!$auth->validateCsrfToken($_POST['csrf_token'] ?? null)) {
        die("CSRF token validation failed.");
    }

    try {
        if (!$githubToken) {
            throw new Exception("GitHub token not found for this project.");
        }

        $githubService = new GitHubService(null, $githubToken);
        $githubService->createWebhook($project['github_repo'], $webhookUrl, $project['webhook_secret']);

        header("Location: project-settings.php?id=$projectId&success=webhook_setup");
        exit;
    } catch (Exception $e) {
        $errorMessage = "Error setting up webhook: " . $e->getMessage();
    }
}

// Handle Project Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_project'])) {
    if (!$auth->validateCsrfToken($_POST['csrf_token'] ?? null)) {
        die("CSRF token validation failed.");
    }

    if ($projectModel->delete($projectId, $user['user_id'])) {
        header('Location: index.php?success=project_deleted');
        exit;
    } else {
        $errorMessage = "Failed to delete project.";
    }
}

if (isset($_GET['success'])) {
    if ($_GET['success'] === 'project_updated') $successMessage = "Project settings updated successfully.";
    if ($_GET['success'] === 'webhook_setup') $successMessage = "Webhook set up successfully.";
    if ($_GET['success'] === 'notifications_updated') $successMessage = "Notification settings updated successfully.";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Settings - Agent Control</title>
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
                                    <a href="project.php?id=<?= $projectId ?>" class="text-gray-700 hover:text-gray-900 ml-1 md:ml-2 font-medium">
                                        <?= htmlspecialchars($project['github_repo'] ?? '') ?>
                                    </a>
                                </div>
                            </li>
                            <li>
                                <div class="flex items-center">
                                    <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>
                                    <span class="text-gray-400 ml-1 md:ml-2 font-medium">Settings</span>
                                </div>
                            </li>
                        </ol>
                    </nav>

                    <div class="mb-4">
                        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Project Settings</h1>
                    </div>

                    <?php if ($errorMessage): ?>
                        <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50" role="alert">
                            <span class="font-medium">Error!</span> <?= htmlspecialchars($errorMessage) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($successMessage): ?>
                        <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50" role="alert">
                            <span class="font-medium">Success!</span> <?= htmlspecialchars($successMessage) ?>
                        </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 gap-4">
                        <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                            <h3 class="text-lg font-bold text-gray-900 mb-4">General Settings</h3>
                            <form method="POST" x-data="{
                                repo: '<?= htmlspecialchars($project['github_repo'] ?? '') ?>',
                                selectedAccountId: '<?= $project['github_account_id'] ?>',
                                accounts: <?= htmlspecialchars(json_encode($githubAccounts)) ?>,
                                get recommendedId() {
                                    const owner = this.repo.split('/')[0]?.trim();
                                    if (!owner) return null;
                                    const match = this.accounts.find(a => a.github_username.toLowerCase() === owner.toLowerCase());
                                    return match ? match.github_account_id : null;
                                },
                                init() {
                                    this.$watch('repo', (value) => {
                                        const recId = this.recommendedId;
                                        if (recId) {
                                            this.selectedAccountId = recId;
                                        }
                                    });
                                }
                            }">
                                <input type="hidden" name="csrf_token" value="<?= $auth->getCsrfToken() ?>">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block mb-2 text-sm font-medium text-gray-900">GitHub Account</label>
                                        <select name="github_account_id" x-model="selectedAccountId" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                                            <?php foreach ($githubAccounts as $account): ?>
                                                <option value="<?= $account['github_account_id'] ?>" x-text="'<?= htmlspecialchars($account['github_username'] ?? '') ?>' + (recommendedId == <?= $account['github_account_id'] ?> ? ' (Recommended)' : '')">
                                                    <?= htmlspecialchars($account['github_username'] ?? '') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block mb-2 text-sm font-medium text-gray-900">Repository (owner/repo)</label>
                                        <input type="text" name="github_repo" x-model="repo" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                                    </div>
                                </div>
                                <button type="submit" name="update_project" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 focus:outline-none">Save Changes</button>
                            </form>
                        </div>

                        <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                            <h3 class="text-lg font-bold text-gray-900 mb-4">Notification Preferences</h3>
                            <?php
                            $projectNotifSettings = $notificationService->getProjectSettings($projectId);
                            $statusNotifSettings = $notificationService->getStatusSettings($projectId);
                            ?>
                            <form method="POST" class="space-y-6">
                                <input type="hidden" name="csrf_token" value="<?= $auth->getCsrfToken() ?>">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                        <span class="text-sm font-medium text-gray-700">GitHub Issues</span>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="notify_github_issue" class="sr-only peer" <?= ($projectNotifSettings['github_issue'] ?? true) ? 'checked' : '' ?>>
                                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                        </label>
                                    </div>
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                        <span class="text-sm font-medium text-gray-700">Task Status</span>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="notify_task_status" class="sr-only peer" <?= ($projectNotifSettings['task_status'] ?? true) ? 'checked' : '' ?>>
                                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                        </label>
                                    </div>
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                        <span class="text-sm font-medium text-gray-700">Agent Events</span>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="notify_agent_event" class="sr-only peer" <?= ($projectNotifSettings['agent_event'] ?? true) ? 'checked' : '' ?>>
                                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                        </label>
                                    </div>
                                </div>

                                <div class="border-t border-gray-100 pt-4">
                                    <h4 class="text-sm font-bold text-gray-900 mb-2 uppercase tracking-wider">Status Broadcast Preferences</h4>
                                    <p class="text-xs text-gray-500 mb-4">Choose which status changes trigger a broadcast (Telegram/Browser). All events still appear in the inbox.</p>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                                        <?php
                                        $statuses = [
                                            'researching' => 'Researching',
                                            'planning' => 'Planning',
                                            'coding' => 'Coding',
                                            'testing' => 'Testing',
                                            'in_progress' => 'In Progress',
                                            'implemented' => 'Implemented',
                                            'completed' => 'Completed',
                                            'failed_jules' => 'Jules Failed',
                                            'failed_pr' => 'PR Failed'
                                        ];
                                        foreach ($statuses as $id => $label):
                                        ?>
                                        <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg border border-gray-100 shadow-sm">
                                            <span class="text-xs font-medium text-gray-600"><?= $label ?></span>
                                            <label class="relative inline-flex items-center cursor-pointer scale-75">
                                                <input type="checkbox" name="broadcast_<?= $id ?>" class="sr-only peer" <?= ($statusNotifSettings[$id] ?? true) ? 'checked' : '' ?>>
                                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                            </label>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <button type="submit" name="update_notifications" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 focus:outline-none">Save Notification Settings</button>
                            </form>
                        </div>

                        <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                            <h3 class="text-lg font-bold text-gray-900 mb-4">Webhook Configuration</h3>
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-gray-700">Status</span>
                                    <?php if ($webhookStatus === 'active'): ?>
                                        <span class="px-2.5 py-0.5 text-xs font-bold rounded-full bg-green-100 text-green-800">Connected</span>
                                    <?php elseif ($webhookStatus === 'inactive'): ?>
                                        <span class="px-2.5 py-0.5 text-xs font-bold rounded-full bg-yellow-100 text-yellow-800">Inactive</span>
                                    <?php elseif ($webhookStatus === 'missing'): ?>
                                        <span class="px-2.5 py-0.5 text-xs font-bold rounded-full bg-red-100 text-red-800">Not Found</span>
                                    <?php elseif ($webhookStatus === 'error'): ?>
                                        <span class="px-2.5 py-0.5 text-xs font-bold rounded-full bg-red-100 text-red-800">API Error</span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-0.5 text-xs font-bold rounded-full bg-gray-100 text-gray-800">Unknown</span>
                                    <?php endif; ?>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Payload URL</label>
                                    <div class="mt-1 flex rounded-md shadow-sm">
                                        <input type="text" readonly value="<?= htmlspecialchars($webhookUrl) ?>" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Secret</label>
                                    <div class="mt-1 flex rounded-md shadow-sm">
                                        <input type="text" readonly value="<?= htmlspecialchars($project['webhook_secret'] ?? '') ?>" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                                    </div>
                                </div>

                                <?php if ($webhookStatus === 'missing' || $webhookStatus === 'error'): ?>
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?= $auth->getCsrfToken() ?>">
                                        <button type="submit" name="setup_webhook" class="text-white bg-blue-600 hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 focus:outline-none">Setup Webhook Automatically</button>
                                    </form>
                                <?php endif; ?>

                                <p class="text-sm text-gray-500">
                                    Configure this in your GitHub Repository <b>Settings > Webhooks</b>.<br>
                                    Set Content type to <b>application/json</b> and select <b>Issues</b> events.
                                </p>
                            </div>
                        </div>

                        <div class="p-4 bg-white border border-red-200 rounded-lg shadow-sm">
                            <h3 class="text-lg font-bold text-red-900 mb-4">Danger Zone</h3>
                            <p class="text-sm text-gray-600 mb-4">Once you delete a project, there is no going back. Please be certain.</p>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this project? This action cannot be undone.')">
                                <input type="hidden" name="csrf_token" value="<?= $auth->getCsrfToken() ?>">
                                <button type="submit" name="delete_project" class="text-white bg-red-600 hover:bg-red-700 focus:ring-4 focus:ring-red-300 font-medium rounded-lg text-sm px-5 py-2.5 focus:outline-none">Delete Project</button>
                            </form>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
