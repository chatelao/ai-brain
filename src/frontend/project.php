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
    exit;
}

$user = $userModel->findById($auth->getUserId());

$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$project = $projectModel->findById($projectId);

if (!$project || $project['user_id'] !== $user['user_id']) {
    die("Project not found or access denied.");
}

// Handle Legacy Preference (to allow temporary toggle back if needed via cookie)
if (isset($_GET['legacy']) && $_GET['legacy'] === '1') {
    setcookie('prefer_legacy', '1', time() + (86400 * 30), "/");
} elseif (isset($_GET['legacy']) && $_GET['legacy'] === '0') {
    setcookie('prefer_legacy', '', time() - 3600, "/");
}

// Redirect to Next-Gen UI
$redirectUrl = '/web/projects/' . $projectId . '/';
if (isset($_GET['settings'])) {
    $redirectUrl .= 'settings/';
}

header('Location: ' . $redirectUrl);
exit;
