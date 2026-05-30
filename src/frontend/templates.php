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

// Redirect to Next-Gen UI
header('Location: /web/templates/');
exit;
