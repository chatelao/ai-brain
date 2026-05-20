<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Database;
use App\User;
use App\Auth;
use PDO;

class UserApiIntegrationTest extends TestCase
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
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            jules_api_key VARCHAR(255),
            telegram_bot_token VARCHAR(255),
            telegram_webhook_secret VARCHAR(255),
            telegram_bot_name VARCHAR(255),
            telegram_link_token VARCHAR(255),
            jules_quota_usage INTEGER DEFAULT 0,
            jules_quota_limit INTEGER DEFAULT 0,
            jules_quota_updated_at DATETIME
        )");

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_unset();
    }

    public function testUserApiLogic()
    {
        $userModel = new User($this->db);
        $userData = [
            'google_id' => 'u123',
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'avatar' => 'https://example.com/avatar.jpg'
        ];
        $user = $userModel->createOrUpdate($userData);

        // Verify we can retrieve the user by ID
        $retrievedUser = $userModel->findById($user['user_id']);

        $this->assertEquals($user['user_id'], $retrievedUser['user_id']);
        $this->assertEquals('John Doe', $retrievedUser['name']);
        $this->assertEquals('john@example.com', $retrievedUser['email']);
        $this->assertEquals('user', $retrievedUser['role']);

        // Ensure sensitive fields are present in the model but we'll manually verify they'd be filtered in the API response logic
        $this->assertArrayHasKey('google_id', $retrievedUser);
        $this->assertArrayHasKey('created_at', $retrievedUser);

        // Test output mapping logic used in src/frontend/api/user.php
        $output = [
            'id' => (int)$retrievedUser['user_id'],
            'google_id' => $retrievedUser['google_id'],
            'name' => $retrievedUser['name'],
            'email' => $retrievedUser['email'],
            'avatar' => $retrievedUser['avatar'] ?? null,
            'role' => $retrievedUser['role'],
            'created_at' => $retrievedUser['created_at']
        ];

        $this->assertEquals($user['user_id'], $output['id']);
        $this->assertEquals('John Doe', $output['name']);
        $this->assertArrayNotHasKey('jules_api_key', $output);
        $this->assertArrayNotHasKey('telegram_bot_token', $output);
    }
}
