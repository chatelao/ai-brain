<?php
require_once __DIR__ . '/../vendor/autoload.php';
use App\Auth;
$auth = new Auth();
$auth->logout();
header('Location: index.php');
exit;
