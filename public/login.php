<?php
require_once __DIR__ . '/../vendor/autoload.php';
use App\Auth;
$auth = new Auth();
header('Location: ' . $auth->getAuthUrl());
exit;
