<?php
require_once __DIR__ . '/../../vendor/autoload.php';
use App\Database;
use App\Auth;
use App\User;
use App\Project;

$auth = new Auth();
$db = new Database();
$userModel = new User($db);
$projectModel = new Project($db);

if (!$auth->isLoggedIn()) {
    header('Location: google/login.php');
    die();
}

$user = $userModel->findById($auth->getUserId());
$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$project = $projectModel->findById($projectId);

if (!$project || $project['user_id'] !== $user['user_id']) {
    die("Project not found or access denied.");
}

if (($_COOKIE['prefer_legacy'] ?? '') !== '1' && !isset($_GET['legacy'])) {
    header('Location: /index.php');
    die();
}
?>
<!DOCTYPE html>
<html>
<head><title>Legacy Project View</title></head>
<body>
    <h1><?= htmlspecialchars($project['github_repo'] ?? 'Project') ?></h1>
    <p>This is the legacy view. Please switch to the <a href="/web/projects/<?= $projectId ?>/">Next-Gen UI</a>.</p>
</body>
</html>
