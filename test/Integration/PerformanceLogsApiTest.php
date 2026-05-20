<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Database;
use App\User;
use App\Auth;
use App\Logger;
use PDO;

class PerformanceLogsApiTest extends TestCase
{
    private $db;
    private $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->db = $this->createMock(Database::class);
        $this->db->method('getConnection')->willReturn($this->pdo);

        $this->pdo->exec("CREATE TABLE users (
            user_id INTEGER PRIMARY KEY AUTOINCREMENT,
            google_id VARCHAR(255) UNIQUE NOT NULL,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            avatar VARCHAR(255),
            role VARCHAR(20) DEFAULT 'user',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE performance_logs (
            log_id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            type VARCHAR(50) NOT NULL,
            target VARCHAR(255) NOT NULL,
            duration FLOAT NOT NULL,
            context TEXT,
            status_code INTEGER,
            error_message TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_unset();
    }

    public function testGetPerformanceLogsAsUser()
    {
        $userModel = new User($this->db);
        $logger = new Logger($this->db);

        $user = $userModel->createOrUpdate([
            'google_id' => 'u1',
            'name' => 'User One',
            'email' => 'u1@example.com',
            'role' => 'user'
        ]);

        $admin = $userModel->createOrUpdate([
            'google_id' => 'admin',
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'role' => 'admin'
        ]);

        // Create logs
        $logger->logPerformance($user['user_id'], 'api', 'GET /api/projects', 0.1);
        $logger->logPerformance($admin['user_id'], 'api', 'GET /api/webhook-logs', 0.2);

        // Simulate login as user
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['user_role'] = 'user';

        // Fetch logs (using the Logger method directly to test RBAC logic)
        $logs = $logger->getPerformanceLogs($user['user_id']);

        $this->assertCount(1, $logs);
        $this->assertEquals('GET /api/projects', $logs[0]['target']);
        $this->assertEquals($user['email'], $logs[0]['user_email']);
    }

    public function testGetPerformanceLogsAsAdmin()
    {
        $userModel = new User($this->db);
        $logger = new Logger($this->db);

        $user = $userModel->createOrUpdate([
            'google_id' => 'u1',
            'name' => 'User One',
            'email' => 'u1@example.com',
            'role' => 'user'
        ]);

        $admin = $userModel->createOrUpdate([
            'google_id' => 'admin',
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'role' => 'admin'
        ]);

        // Create logs
        $logger->logPerformance($user['user_id'], 'api', 'GET /api/projects', 0.1);
        $logger->logPerformance($admin['user_id'], 'api', 'GET /api/webhook-logs', 0.2);

        // Simulate login as admin
        $_SESSION['user_id'] = $admin['user_id'];
        $_SESSION['user_role'] = 'admin';

        // Admins can see all logs
        $logs = $logger->getPerformanceLogs(null);

        $this->assertCount(2, $logs);
    }
}
