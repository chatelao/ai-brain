<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;
use App\Auth;
use App\User;
use App\Project;
use App\Task;

// Set environment for testing
putenv('DB_NAME=:memory:');

// Initialize session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db = new Database();
$pdo = $db->getConnection();

// Create schema for SQLite in-memory
$pdo->exec("CREATE TABLE IF NOT EXISTS users (
    user_id TEXT PRIMARY KEY,
    google_id VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    avatar VARCHAR(255),
    role VARCHAR(20) DEFAULT 'user',
    jules_api_key VARCHAR(255),
    telegram_link_token VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS user_github_accounts (
    github_account_id TEXT PRIMARY KEY,
    user_id TEXT,
    github_username VARCHAR(255) NOT NULL,
    github_token VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE(user_id, github_username)
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS projects (
    project_id TEXT PRIMARY KEY,
    user_id TEXT,
    github_account_id TEXT,
    github_repo VARCHAR(255) NOT NULL,
    webhook_secret VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (github_account_id) REFERENCES user_github_accounts(github_account_id) ON DELETE CASCADE
)");

// Create a mock admin user
$userModel = new User($db);
$admin = $userModel->createOrUpdate(['google_id' => 'google-admin', 'name' => 'Admin User', 'email' => 'admin@example.com', 'role' => 'admin']);
$user = $userModel->createOrUpdate(['google_id' => 'google-user', 'name' => 'Regular User', 'email' => 'user@example.com', 'role' => 'user']);

$_SESSION['user_id'] = $admin['user_id'];
$_SESSION['user_role'] = 'admin';

// Add projects for regular user
$projectModel = new Project($db);
$userModel->addGitHubAccount($user['user_id'], 'token', 'user');
$accounts = $userModel->getGitHubAccounts($user['user_id']);
$projectModel->create($user['user_id'], $accounts[0]['github_account_id'], 'user/repo-a');
$projectModel->create($user['user_id'], $accounts[0]['github_account_id'], 'user/repo-b');

// Include the admin dashboard
include __DIR__ . '/../../src/frontend/admin/index.php';
