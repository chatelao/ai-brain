<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Database;
use App\User;
use PDO;
use PDOStatement;

class UserTest extends TestCase
{
    private $db;
    private $pdo;
    private $userModel;

    protected function setUp(): void
    {
        $this->pdo = $this->createMock(PDO::class);
        $this->db = $this->createMock(Database::class);
        $this->db->method('getConnection')->willReturn($this->pdo);
        $this->userModel = new User($this->db);
    }

    public function testFindByGoogleId()
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
             ->method('execute')
             ->with(['google-123']);
        $stmt->expects($this->once())
             ->method('fetch')
             ->willReturn(['id' => 1, 'google_id' => 'google-123', 'name' => 'Test User']);

        $this->pdo->expects($this->once())
                  ->method('prepare')
                  ->willReturn($stmt);

        $result = $this->userModel->findByGoogleId('google-123');
        $this->assertEquals('Test User', $result['name']);
    }

    public function testCreateOrUpdateCreatesNewUser()
    {
        $userData = [
            'google_id' => 'google-456',
            'name' => 'New User',
            'email' => 'new@example.com',
            'avatar' => 'avatar.jpg'
        ];

        // 1. findByGoogleId returns null
        $stmt1 = $this->createMock(PDOStatement::class);
        $stmt1->method('fetch')->willReturn(null);

        // 2. INSERT
        $stmt2 = $this->createMock(PDOStatement::class);

        // 3. findById after insert
        $stmt3 = $this->createMock(PDOStatement::class);
        $stmt3->method('fetch')->willReturn(['id' => 2, 'google_id' => 'google-456', 'name' => 'New User']);

        $this->pdo->method('prepare')
                  ->willReturnCallback(function($sql) use ($stmt1, $stmt2, $stmt3) {
                      if (str_contains($sql, "SELECT * FROM users WHERE google_id = ?")) return $stmt1;
                      if (str_contains($sql, "INSERT INTO users")) return $stmt2;
                      if (str_contains($sql, "SELECT * FROM users WHERE id = ?")) return $stmt3;
                      return null;
                  });

        $this->pdo->method('lastInsertId')->willReturn("2");

        $result = $this->userModel->createOrUpdate($userData);
        $this->assertEquals(2, $result['id']);
        $this->assertEquals('New User', $result['name']);
    }

    public function testUpdateGitHubInfo()
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
             ->method('execute')
             ->with(['token123', 'githubuser', 1])
             ->willReturn(true);

        $this->pdo->expects($this->once())
                  ->method('prepare')
                  ->with($this->stringContains('UPDATE users SET github_token = ?, github_username = ? WHERE id = ?'))
                  ->willReturn($stmt);

        $result = $this->userModel->updateGitHubInfo(1, 'token123', 'githubuser');
        $this->assertTrue($result);
    }
}
