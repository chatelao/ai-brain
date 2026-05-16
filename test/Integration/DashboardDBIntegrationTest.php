<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Auth;
use App\User;
use App\Database;
use PDO;
use Tests\TestDatabaseTrait;

class DashboardDBIntegrationTest extends TestCase
{
    use TestDatabaseTrait;

    private $pdo;
    private $db;

    protected function setUp(): void
    {
        $this->pdo = $this->getTestPdo();
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        $this->pdo->exec("DROP TABLE IF EXISTS users");

        $pk = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
        $timestamp = $driver === 'sqlite' ? 'DATETIME DEFAULT CURRENT_TIMESTAMP' : 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP';

        $this->pdo->exec("CREATE TABLE users (
            user_id $pk,
            google_id VARCHAR(255) UNIQUE NOT NULL,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            avatar VARCHAR(255), role VARCHAR(20) DEFAULT 'user',
            created_at $timestamp
        )");

        $this->db = $this->createMock(Database::class);
        $this->db->method('getConnection')->willReturn($this->pdo);
    }

    public function testDashboardIntegrationWithRealDB()
    {
        $userModel = new User($this->db);
        $userModel->createOrUpdate([
            'google_id' => 'google-999',
            'name' => 'Real DB User',
            'email' => 'realdb@example.com',
            'avatar' => 'http://example.com/avatar.png'
        ]);
        $user = $userModel->findByGoogleId('google-999');

        $auth = $this->createMock(Auth::class);
        $auth->method('isLoggedIn')->willReturn(true);
        $auth->method('getUserId')->willReturn($user['user_id']);

        ob_start();
        include __DIR__ . '/dashboard_sim.php';
        $output = ob_get_clean();

        $this->assertStringContainsString('Real DB User', $output);
        $this->assertStringContainsString('realdb@example.com', $output);
    }
}
