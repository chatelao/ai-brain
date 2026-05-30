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
    header('Location: /web/settings/');
    die();
}
?>
<!DOCTYPE html>
<html>
<head><title>Legacy Settings</title></head>
<body>
    <h1>Account Settings</h1>
    <div x-data="{ tab: 'general' }">
        <button @click="tab = 'notifications'">Notifications</button>
        <div x-show="tab === 'notifications'">
            <h3>Notification Channels</h3>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</body>
</html>
