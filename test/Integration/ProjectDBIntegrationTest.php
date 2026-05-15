<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Database;
use App\Project;
use App\User;
use PDO;

class ProjectDBIntegrationTest extends TestCase
{
    private $db;
    private $pdo;
    private $projectModel;
    private $userModel;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec("CREATE TABLE users (
            user_id TEXT PRIMARY KEY,
            google_id VARCHAR(255) UNIQUE NOT NULL,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            avatar VARCHAR(255), role VARCHAR(20) DEFAULT 'user',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE user_github_accounts (
            github_account_id TEXT PRIMARY KEY,
            user_id TEXT,
            github_username VARCHAR(255) NOT NULL,
            github_token VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            UNIQUE(user_id, github_username)
        )");

        $this->pdo->exec("CREATE TABLE projects (
            project_id TEXT PRIMARY KEY,
            user_id TEXT,
            github_account_id TEXT,
            github_repo VARCHAR(255) NOT NULL,
            webhook_secret VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            FOREIGN KEY (github_account_id) REFERENCES user_github_accounts(github_account_id) ON DELETE CASCADE
        )");

        $this->db = $this->createMock(Database::class);
        $this->db->method('getConnection')->willReturn($this->pdo);

        $this->projectModel = new Project($this->db);
        $this->userModel = new User($this->db);
    }

    private function setupUserAndAccount()
    {
        $user = $this->userModel->createOrUpdate([
            'google_id' => 'google-123',
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);
        $this->userModel->addGitHubAccount($user['user_id'], 'token-123', 'github-user');
        $accounts = $this->userModel->getGitHubAccounts($user['user_id']);
        return ['user_id' => $user['user_id'], 'account_id' => $accounts[0]['github_account_id']];
    }

    public function testCreateProject()
    {
        $setup = $this->setupUserAndAccount();
        $accountId = $setup['account_id'];
        $userId = $setup['user_id'];
        $repo = 'owner/repo';

        $result = $this->projectModel->create($userId, $accountId, $repo);
        $this->assertTrue($result);

        $projects = $this->projectModel->findByUserId($userId);
        $this->assertCount(1, $projects);
        $this->assertEquals($repo, $projects[0]['github_repo']);
        $this->assertEquals('github-user', $projects[0]['github_username']);
        $this->assertNotEmpty($projects[0]['webhook_secret']);
    }

    public function testFindByRepo()
    {
        $setup = $this->setupUserAndAccount();
        $accountId = $setup['account_id'];
        $userId = $setup['user_id'];
        $repo = 'owner/repo';
        $this->projectModel->create($userId, $accountId, $repo);

        $projects = $this->projectModel->findByRepo($repo);
        $this->assertCount(1, $projects);
        $this->assertEquals($repo, $projects[0]['github_repo']);
        $this->assertEquals('token-123', $projects[0]['github_token']);
    }

    public function testDeleteProject()
    {
        $setup = $this->setupUserAndAccount();
        $accountId = $setup['account_id'];
        $userId = $setup['user_id'];
        $repo = 'owner/repo';
        $this->projectModel->create($userId, $accountId, $repo);
        $projects = $this->projectModel->findByUserId($userId);
        $projectId = $projects[0]['project_id'];

        $result = $this->projectModel->delete($projectId, $userId);
        $this->assertTrue($result);

        $projects = $this->projectModel->findByUserId($userId);
        $this->assertCount(0, $projects);
    }

    public function testCreateProjectWithUnauthorizedAccount()
    {
        $setup = $this->setupUserAndAccount(); // Sets up User 1 and Account 1

        // Setup User 2
        $user2 = $this->userModel->createOrUpdate([
            'google_id' => 'google-456',
            'name' => 'User 2',
            'email' => 'user2@example.com'
        ]);
        $userId2 = $user2['user_id'];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Invalid GitHub account selected.");

        // User 2 tries to use Account 1
        $this->projectModel->create($userId2, $setup['account_id'], 'user2/repo');
    }
}
