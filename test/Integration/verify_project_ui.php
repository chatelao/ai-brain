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
    user_id INTEGER PRIMARY KEY AUTOINCREMENT,
    google_id VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    avatar VARCHAR(255),
    role VARCHAR(20) DEFAULT 'user',
    jules_api_key VARCHAR(255),
    telegram_link_token VARCHAR(255),
    telegram_bot_token VARCHAR(255),
    telegram_webhook_secret VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
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
    webhook_secret VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
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
    github_state VARCHAR(20) DEFAULT 'open',
    github_data TEXT,
    agent_response TEXT,
    jules_session_id VARCHAR(255),
    jules_status VARCHAR(255),
    last_synced_at TIMESTAMP NULL,
    jules_url VARCHAR(255),
    pr_url VARCHAR(255),
    github_pr_data TEXT,
    github_comments_data TEXT,
    github_data_updated_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(project_id, issue_number)
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS user_telegram_accounts (
    telegram_account_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INT NOT NULL,
    telegram_chat_id BIGINT UNIQUE NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS task_logs (
    task_log_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INT NOT NULL,
    task_id INT NOT NULL,
    level VARCHAR(20) DEFAULT 'info',
    message TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(task_id) ON DELETE CASCADE
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

$pdo->exec("CREATE TABLE IF NOT EXISTS notification_user_settings (
    user_id INTEGER PRIMARY KEY,
    channels TEXT, -- JSON array of enabled channels
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS task_notification_settings (
    user_id INT NOT NULL,
    task_id INT NOT NULL,
    is_muted BOOLEAN DEFAULT 0,
    PRIMARY KEY (user_id, task_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES tasks(task_id) ON DELETE CASCADE
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS user_event_notification_settings (user_id INT NOT NULL, notification_type VARCHAR(50) NOT NULL, is_enabled BOOLEAN DEFAULT TRUE, PRIMARY KEY (user_id, notification_type), FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE)");
$pdo->exec("CREATE TABLE IF NOT EXISTS user_event_notification_settings (user_id INT NOT NULL, notification_type VARCHAR(50) NOT NULL, is_enabled BOOLEAN DEFAULT TRUE, PRIMARY KEY (user_id, notification_type), FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE)");
$pdo->exec("CREATE TABLE IF NOT EXISTS user_notification_settings (
    user_id INT NOT NULL,
    channel VARCHAR(20) NOT NULL,
    is_enabled BOOLEAN DEFAULT 1,
    PRIMARY KEY (user_id, channel),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS project_notification_settings (
    project_id INT NOT NULL,
    notification_type VARCHAR(50) NOT NULL,
    is_enabled BOOLEAN DEFAULT 1,
    PRIMARY KEY (project_id, notification_type),
    FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS issue_templates (
    issue_template_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    title_template VARCHAR(255) NOT NULL,
    body_template TEXT,
    parameter_config TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
)");

// Create a mock user
$userModel = new User($db);
$pdo->exec("INSERT OR IGNORE INTO users (user_id, google_id, name, email) VALUES (1, 'google-123', 'Test User', 'test@example.com')");
$_SESSION['user_id'] = 1;

// Add GitHub account
$userModel->addGitHubAccount(1, 'mock-token', 'mock-user');
$githubAccountId = $pdo->lastInsertId();

// Create a mock project
$projectModel = new Project($db);
$result = $projectModel->create(1, $githubAccountId, 'owner/repo');
$project = $projectModel->findByRepo('owner/repo')[0];

// Create some tasks
$taskModel = new Task($db);
$taskModel->create([
    'user_id' => 1,
    'project_id' => $project['project_id'],
    'issue_number' => 101,
    'title' => 'Sample Issue #1',
    'body' => 'Description for issue 1',
    'status' => 'pending'
]);

// Task with mergeable PR
$pdo->prepare("INSERT INTO tasks (user_id, project_id, issue_number, title, body, status, pr_url, github_pr_data, github_data_updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
    ->execute([
        1,
        $project['project_id'],
        103,
        'Feature implementation',
        'Implements the new feature',
         'checking',
        'https://github.com/owner/repo/pull/1',
        json_encode(['state' => 'open', 'mergeable_state' => 'clean', 'draft' => false]),
        date('Y-m-d H:i:s')
    ]);

// Add some logs to task 1
$logger = new \App\Logger($db);
$logger->log(1, 1, "Mock log entry 1 for task 101");
$logger->log(1, 1, "Mock error log entry 2 for task 101", "error");
$taskModel->create([
    'user_id' => 1,
    'project_id' => $project['project_id'],
    'issue_number' => 102,
    'title' => 'Another Bug #2',
    'body' => 'Description for bug 2',
    'status' => executing
]);

// Include the actual file to test
$_GET['id'] = $project['project_id'];
include __DIR__ . '/../../src/frontend/project.php';
