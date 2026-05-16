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
        $stmt->expects($this->once())->method('execute')->with(['google-123']);
        $stmt->method('fetch')->willReturn(['user_id' => 1, 'name' => 'Test User']);

        $this->pdo->method('prepare')->with($this->stringContains('SELECT * FROM users WHERE google_id = ?'))
            ->willReturn($stmt);

        $user = $this->userModel->findByGoogleId('google-123');
        $this->assertEquals('Test User', $user['name']);
    }

    public function testFindById()
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with([1]);
        $stmt->method('fetch')->willReturn(['user_id' => 1, 'name' => 'Test User']);

        $this->pdo->method('prepare')->with($this->stringContains('SELECT * FROM users WHERE user_id = ?'))
            ->willReturn($stmt);

        $user = $this->userModel->findById(1);
        $this->assertEquals('Test User', $user['name']);
    }

    public function testCreateOrUpdateCreatesNewUser()
    {
        // 1. Mock findByGoogleId call (returns null)
        $stmtFind = $this->createMock(PDOStatement::class);
        $stmtFind->method('fetch')->willReturn(null);

        // 2. Mock INSERT
        $stmtInsert = $this->createMock(PDOStatement::class);
        $stmtInsert->method('execute')->willReturn(true);

        // 3. Mock findById call
        $stmtFindById = $this->createMock(PDOStatement::class);
        $stmtFindById->method('fetch')->willReturn(['user_id' => 1, 'google_id' => 'g1', 'name' => 'N', 'email' => 'e']);

        $this->pdo->method('prepare')->willReturnMap([
            ['SELECT * FROM users WHERE google_id = ?', $stmtFind],
            ['INSERT INTO users (google_id, name, email, avatar, role) VALUES (?, ?, ?, ?, ?)', $stmtInsert],
            ['SELECT * FROM users WHERE user_id = ?', $stmtFindById]
        ]);

        $this->pdo->method('lastInsertId')->willReturn("1");

        $data = ['google_id' => 'g1', 'name' => 'N', 'email' => 'e'];
        $user = $this->userModel->createOrUpdate($data);

        $this->assertNotNull($user);
        $this->assertEquals('g1', $user['google_id']);
    }

    public function testGenerateTelegramLinkToken()
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute');

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('UPDATE users SET telegram_link_token = ? WHERE user_id = ?'))
            ->willReturn($stmt);

        $token = $this->userModel->generateTelegramLinkToken(1);
        $this->assertNotEmpty($token);
    }

    public function testLinkTelegramAccountSuccess()
    {
        $stmtFind = $this->createMock(PDOStatement::class);
        $stmtFind->method('fetch')->willReturn(['user_id' => 1]);

        $stmtInsert = $this->createMock(PDOStatement::class);
        $stmtInsert->method('execute')->willReturn(true);

        $stmtUpdate = $this->createMock(PDOStatement::class);
        $stmtUpdate->method('execute')->willReturn(true);

        $this->pdo->method('prepare')->willReturnMap([
            ['SELECT user_id FROM users WHERE telegram_link_token = ?', $stmtFind],
            ['INSERT INTO user_telegram_accounts (user_id, telegram_chat_id) VALUES (?, ?)', $stmtInsert],
            ['UPDATE users SET telegram_link_token = NULL WHERE user_id = ?', $stmtUpdate]
        ]);

        $success = $this->userModel->linkTelegramAccount('token-123', 999);
        $this->assertTrue($success);
    }

    public function testLinkTelegramAccountFailure()
    {
        $stmtFind = $this->createMock(PDOStatement::class);
        $stmtFind->method('fetch')->willReturn(null);

        $this->pdo->method('prepare')->with($this->stringContains('SELECT user_id FROM users WHERE telegram_link_token = ?'))
            ->willReturn($stmtFind);

        $success = $this->userModel->linkTelegramAccount('bad-token', 999);
        $this->assertFalse($success);
    }

    public function testUpdateTelegramConfig()
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with(['bot-token', 'secret-123', 1])
            ->willReturn(true);

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('UPDATE users SET telegram_bot_token = ?, telegram_webhook_secret = ? WHERE user_id = ?'))
            ->willReturn($stmt);

        $success = $this->userModel->updateTelegramConfig(1, 'bot-token', 'secret-123');
        $this->assertTrue($success);
    }
}
