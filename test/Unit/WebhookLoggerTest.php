<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\WebhookLogger;
use App\Database;
use PDO;
use Tests\TestDatabaseTrait;

class WebhookLoggerTest extends TestCase
{
    use TestDatabaseTrait;

    private $db;
    private $pdo;
    private $logger;

    protected function setUp(): void
    {
        $this->db = new Database(null, ':memory:');
        Database::resetConnection();
        $this->pdo = $this->db->getConnection();

        // Create table for testing
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS users (user_id INTEGER PRIMARY KEY AUTOINCREMENT)");
        $this->pdo->exec("INSERT INTO users (user_id) VALUES (1)");

        $this->pdo->exec("DROP TABLE IF EXISTS webhook_logs");
        $this->pdo->exec("CREATE TABLE webhook_logs (
            log_id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            endpoint TEXT NOT NULL,
            payload TEXT,
            headers TEXT,
            status_code INTEGER,
            error_message TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->logger = new WebhookLogger($this->db);
    }

    public function testLog()
    {
        $result = $this->logger->log(1, 'github', '{"foo":"bar"}', '{"Content-Type":"application/json"}', 200);
        $this->assertTrue($result);

        $logs = $this->logger->getLogsByUser(1);
        $this->assertCount(1, $logs);
        $this->assertEquals('github', $logs[0]['endpoint']);
        $this->assertEquals('{"foo":"bar"}', $logs[0]['payload']);
        $this->assertEquals(200, $logs[0]['status_code']);
    }

    public function testPruning()
    {
        // Log 6 items
        for ($i = 1; $i <= 6; $i++) {
            $this->logger->log(1, 'github', "payload $i", "headers $i", 200);
            // Sleep a bit to ensure different timestamps if needed,
            // though we use IDs for pruning too.
            usleep(1000);
        }

        $logs = $this->logger->getLogsByUser(1);
        $this->assertCount(5, $logs);
        // The oldest one (payload 1) should be gone.
        // Since we order by created_at DESC, log_id DESC, the newest is first.
        $this->assertEquals('payload 6', $logs[0]['payload']);
        $this->assertEquals('payload 2', $logs[4]['payload']);
    }
}
