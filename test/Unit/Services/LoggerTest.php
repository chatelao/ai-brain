<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Database;
use App\Logger;

class LoggerTest extends TestCase
{
    private Database $db;
    private Logger $logger;

    protected function setUp(): void
    {
        $this->db = new Database(null, ':memory:');
        Database::resetConnection();
        $this->createSchema();
        $this->logger = new Logger($this->db);
    }

    private function createSchema(): void
    {
        $pdo = $this->db->getConnection();
        $pdo->exec("CREATE TABLE tasks (task_id INTEGER PRIMARY KEY, user_id INTEGER, project_id INTEGER, issue_number INTEGER, title TEXT, status TEXT, github_state TEXT)");
        $pdo->exec("CREATE TABLE task_logs (task_log_id INTEGER PRIMARY KEY, user_id INTEGER, task_id INTEGER, level TEXT, message TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    }

    public function testLogAndGetLogs(): void
    {
        $userId = 1;
        $taskId = 1;
        $this->logger->log($userId, $taskId, "Test message 1", "info");
        $this->logger->log($userId, $taskId, "Test message 2", "error");

        $logs = $this->logger->getLogsByTaskId($taskId);

        $this->assertCount(2, $logs);
        $this->assertEquals("Test message 1", $logs[0]['message']);
        $this->assertEquals("info", $logs[0]['level']);
        $this->assertEquals("Test message 2", $logs[1]['message']);
        $this->assertEquals("error", $logs[1]['level']);
    }
}
