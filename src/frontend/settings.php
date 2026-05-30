<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;
use App\Auth;
use App\User;

$auth = new Auth();
$db = new Database();
$userModel = new User($db);

if (!$auth->isLoggedIn()) {
    header('Location: google/login.php');
    exit;
}

$user = $userModel->findById($auth->getUserId());

// Handle Legacy Preference
if (isset($_GET['legacy']) && $_GET['legacy'] === '1') {
    setcookie('prefer_legacy', '1', time() + (86400 * 30), "/");
} elseif (isset($_GET['legacy']) && $_GET['legacy'] === '0') {
    setcookie('prefer_legacy', '', time() - 3600, "/");
}

// Redirect to Next-Gen UI
header('Location: /web/settings/');
exit;
