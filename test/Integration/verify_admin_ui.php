<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;
use App\Auth;
use App\User;
use App\Project;

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
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    google_id VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    avatar VARCHAR(255),
    role VARCHAR(20) DEFAULT 'user',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS user_github_accounts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INT NOT NULL,
    github_username VARCHAR(255) NOT NULL,
    github_token VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE(user_id, github_username)
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS projects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INT NOT NULL,
    github_account_id INT NOT NULL,
    github_repo VARCHAR(255) NOT NULL,
    webhook_secret VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (github_account_id) REFERENCES user_github_accounts(id) ON DELETE CASCADE
)");

// Create a mock admin user
$pdo->exec("INSERT INTO users (id, google_id, name, email, role) VALUES (1, 'google-admin', 'Admin User', 'admin@example.com', 'admin')");
$pdo->exec("INSERT INTO users (id, google_id, name, email, role) VALUES (2, 'google-user', 'Regular User', 'user@example.com', 'user')");

$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'admin';

// Add projects for regular user
$pdo->exec("INSERT INTO projects (user_id, github_account_id, github_repo) VALUES (2, 1, 'user/repo-a')");
$pdo->exec("INSERT INTO projects (user_id, github_account_id, github_repo) VALUES (2, 1, 'user/repo-b')");

// Include the admin dashboard
include __DIR__ . '/../../src/frontend/admin/index.php';
