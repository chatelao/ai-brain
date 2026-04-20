<?php
require_once __DIR__ . '/../../vendor/autoload.php';
use App\Auth;
use App\GitHubService;
use App\Database;
use App\User;

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$state = $_GET['state'] ?? '';
$sessionState = $_SESSION['github_oauth_state'] ?? '';

if (empty($state) || $state !== $sessionState) {
    die('Invalid state');
}

$code = $_GET['code'] ?? '';
if (empty($code)) {
    header('Location: index.php');
    exit;
}

try {
    $githubService = new GitHubService();
    $token = $githubService->getAccessToken($code);
    $githubUser = $githubService->getAuthenticatedUser($token);

    $db = new Database();
    $userModel = new User($db);
    $userModel->updateGitHubInfo($auth->getUserId(), $token, $githubUser['login']);

    header('Location: index.php');
    exit;
} catch (Exception $e) {
    echo "Error: " . htmlspecialchars($e->getMessage());
}
