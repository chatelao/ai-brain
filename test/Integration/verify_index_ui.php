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

// Create schema
$pdo->exec("CREATE TABLE IF NOT EXISTS users (
    user_id INTEGER PRIMARY KEY AUTOINCREMENT,
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
    github_account_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INT NOT NULL,
    github_username VARCHAR(255) NOT NULL,
    github_token VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS projects (
    project_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INT NOT NULL,
    github_account_id INT NOT NULL,
    github_repo VARCHAR(255) NOT NULL,
    github_username VARCHAR(255),
    webhook_secret VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS tasks (
    task_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INT NOT NULL,
    project_id INT NOT NULL,
    issue_number INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    body TEXT,
    status TEXT DEFAULT 'pending',
    github_state VARCHAR(20) DEFAULT 'open',
    github_data TEXT, autorepeat_remaining INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS user_telegram_accounts (
    telegram_account_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INT NOT NULL,
    telegram_chat_id BIGINT UNIQUE NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
    notification_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    data TEXT,
    is_read BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS user_notification_settings (
    user_id INT NOT NULL,
    channel VARCHAR(20) NOT NULL,
    is_enabled BOOLEAN DEFAULT 1,
    PRIMARY KEY (user_id, channel)
)");

// Create a mock user
$pdo->exec("INSERT OR IGNORE INTO users (user_id, google_id, name, email) VALUES (1, 'google-123', 'Test User', 'test@example.com')");
$_SESSION['user_id'] = 1;

// Create mock project
$pdo->exec("INSERT INTO projects (project_id, user_id, github_account_id, github_repo, github_username) VALUES (1, 1, 1, 'owner/repo', 'owner')");

// Create mock task with autorepeat label
$github_data = json_encode([
    'state' => 'open',
    'labels' => [['name' => 'autorepeat']]
]);
$pdo->exec("INSERT INTO tasks (user_id, project_id, issue_number, title, status, github_state, github_data) VALUES (1, 1, 123, 'Autorepeat Task Title', 'pending', 'open', '$github_data')");

// Include index.php
include __DIR__ . '/../../src/frontend/index.php';
