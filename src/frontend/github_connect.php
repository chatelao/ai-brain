<?php
require_once __DIR__ . '/../../vendor/autoload.php';
use App\Auth;
use App\GitHubService;

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$githubService = new GitHubService();
$state = bin2hex(random_bytes(16));
$_SESSION['github_oauth_state'] = $state;

header('Location: ' . $githubService->getAuthUrl($state));
exit;
