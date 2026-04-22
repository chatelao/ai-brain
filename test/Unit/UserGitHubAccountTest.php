<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Database;
use App\User;
use PDO;
use PDOStatement;

class UserGitHubAccountTest extends TestCase
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

    public function testAddGitHubAccount()
    {
        $this->pdo->method('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->willReturn('mysql');

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
             ->method('execute')
             ->with([1, 'token123', 'user123', 'token123'])
             ->willReturn(true);

        $this->pdo->expects($this->once())
                  ->method('prepare')
                  ->with($this->stringContains("INSERT INTO user_github_accounts"))
                  ->willReturn($stmt);

        $result = $this->userModel->addGitHubAccount(1, 'token123', 'user123');
        $this->assertTrue($result);
    }

    public function testGetGitHubAccounts()
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
             ->method('execute')
             ->with([1]);
        $stmt->expects($this->once())
             ->method('fetchAll')
             ->willReturn([
                 ['id' => 1, 'user_id' => 1, 'github_username' => 'user1', 'github_token' => 'token1'],
                 ['id' => 2, 'user_id' => 1, 'github_username' => 'user2', 'github_token' => 'token2']
             ]);

        $this->pdo->expects($this->once())
                  ->method('prepare')
                  ->with($this->stringContains("SELECT * FROM user_github_accounts"))
                  ->willReturn($stmt);

        $accounts = $this->userModel->getGitHubAccounts(1);
        $this->assertCount(2, $accounts);
        $this->assertEquals('user1', $accounts[0]['github_username']);
        $this->assertEquals('user2', $accounts[1]['github_username']);
    }
}
