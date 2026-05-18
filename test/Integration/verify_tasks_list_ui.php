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
    jules_quota_usage INT DEFAULT 0,
    jules_quota_limit INT DEFAULT 100,
    telegram_bot_token VARCHAR(255),
    telegram_webhook_secret VARCHAR(255),
    telegram_link_token VARCHAR(255),
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
    jules_status VARCHAR(50) DEFAULT 'pending',
    github_state VARCHAR(20) DEFAULT 'open',
    github_data TEXT,
    agent_response TEXT,
    pr_url VARCHAR(255),
    jules_url VARCHAR(255),
    jules_session_id VARCHAR(255),
    last_synced_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
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

$pdo->exec("CREATE TABLE IF NOT EXISTS user_telegram_accounts (
    telegram_account_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INT NOT NULL,
    telegram_chat_id BIGINT UNIQUE NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Create a mock user
$pdo->exec("INSERT OR IGNORE INTO users (user_id, google_id, name, email, jules_quota_usage, jules_quota_limit) VALUES (1, 'google-123', 'Test User', 'test@example.com', 10, 100)");
$_SESSION['user_id'] = 1;

// Create mock project
$pdo->exec("INSERT INTO projects (project_id, user_id, github_account_id, github_repo, github_username) VALUES (1, 1, 1, 'owner/repo', 'owner')");

// Create mock tasks for different filters
$pdo->exec("INSERT INTO tasks (user_id, project_id, issue_number, title, status, github_state) VALUES (1, 1, 1, 'GitHub Running Task', 'checking', 'open')");
$pdo->exec("INSERT INTO tasks (user_id, project_id, issue_number, title, status, github_state) VALUES (1, 1, 2, 'GitHub Passed Task', 'completed', 'open')");
$pdo->exec("INSERT INTO tasks (user_id, project_id, issue_number, title, status, github_state) VALUES (1, 1, 3, 'GitHub Failed Task', 'failed_pr', 'open')");
$pdo->exec("INSERT INTO tasks (user_id, project_id, issue_number, title, status, github_state) VALUES (1, 1, 4, 'Jules Running Task', 'coding', 'open')");
$pdo->exec("INSERT INTO tasks (user_id, project_id, issue_number, title, status, github_state) VALUES (1, 1, 5, 'Jules Failed Task', 'failed_jules', 'open')");

// Include tasks.php
include __DIR__ . '/../../src/frontend/tasks.php';
