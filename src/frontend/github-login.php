<?php
require_once __DIR__ . '/../../vendor/autoload.php';
use App\Auth;
use App\GitHubAuth;

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$githubAuth = new GitHubAuth();
header('Location: ' . $githubAuth->getAuthUrl());
exit;
