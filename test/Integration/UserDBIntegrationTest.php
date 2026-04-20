<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Database;
use App\User;
use PDO;

class UserDBIntegrationTest extends TestCase
{
    private $db;
    private $pdo;
    private $userModel;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec("CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            google_id VARCHAR(255) UNIQUE NOT NULL,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            avatar VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->db = $this->createMock(Database::class);
        $this->db->method('getConnection')->willReturn($this->pdo);

        $this->userModel = new User($this->db);
    }

    public function testCreateOrUpdateCreatesNewUser()
    {
        $userData = [
            'google_id' => 'google-int-123',
            'name' => 'Integration User',
            'email' => 'int@example.com',
            'avatar' => 'avatar.jpg'
        ];

        $user = $this->userModel->createOrUpdate($userData);

        $this->assertEquals('Integration User', $user['name']);
        $this->assertEquals('google-int-123', $user['google_id']);

        $foundUser = $this->userModel->findByGoogleId('google-int-123');
        $this->assertNotNull($foundUser);
        $this->assertEquals('int@example.com', $foundUser['email']);
    }

    public function testCreateOrUpdateUpdatesExistingUser()
    {
        $userData = [
            'google_id' => 'google-int-123',
            'name' => 'Original Name',
            'email' => 'int@example.com',
            'avatar' => 'avatar.jpg'
        ];

        $this->userModel->createOrUpdate($userData);

        $updatedData = [
            'google_id' => 'google-int-123',
            'name' => 'Updated Name',
            'email' => 'int@example.com',
            'avatar' => 'new_avatar.jpg'
        ];

        $user = $this->userModel->createOrUpdate($updatedData);

        $this->assertEquals('Updated Name', $user['name']);
        $this->assertEquals('new_avatar.jpg', $user['avatar']);

        $stmt = $this->pdo->query("SELECT COUNT(*) FROM users");
        $this->assertEquals(1, $stmt->fetchColumn());
    }
}
