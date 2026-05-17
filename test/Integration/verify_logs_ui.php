<?php
namespace {
    require_once __DIR__ . '/../../vendor/autoload.php';

    use App\Database;
    use App\Logger;
    use App\WebhookLogger;

    // Define mock classes
    if (!class_exists('App\Auth')) {
        eval('namespace App { class Auth { public function isLoggedIn() { return true; } public function getUserId() { return 1; } } }');
    }

    // Mock login and session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['user_id'] = 1;
    putenv('UPGRADE_ALLOWED_EMAIL=admin@example.com');

    $db = new Database(null, ':memory:');
    Database::resetConnection();
    $pdo = $db->getConnection();

    // Create schema
    $pdo->exec("CREATE TABLE users (user_id INTEGER PRIMARY KEY, name TEXT, email TEXT, avatar TEXT, jules_api_key TEXT, telegram_bot_token TEXT)");
    $pdo->exec("CREATE TABLE performance_logs (performance_log_id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, type TEXT, target TEXT, duration FLOAT, context TEXT, status_code INTEGER, error_message TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE webhook_logs (log_id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, endpoint TEXT, payload TEXT, headers TEXT, status_code INTEGER, error_message TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE task_logs (task_log_id INTEGER PRIMARY KEY, user_id INTEGER, task_id INTEGER, level TEXT, message TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE tasks (task_id INTEGER PRIMARY KEY, user_id INTEGER, project_id INTEGER, issue_number INTEGER, title TEXT, status TEXT, github_state TEXT)");

    // Insert mock user
    $pdo->prepare("INSERT INTO users (user_id, name, email) VALUES (1, 'Admin User', 'admin@example.com')")->execute();

    // Insert mock performance logs
    $logger = new Logger($db);
    $logger->logPerformance(1, 'GitHub API', 'GET issues', 1.5, null, 200);
    $logger->logPerformance(1, 'Jules API', 'POST trigger', 0.5, null, 500, 'Internal Server Error');
    $logger->logPerformance(1, 'Telegram API', 'POST sendMessage', 2.1, null, 401, 'Unauthorized');

    $webhookLogger = new WebhookLogger($db);

    $user = ['user_id' => 1, 'name' => 'Admin User', 'email' => 'admin@example.com', 'avatar' => null];
    $isAdmin = true;
    $performanceLogs = $logger->getPerformanceLogs(null, 100);
    $webhookLogs = [];

    // Now include logs.php but we need to mock the top part
    $logsPhp = file_get_contents(__DIR__ . '/../../src/frontend/logs.php');

    // Replace the setup part with our mock
    $logsPhp = preg_replace('/<\?php.*?\$webhookLogger = new WebhookLogger\(\$db\);.*?\?>/s', '', $logsPhp);

    // Also remove the include of navbar-icons.php as it might fail
    $logsPhp = str_replace("<?php include 'navbar-icons.php'; ?>", "<!-- Navbar Icons Mock -->", $logsPhp);

    eval(" ?>".$logsPhp);
}
