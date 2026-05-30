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
    die();
}

if (($_COOKIE['prefer_legacy'] ?? '') !== '1' && !isset($_GET['legacy'])) {
    header('Location: /web/templates/');
    die();
}
?>
<!DOCTYPE html>
<html>
<head><title>Legacy Templates</title></head>
<body>
    <h1>Issue Templates</h1>
    <p>Please switch to <a href="/web/templates/">Next-Gen UI</a>.</p>
</body>
</html>
