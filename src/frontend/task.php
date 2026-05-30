<?php
require_once __DIR__ . '/../../vendor/autoload.php';
use App\Database;
use App\Auth;
use App\User;
use App\Task;

$auth = new Auth();
$db = new Database();
$userModel = new User($db);
$taskModel = new Task($db);

if (!$auth->isLoggedIn()) {
    header('Location: google/login.php');
    die();
}

$user = $userModel->findById($auth->getUserId());
$taskId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$task = $taskModel->findById($taskId);

if (!$task || $task['user_id'] !== $user['user_id']) {
    die("Task not found or access denied.");
}

if (($_COOKIE['prefer_legacy'] ?? '') !== '1' && !isset($_GET['legacy'])) {
    header('Location: /web/tasks/?id=' . $taskId);
    die();
}
?>
<!DOCTYPE html>
<html>
<head><title>Legacy Task View</title></head>
<body>
    <h1><?= htmlspecialchars($task['title'] ?? 'Task') ?></h1>
    <div>Task Logs</div>
    <div>Status & Links</div>
</body>
</html>
