<?php

require_once __DIR__ . '/../../../vendor/autoload.php';
use App\Auth;
use App\GitHubAuth;
use App\Database;
use App\RateLimiter;

$db = new Database();
$rateLimiter = new RateLimiter($db);
$ip = $rateLimiter->getIpAddress();

if (!$rateLimiter->check("login_$ip", 10, 60)) {
    http_response_code(429);
    exit('Too many login attempts. Please try again in a minute.');
}

$githubAuth = new GitHubAuth();
header('Location: ' . $githubAuth->getAuthUrl());
exit;
