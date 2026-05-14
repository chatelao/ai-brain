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
            user_id INTEGER PRIMARY KEY AUTOINCREMENT,
            google_id VARCHAR(255) UNIQUE NOT NULL,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            avatar VARCHAR(255), role VARCHAR(20) DEFAULT 'user',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE user_github_accounts (
            user_github_account_id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INT NOT NULL,
            github_username VARCHAR(255) NOT NULL,
            github_token VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            UNIQUE(user_id, github_username)
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

    public function testAddAndGetGitHubAccounts()
    {
        $user = $this->userModel->createOrUpdate([
            'google_id' => 'google-int-123',
            'name' => 'Integration User',
            'email' => 'int@example.com'
        ]);

        $this->userModel->addGitHubAccount($user['user_id'], 'token1', 'user1');
        $this->userModel->addGitHubAccount($user['user_id'], 'token2', 'user2');

        $accounts = $this->userModel->getGitHubAccounts($user['user_id']);
        $this->assertCount(2, $accounts);

        $this->userModel->addGitHubAccount($user['user_id'], 'token1-updated', 'user1');
        $accounts = $this->userModel->getGitHubAccounts($user['user_id']);
        $this->assertCount(2, $accounts);

        foreach ($accounts as $account) {
            if ($account['github_username'] === 'user1') {
                $this->assertEquals('token1-updated', $account['github_token']);
            }
        }
    }
}
