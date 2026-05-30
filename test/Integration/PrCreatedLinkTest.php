<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Database;
use App\WebhookHandler;
use App\SandboxService;
use App\GitHubService;
use App\NotificationService;
use App\JulesService;
use App\Logger;
use App\Task;
use App\Project;
use App\User;
use PDO;
use Tests\TestDatabaseTrait;

class PrCreatedLinkTest extends TestCase
{
    use TestDatabaseTrait;

    private $db;
    private $pdo;
    private $githubService;
    private $notificationService;
    private $julesService;
    private $sandboxService;
    private $webhookHandler;

    protected function setUp(): void
    {
        $this->pdo = $this->getTestPdo();
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        // Reset tables
        $tables = [
            'task_logs', 'tasks', 'projects', 'users', 'user_github_accounts',
            'notifications', 'task_notification_settings', 'user_event_notification_settings',
            'project_status_notification_settings', 'user_notification_settings'
        ];
        foreach ($tables as $table) {
            $this->pdo->exec("DROP TABLE IF EXISTS $table");
        }

        $pk = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
        $timestamp = $driver === 'sqlite' ? 'DATETIME DEFAULT CURRENT_TIMESTAMP' : 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP';

        $this->pdo->exec("CREATE TABLE users (
            user_id $pk,
            email VARCHAR(255) UNIQUE,
            blockly_config TEXT,
            jules_api_key VARCHAR(255),
            automations_enabled BOOLEAN DEFAULT 1
        )");

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
            blockly_config TEXT
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
            github_repo VARCHAR(255),
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
        $this->julesService = $this->createMock(JulesService::class);

        $this->sandboxService = new SandboxService($this->db, $this->githubService, $this->notificationService, $this->julesService);
        $this->webhookHandler = new WebhookHandler($this->db);

        Logger::resetInstance();
        new Logger($this->db);
    }

    public function testPrCreatedAutomationSucceedsWhenLinkedViaBody()
    {
        // 1. Setup: Global Blockly script for PR_CREATED
        $jsCode = "onEvent('PR_CREATED', (event) => { notify('Hello'); });";
        $blocklyConfig = json_encode(['js' => $jsCode]);

        $stmt = $this->pdo->prepare("INSERT INTO users (user_id, email, blockly_config) VALUES (1, 'test@example.com', ?)");
        $stmt->execute([$blocklyConfig]);
        $this->pdo->exec("INSERT INTO projects (project_id, user_id, github_repo) VALUES (1, 1, 'owner/repo')");

        // Task exists but NO pr_url yet
        $this->pdo->prepare("INSERT INTO tasks (task_id, user_id, project_id, issue_number, title, github_data, github_repo)
                          VALUES (1, 1, 1, 101, 'Fix Bug', '{\"labels\":[]}', 'owner/repo')")->execute();

        $project = [
            'project_id' => 1,
            'user_id' => 1,
            'github_repo' => 'owner/repo',
            'blockly_config' => null
        ];

        // 2. Trigger pull_request opened event referencing issue #101
        $payload = [
            'action' => 'opened',
            'pull_request' => [
                'number' => 42,
                'title' => 'Fix Bug',
                'body' => 'This fixes #101',
                'html_url' => 'https://github.com/owner/repo/pull/42',
                'user' => ['login' => 'jules']
            ],
            'repository' => ['full_name' => 'owner/repo']
        ];

        // Expectation: notify() SHOULD be called because linking works now
        $this->notificationService->expects($this->atLeastOnce())->method('notify');

        $this->webhookHandler->handle($project, $payload, $this->githubService, $this->notificationService, $this->julesService, $this->sandboxService, 'pull_request');

        // Check if Blockly automation ran
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM task_logs WHERE message LIKE '%Executing [Global] Blockly automation%'");
        $count = (int)$stmt->fetchColumn();

        $this->assertGreaterThan(0, $count, "Blockly automation did not run");

        // Verify pr_url was updated in the database
        $stmt = $this->pdo->query("SELECT pr_url FROM tasks WHERE task_id = 1");
        $this->assertEquals('https://github.com/owner/repo/pull/42', $stmt->fetchColumn());
    }
}
