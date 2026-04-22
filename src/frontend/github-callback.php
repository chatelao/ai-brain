<?php
require_once __DIR__ . '/../../vendor/autoload.php';
use App\Auth;
use App\GitHubAuth;
use App\Database;
use App\User;
use App\RateLimiter;

$db = new Database();
$rateLimiter = new RateLimiter($db);
$ip = $rateLimiter->getIpAddress();

if (!$rateLimiter->check("github_callback_$ip", 20, 60)) {
    http_response_code(429);
    exit('Too many requests. Please try again later.');
}

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: index.php');
    exit;
}

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
