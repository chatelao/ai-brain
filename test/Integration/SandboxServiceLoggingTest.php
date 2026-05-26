<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Database;
use App\SandboxService;
use App\GitHubService;
use App\NotificationService;
use App\Logger;
use PDO;
use Tests\TestDatabaseTrait;

class SandboxServiceLoggingTest extends TestCase
{
    use TestDatabaseTrait;

    private $db;
    private $pdo;
    private $githubService;
    private $notificationService;
    private $sandboxService;

    protected function setUp(): void
    {
        $this->pdo = $this->getTestPdo();
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        // Reset tables
        $tables = [
            'task_logs', 'tasks', 'projects', 'user_github_accounts',
            'notifications'
        ];
        foreach ($tables as $table) {
            $this->pdo->exec("DROP TABLE IF EXISTS $table");
        }

        $pk = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
        $timestamp = $driver === 'sqlite' ? 'DATETIME DEFAULT CURRENT_TIMESTAMP' : 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP';

        $this->pdo->exec("CREATE TABLE user_github_accounts (
            github_account_id $pk,
            user_id INT NOT NULL,
            github_token VARCHAR(255),
            github_username VARCHAR(255)
        )");

        $this->pdo->exec("CREATE TABLE projects (
            project_id $pk,
            user_id INT NOT NULL,
            github_repo VARCHAR(255) NOT NULL,
            github_account_id INT,
            blockly_config TEXT,
            webhook_secret VARCHAR(255),
            created_at $timestamp
        )");

        $this->pdo->exec("CREATE TABLE tasks (
            task_id $pk,
            user_id INT NOT NULL,
            project_id INT NOT NULL,
            issue_number INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            body TEXT,
            status TEXT DEFAULT 'created',
            github_state VARCHAR(20) DEFAULT 'open',
            github_data TEXT,
            pr_url VARCHAR(255),
            jules_session_id VARCHAR(255),
            created_at $timestamp,
            updated_at $timestamp,
            UNIQUE(project_id, issue_number)
        )");

        $this->pdo->exec("CREATE TABLE task_logs (
            log_id $pk,
            task_id INT NOT NULL,
            user_id INT NOT NULL,
            message TEXT,
            level VARCHAR(20),
            created_at $timestamp
        )");

        $this->pdo->exec("CREATE TABLE notifications (
            notification_id $pk,
            user_id INT NOT NULL,
            project_id INT,
            type VARCHAR(50),
            title VARCHAR(255),
            message TEXT,
            data TEXT,
            is_read BOOLEAN DEFAULT 0,
            created_at $timestamp
        )");

        $this->db = $this->createMock(Database::class);
        $this->db->method('getConnection')->willReturn($this->pdo);

        $this->githubService = $this->createMock(GitHubService::class);
        $this->notificationService = $this->createMock(NotificationService::class);

        $this->sandboxService = new SandboxService($this->db, $this->githubService, $this->notificationService);

        // Ensure Logger uses the test DB
        Logger::resetInstance();
        new Logger($this->db);
    }

    public function testLoggingWithSource()
    {
        // Seed data
        $this->pdo->exec("INSERT INTO user_github_accounts (github_account_id, user_id, github_username) VALUES (1, 1, 'testuser')");
        $this->pdo->exec("INSERT INTO projects (project_id, user_id, github_repo, github_account_id) VALUES (1, 1, 'owner/repo', 1)");
        $this->pdo->exec("INSERT INTO tasks (task_id, user_id, project_id, issue_number, title, github_data, status) VALUES (1, 1, 1, 101, 'Test Task', '{\"labels\":[]}', 'created')");

        $jsCode = "setLabel('automated'); notify('Hello');";

        $result = $this->sandboxService->execute(1, 1, $jsCode, [], 'Global');

        $this->assertTrue($result['success']);

        // Verify start log
        $stmt = $this->pdo->query("SELECT message FROM task_logs WHERE task_id = 1 AND message LIKE 'Executing [Global] Blockly automation%'");
        $this->assertNotEmpty($stmt->fetchAll());

        // Verify action logs with source
        $stmt = $this->pdo->query("SELECT message FROM task_logs WHERE task_id = 1 AND message LIKE 'Blockly Action [Global]: Added label%'");
        $this->assertNotEmpty($stmt->fetchAll());

        $stmt = $this->pdo->query("SELECT message FROM task_logs WHERE task_id = 1 AND message LIKE 'Blockly Action [Global]: Notifying user%'");
        $this->assertNotEmpty($stmt->fetchAll());
    }

    public function testDryRunMode()
    {
        // Seed data
        $this->pdo->exec("INSERT INTO user_github_accounts (github_account_id, user_id, github_username) VALUES (1, 1, 'testuser')");
        $this->pdo->exec("INSERT INTO projects (project_id, user_id, github_repo, github_account_id) VALUES (1, 1, 'owner/repo', 1)");
        $this->pdo->exec("INSERT INTO tasks (task_id, user_id, project_id, issue_number, title, github_data, status) VALUES (1, 1, 1, 101, 'Test Task', '{\"labels\":[]}', 'created')");

        $jsCode = "setLabel('automated'); notify('Hello');";

        // Mocks should NOT be called in dry run
        $this->githubService->expects($this->never())->method('addLabel');
        $this->notificationService->expects($this->never())->method('notify');

        $result = $this->sandboxService->execute(1, 1, $jsCode, [], 'Local', true);

        $this->assertTrue($result['success']);

        // Verify start log mentions DRY RUN
        $stmt = $this->pdo->query("SELECT message FROM task_logs WHERE task_id = 1 AND message LIKE 'Executing [Local] Blockly automation (DRY RUN)%'");
        $this->assertNotEmpty($stmt->fetchAll());

        // Verify action logs mention DRY RUN
        $stmt = $this->pdo->query("SELECT message FROM task_logs WHERE task_id = 1 AND message LIKE 'Blockly Action [Local]: (DRY RUN) Would have added label%'");
        $this->assertNotEmpty($stmt->fetchAll());

        $stmt = $this->pdo->query("SELECT message FROM task_logs WHERE task_id = 1 AND message LIKE 'Blockly Action [Local]: (DRY RUN) Would have notified user%'");
        $this->assertNotEmpty($stmt->fetchAll());
    }
}
