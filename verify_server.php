<?php
require_once __DIR__ . '/vendor/autoload.php';

use App\Database;

// Set environment for testing
putenv('DB_NAME=:memory:');

$db = new Database();
$pdo = $db->getConnection();

// Create schema (simplified for verification)
$pdo->exec("CREATE TABLE IF NOT EXISTS users (
    user_id INTEGER PRIMARY KEY AUTOINCREMENT,
    google_id VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    avatar VARCHAR(255),
    role VARCHAR(20) DEFAULT 'user',
    jules_api_key VARCHAR(255),
    telegram_bot_token VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
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
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS projects (
    project_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INT NOT NULL,
    github_account_id INT NOT NULL,
    github_repo VARCHAR(255) NOT NULL,
    github_username VARCHAR(255),
    github_token VARCHAR(255),
    roadmap_data TEXT,
    roadmap_updated_at DATETIME,
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
    github_pr_data TEXT,
    github_comments_data TEXT,
    github_data_updated_at DATETIME,
    agent_response TEXT,
    pr_url VARCHAR(255),
    jules_url VARCHAR(255),
    jules_session_id VARCHAR(255),
    last_synced_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS task_logs (
    task_log_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INT NOT NULL,
    task_id INT NOT NULL,
    level VARCHAR(20) DEFAULT 'info',
    message TEXT NOT NULL,
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

$pdo->exec("CREATE TABLE IF NOT EXISTS task_notification_settings (
    task_id INTEGER NOT NULL PRIMARY KEY,
    is_muted BOOLEAN DEFAULT 0
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS issue_templates (
    issue_template_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    title_template VARCHAR(255) NOT NULL,
    body_template TEXT,
    parameter_config TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Insert mock data
$pdo->exec("INSERT INTO users (user_id, google_id, name, email) VALUES (1, 'google-123', 'Test User', 'test@example.com')");
$pdo->exec("INSERT INTO user_github_accounts (github_account_id, user_id, github_username, github_token) VALUES (1, 1, 'owner', 'fake-token')");
$pdo->exec("INSERT INTO projects (project_id, user_id, github_account_id, github_repo, github_username) VALUES (1, 1, 1, 'owner/repo', 'owner')");

$now = date('Y-m-d H:i:s');
// Task 1: Open Issue, Implemented, PR Ready (Open) -> Should show merge buttons
$pdo->exec("INSERT INTO tasks (task_id, user_id, project_id, issue_number, title, body, status, jules_status, pr_url, github_state, github_pr_data, github_data_updated_at)
            VALUES (101, 1, 1, 101, 'Open Task', 'Desc', 'ready', 'completed', 'https://github.com/owner/repo/pull/1', 'open', '{\"state\":\"open\",\"mergeable_state\":\"clean\"}', '$now')");

// Task 2: Closed Issue, Implemented, PR Ready -> Should NOT show merge buttons
$pdo->exec("INSERT INTO tasks (task_id, user_id, project_id, issue_number, title, body, status, jules_status, pr_url, github_state, github_pr_data, github_data_updated_at)
            VALUES (102, 1, 1, 102, 'Closed Task', 'Desc', 'finished', 'completed', 'https://github.com/owner/repo/pull/2', 'closed', '{\"state\":\"open\",\"mergeable_state\":\"clean\"}', '$now')");

session_start();
$_SESSION['user_id'] = 1;

$uri = $_SERVER['REQUEST_URI'];
if (strpos($uri, 'bypass_auth.php') !== false) {
    header('Location: project.php?id=1');
    exit;
}

$path = parse_url($uri, PHP_URL_PATH);
$file = basename($path);

if (strpos($path, '/api/task.php') !== false) {
    include 'src/frontend/api/task.php';
} elseif ($file === 'project.php') {
    $_GET['id'] = 1;
    include 'src/frontend/project.php';
} elseif ($file === 'task.php') {
    $_GET['id'] = $_GET['id'] ?? 101;
    include 'src/frontend/task.php';
} else {
    echo "Verification server running. Use /project.php or /task.php";
}
