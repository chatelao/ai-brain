<?php
require_once __DIR__ . '/../vendor/autoload.php';
use App\Auth;
use App\Database;
use App\User;

$auth = new Auth();
$db = new Database();
$userModel = new User($db);

if (isset($_GET['code']) && isset($_GET['state'])) {
    if (!$auth->verifyState($_GET['state'])) {
        die("Invalid OAuth state");
    }
    try {
        $googleUser = $auth->authenticate($_GET['code']);
        $user = $userModel->createOrUpdate($googleUser);
        $auth->login($user);
        header('Location: index.php');
        exit;
    } catch (Exception $e) {
        echo "Error: " . htmlspecialchars($e->getMessage());
    }
} else {
    header('Location: index.php');
    exit;
}
