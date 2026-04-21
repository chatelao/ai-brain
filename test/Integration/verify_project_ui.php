<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;
use App\Auth;
use App\User;
use App\Project;
use App\Task;

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
    github_token VARCHAR(255),
    github_username VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS projects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INT NOT NULL,
    github_repo VARCHAR(255) NOT NULL,
    webhook_secret VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS tasks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INT NOT NULL,
    issue_number INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    body TEXT,
    status TEXT DEFAULT 'pending',
    github_data TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(project_id, issue_number)
)");

// Create a mock user
$userModel = new User($db);
$pdo->exec("INSERT OR IGNORE INTO users (id, google_id, name, email) VALUES (1, 'google-123', 'Test User', 'test@example.com')");
$_SESSION['user_id'] = 1;

// Create a mock project
$projectModel = new Project($db);
$projectModel->create(1, 'owner/repo');
$project = $projectModel->findByRepo('owner/repo')[0];

// Create some tasks
$taskModel = new Task($db);
$taskModel->create([
    'project_id' => $project['id'],
    'issue_number' => 101,
    'title' => 'Sample Issue #1',
    'body' => 'Description for issue 1',
    'status' => 'pending'
]);
$taskModel->create([
    'project_id' => $project['id'],
    'issue_number' => 102,
    'title' => 'Another Bug #2',
    'body' => 'Description for bug 2',
    'status' => 'in_progress'
]);

// Include the actual file to test
$_GET['id'] = $project['id'];
include __DIR__ . '/../../src/frontend/project.php';
