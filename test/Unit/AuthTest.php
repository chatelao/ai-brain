<?php

namespace Test\Unit;

use PHPUnit\Framework\TestCase;
use App\Auth;
use App\Database;
use PDO;

class AuthTest extends TestCase
{
    private $pdo;
    private $db;
    private $auth;

    protected function setUp(): void
    {
        // Use in-memory SQLite for testing
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec("CREATE TABLE users (user_id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT)");
        $this->pdo->exec("CREATE TABLE user_refresh_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            token_hash TEXT,
            expires_at DATETIME
        )");

        $this->pdo->exec("INSERT INTO users (user_id, email) VALUES (123, 'test@example.com')");

        $this->db = $this->createMock(Database::class);
        $this->db->method('getConnection')->willReturn($this->pdo);

        putenv('JWT_SECRET=a_very_long_and_secure_secret_for_testing_purposes_only_1234567890');

        $this->auth = new Auth($this->db);
    }

    public function testGenerateAndValidateToken()
    {
        $userId = 123;
        $token = $this->auth->generateToken($userId);

        $validatedUserId = $this->auth->validateToken($token);
        $this->assertEquals($userId, $validatedUserId);
    }

    public function testGenerateRefreshToken()
    {
        $userId = 123;
        $token = $this->auth->generateRefreshToken($userId);
        $this->assertNotEmpty($token);

        $stmt = $this->pdo->query("SELECT * FROM user_refresh_tokens WHERE user_id = 123");
        $row = $stmt->fetch();
        $this->assertNotEmpty($row);
        $this->assertEquals(hash('sha256', $token), $row['token_hash']);
    }

    public function testValidateRefreshTokenSuccess()
    {
        $userId = 123;
        $token = $this->auth->generateRefreshToken($userId);

        $validatedUserId = $this->auth->validateRefreshToken($token);
        $this->assertEquals($userId, $validatedUserId);
    }

    public function testValidateRefreshTokenFailure()
    {
        $userId = 123;
        $this->auth->generateRefreshToken($userId);

        $validatedUserId = $this->auth->validateRefreshToken('wrong_token');
        $this->assertNull($validatedUserId);
    }

    public function testValidateRefreshTokenExpired()
    {
        $userId = 123;
        $token = 'expired_token';
        $hash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() - 3600); // 1 hour ago

        $stmt = $this->pdo->prepare("INSERT INTO user_refresh_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $hash, $expiresAt]);

        $validatedUserId = $this->auth->validateRefreshToken($token);
        $this->assertNull($validatedUserId);
    }

    public function testRevokeRefreshToken()
    {
        $userId = 123;
        $token = $this->auth->generateRefreshToken($userId);

        $this->auth->revokeRefreshToken($token);

        $stmt = $this->pdo->query("SELECT COUNT(*) FROM user_refresh_tokens");
        $this->assertEquals(0, $stmt->fetchColumn());
    }
}
