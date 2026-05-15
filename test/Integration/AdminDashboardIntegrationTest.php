<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Database;
use App\User;
use App\Auth;
use PDO;

class AdminDashboardIntegrationTest extends TestCase
{
    private $db;
    private $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->db = $this->createMock(Database::class);
        $this->db->method('getConnection')->willReturn($this->pdo);

        // Reset database
        $this->pdo->exec("DROP TABLE IF EXISTS users");
        $this->pdo->exec("DROP TABLE IF EXISTS projects");
        $this->pdo->exec("CREATE TABLE users (
            user_user_id INTEGER PRIMARY KEY AUTOINCREMENT,
            google_id VARCHAR(255) UNIQUE NOT NULL,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            avatar VARCHAR(255),
            role VARCHAR(20) DEFAULT 'user',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $this->pdo->exec("CREATE TABLE projects (
            user_user_id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_user_user_id INTEGER NOT NULL,
            github_account_user_user_id INTEGER NOT NULL,
            github_repo VARCHAR(255) NOT NULL,
            webhook_secret VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_unset();
    }

    public function testGetAllUsersWithProjectCount()
    {
        $userModel = new User($this->db);

        // Create 2 users
        $user1 = $userModel->createOrUpdate([
            'google_id' => 'g1',
            'name' => 'User One',
            'email' => 'u1@example.com',
            'role' => 'admin'
        ]);

        $user2 = $userModel->createOrUpdate([
            'google_id' => 'g2',
            'name' => 'User Two',
            'email' => 'u2@example.com',
            'role' => 'user'
        ]);

        // Add project for user 2
        $this->pdo->exec("INSERT INTO projects (user_id, github_account_id, github_repo) VALUES ({$user2['user_id']}, 1, 'repo1')");

        $allUsers = $userModel->getAllUsersWithProjectCount();

        $this->assertCount(2, $allUsers);

        // Check user 2 has 1 project
        $u2 = array_values(array_filter($allUsers, fn($u) => $u['user_id'] == $user2['user_id']))[0];
        $this->assertEquals(1, $u2['project_count']);

        // Check user 1 has 0 projects
        $u1 = array_values(array_filter($allUsers, fn($u) => $u['user_id'] == $user1['user_id']))[0];
        $this->assertEquals(0, $u1['project_count']);
        $this->assertEquals('admin', $u1['role']);
    }
}
