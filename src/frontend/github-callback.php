<?php
require_once __DIR__ . '/../../vendor/autoload.php';
use App\Auth;
use App\GitHubAuth;
use App\Database;
use App\User;

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$db = new Database();
$userModel = new User($db);
$githubAuth = new GitHubAuth();

if (isset($_GET['code']) && isset($_GET['state'])) {
    try {
        $githubData = $githubAuth->authenticate($_GET['code'], $_GET['state']);
        $userModel->addGitHubAccount($auth->getUserId(), $githubData['access_token'], $githubData['github_username']);
        header('Location: index.php?github=success');
        exit;
    } catch (Exception $e) {
        header('Location: index.php?github=error&message=' . urlencode($e->getMessage()));
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}
