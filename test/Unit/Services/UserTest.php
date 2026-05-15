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
                      if (str_contains($sql, "SELECT * FROM users WHERE user_id = ?")) return $stmt3;
                      return null;
                  });

        $this->pdo->method('lastInsertId')->willReturn("2");

        $result = $this->userModel->createOrUpdate($userData);
        $this->assertEquals(2, $result['id']);
        $this->assertEquals('New User', $result['name']);
    }

    public function testGenerateTelegramLinkToken()
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
             ->method('execute')
             ->with($this->callback(function($params) {
                 return strlen($params[0]) === 32 && $params[1] === 1;
             }));

        $this->pdo->expects($this->once())
                  ->method('prepare')
                  ->with($this->stringContains("UPDATE users SET telegram_link_token = ? WHERE user_id = ?"))
                  ->willReturn($stmt);

        $token = $this->userModel->generateTelegramLinkToken(1);
        $this->assertEquals(32, strlen($token));
    }

    public function testLinkTelegramAccountSuccess()
    {
        // 1. SELECT user by token
        $stmt1 = $this->createMock(PDOStatement::class);
        $stmt1->method('fetch')->willReturn(['id' => 10]);

        // 2. INSERT into user_telegram_accounts
        $stmt2 = $this->createMock(PDOStatement::class);
        $stmt2->method('execute')->willReturn(true);

        // 3. UPDATE users to clear token
        $stmt3 = $this->createMock(PDOStatement::class);

        $this->pdo->method('prepare')
                  ->willReturnCallback(function($sql) use ($stmt1, $stmt2, $stmt3) {
                      if (str_contains($sql, "SELECT id FROM users WHERE telegram_link_token = ?")) return $stmt1;
                      if (str_contains($sql, "INSERT INTO user_telegram_accounts")) return $stmt2;
                      if (str_contains($sql, "UPDATE users SET telegram_link_token = NULL")) return $stmt3;
                      return null;
                  });

        $result = $this->userModel->linkTelegramAccount('valid_token', 123456);
        $this->assertTrue($result);
    }

    public function testLinkTelegramAccountFailure()
    {
        // 1. SELECT user by token returns null
        $stmt1 = $this->createMock(PDOStatement::class);
        $stmt1->method('fetch')->willReturn(null);

        $this->pdo->method('prepare')
                  ->willReturnCallback(function($sql) use ($stmt1) {
                      if (str_contains($sql, "SELECT id FROM users WHERE telegram_link_token = ?")) return $stmt1;
                      return null;
                  });

        $result = $this->userModel->linkTelegramAccount('invalid_token', 123456);
        $this->assertFalse($result);
    }
}
