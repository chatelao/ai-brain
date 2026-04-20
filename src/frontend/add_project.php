<?php
require_once __DIR__ . '/../../vendor/autoload.php';
use App\Auth;
use App\Database;
use App\User;
use App\Project;

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$db = new Database();
$userModel = new User($db);
$user = $userModel->findById($auth->getUserId());

if (!$user['github_token']) {
    header('Location: github_connect.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $repo = $_POST['github_repo'] ?? '';
    // Basic validation: owner/repo format
    if (preg_match('/^[a-z0-9_.-]+\/[a-z0-9_.-]+$/i', $repo)) {
        $projectModel = new Project($db);
        $projectModel->create($user['id'], $repo);
        header('Location: index.php');
        exit;
    } else {
        $error = "Invalid repository format. Please use 'owner/repo'.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Project - Agent Control</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal">
    <div class="flex items-center justify-center min-h-screen">
        <div class="w-full max-w-md p-8 space-y-6 bg-white rounded-lg shadow-md">
            <h2 class="text-2xl font-bold text-center text-gray-900">Link GitHub Repository</h2>
            <?php if (isset($error)): ?>
                <div class="p-2 text-sm text-red-600 bg-red-100 rounded"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form class="mt-8 space-y-6" method="POST">
                <div>
                    <label for="github_repo" class="block text-sm font-medium text-gray-700">Repository (e.g., owner/repo)</label>
                    <input id="github_repo" name="github_repo" type="text" required class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="google/agent-control-php">
                </div>
                <div>
                    <button type="submit" class="flex justify-center w-full px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Add Project
                    </button>
                </div>
            </form>
            <div class="text-center">
                <a href="index.php" class="text-sm font-medium text-blue-600 hover:underline">Cancel</a>
            </div>
        </div>
    </div>
</body>
</html>
