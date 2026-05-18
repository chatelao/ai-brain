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
    telegram_bot_token VARCHAR(255),
    telegram_webhook_secret VARCHAR(255),
    telegram_link_token VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS user_event_notification_settings (user_id INT NOT NULL, notification_type VARCHAR(50) NOT NULL, is_enabled BOOLEAN DEFAULT TRUE, PRIMARY KEY (user_id, notification_type), FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE)");
$pdo->exec("CREATE TABLE IF NOT EXISTS user_event_notification_settings (user_id INT NOT NULL, notification_type VARCHAR(50) NOT NULL, is_enabled BOOLEAN DEFAULT TRUE, PRIMARY KEY (user_id, notification_type), FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE)");
$pdo->exec("CREATE TABLE IF NOT EXISTS user_notification_settings (
    user_id INTEGER NOT NULL,
    channel VARCHAR(20) NOT NULL,
    is_enabled BOOLEAN DEFAULT 1,
    PRIMARY KEY (user_id, channel)
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS project_notification_settings (
    project_id INTEGER NOT NULL,
    notification_type VARCHAR(50) NOT NULL,
    is_enabled BOOLEAN DEFAULT 1,
    PRIMARY KEY (project_id, notification_type)
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS task_notification_settings (
    task_id INTEGER NOT NULL PRIMARY KEY,
    is_muted BOOLEAN DEFAULT 0
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS project_status_notification_settings (
    project_id INTEGER NOT NULL,
    status VARCHAR(50) NOT NULL,
    is_enabled BOOLEAN DEFAULT 1,
    PRIMARY KEY (project_id, status)
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS user_telegram_accounts (
    telegram_account_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INT NOT NULL,
    telegram_chat_id BIGINT UNIQUE NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS user_github_accounts (
    github_account_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INT NOT NULL,
    github_username VARCHAR(255) NOT NULL,
    github_token VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE(user_id, github_username)
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS projects (
    project_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INT NOT NULL,
    github_account_id INT NOT NULL,
    github_repo VARCHAR(255) NOT NULL,
    github_username VARCHAR(255),
    webhook_secret VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (github_account_id) REFERENCES user_github_accounts(github_account_id) ON DELETE CASCADE
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
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
)");

// Create a mock user
$pdo->exec("INSERT OR IGNORE INTO users (user_id, google_id, name, email) VALUES (1, 'google-123', 'Test User', 'test@example.com')");
$_SESSION['user_id'] = 1;

// Create mock github account
$pdo->exec("INSERT INTO user_github_accounts (github_account_id, user_id, github_username, github_token) VALUES (1, 1, 'owner', 'fake-token')");

// Create a mock project
$pdo->exec("INSERT INTO projects (project_id, user_id, github_account_id, github_repo, github_username, webhook_secret) VALUES (1, 1, 1, 'owner/repo', 'owner', 'shhh')");

// Create a mock task
$pdo->exec("INSERT INTO tasks (task_id, user_id, project_id, issue_number, title, body, status, jules_status, pr_url, jules_url)
            VALUES (101, 1, 1, 101, 'Mock Task Title', 'This is a mock task description.', 'in_progress', 'coding', 'https://github.com/owner/repo/pull/1', 'https://jules.example.com/session/1')");

// Include the actual file to test
$_GET['id'] = 1;
include __DIR__ . '/../../src/frontend/project-settings.php';
