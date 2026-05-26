<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Database;
use App\WebhookHandler;
use App\SandboxService;
use App\User;
use App\Project;
use App\Task;
use PDO;
use Tests\TestDatabaseTrait;

class BlocklySequentialTest extends TestCase
{
    use TestDatabaseTrait;

    private $pdo;
    private $db;
    private $webhookHandler;

    protected function setUp(): void
    {
        $this->pdo = $this->getTestPdo();
        $this->setUpDatabase();

        $this->db = $this->createMock(Database::class);
        $this->db->method('getConnection')->willReturn($this->pdo);

        $this->webhookHandler = new WebhookHandler($this->db);
    }

    private function setUpDatabase(): void
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $pk = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';

        $this->pdo->exec("DROP TABLE IF EXISTS tasks");
        $this->pdo->exec("DROP TABLE IF EXISTS projects");
        $this->pdo->exec("DROP TABLE IF EXISTS users");
        $this->pdo->exec("DROP TABLE IF EXISTS user_github_accounts");
        $this->pdo->exec("DROP TABLE IF EXISTS performance_logs");

        $this->pdo->exec("CREATE TABLE performance_logs (
            log_id $pk,
            user_id INT,
            category VARCHAR(50),
            target VARCHAR(255),
            duration FLOAT,
            memory INT,
            status_code INT,
            error_message TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE users (
            user_id $pk,
            name VARCHAR(255),
            email VARCHAR(255),
            blockly_config TEXT
        )");

        $this->pdo->exec("CREATE TABLE user_github_accounts (
            github_account_id $pk,
            user_id INT,
            github_username VARCHAR(255),
            github_token VARCHAR(255)
        )");

        $this->pdo->exec("CREATE TABLE projects (
            project_id $pk,
            user_id INT,
            github_account_id INT,
            github_repo VARCHAR(255),
            webhook_secret VARCHAR(255),
            blockly_config TEXT
        )");

        $this->pdo->exec("CREATE TABLE tasks (
            task_id $pk,
            user_id INT,
            project_id INT,
            issue_number INT,
            title VARCHAR(255),
            status VARCHAR(50) DEFAULT 'pending',
            github_data TEXT,
            UNIQUE(project_id, issue_number)
        )");

        $stmt = $this->pdo->prepare("INSERT INTO users (user_id, name, email, blockly_config) VALUES (?, ?, ?, ?)");
        $stmt->execute([1, 'User 1', 'user1@example.com', json_encode(['js' => 'console.log("Global Executed");'])]);

        $this->pdo->exec("INSERT INTO user_github_accounts (github_account_id, user_id, github_username) VALUES (1, 1, 'user1')");

        $stmt = $this->pdo->prepare("INSERT INTO projects (project_id, user_id, github_account_id, github_repo, blockly_config) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([1, 1, 1, 'owner/repo', json_encode(['js' => 'console.log("Local Executed");'])]);
    }

    public function testRunBlocklyAutomationsSequentialExecution()
    {
        $sandboxService = $this->createMock(SandboxService::class);

        $calls = [];
        $sandboxService->method('execute')
            ->willReturnCallback(function($userId, $taskId, $jsCode, $context, $source, $dryRun, $ignoredEvents = []) use (&$calls) {
                $calls[] = [
                    'code' => $jsCode,
                    'source' => $source,
                    'ignoredEvents' => $ignoredEvents
                ];
                return ['success' => true, 'handledEvents' => ($source === 'Local' ? ['TEST_EVENT'] : [])];
            });

        $project = [
            'user_id' => 1,
            'project_id' => 1,
            'blockly_config' => '{"js": "console.log(\"Local Executed\");"}'
        ];

        $event = ['action' => 'opened'];

        $reflection = new \ReflectionClass($this->webhookHandler);
        $method = $reflection->getMethod('runBlocklyAutomations');
        $method->setAccessible(true);

        $method->invoke($this->webhookHandler, $project, $event, 'issues', 123, $sandboxService);

        $dbUser = $this->pdo->query("SELECT * FROM users WHERE user_id = 1")->fetch();

        $this->assertCount(2, $calls, "Should have 2 calls (global and local).");

        // 1. Local call
        $this->assertEquals('Local', $calls[0]['source']);
        $this->assertEquals('console.log("Local Executed");', $calls[0]['code']);
        $this->assertEmpty($calls[0]['ignoredEvents']);

        // 2. Global call
        $this->assertEquals('Global', $calls[1]['source']);
        $this->assertEquals('console.log("Global Executed");', $calls[1]['code']);
        // Verify it received the handledEvents from Local
        $this->assertEquals(['TEST_EVENT'], $calls[1]['ignoredEvents']);
    }
}
