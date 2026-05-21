<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Database;
use App\Project;
use App\User;
use App\GitHubService;
use PDO;
use Exception;
use Tests\TestDatabaseTrait;

class ProjectRepositoryValidationTest extends TestCase
{
    use TestDatabaseTrait;

    private $db;
    private $pdo;
    private $projectModel;
    private $userModel;

    protected function setUp(): void
    {
        $this->pdo = $this->getTestPdo();
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        $this->pdo->exec("DROP TABLE IF EXISTS projects");
        $this->pdo->exec("DROP TABLE IF EXISTS user_github_accounts");
        $this->pdo->exec("DROP TABLE IF EXISTS users");

        $pk = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
        $timestamp = $driver === 'sqlite' ? 'DATETIME DEFAULT CURRENT_TIMESTAMP' : 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP';

        $this->pdo->exec("CREATE TABLE users (
            user_id $pk,
            google_id VARCHAR(255) UNIQUE NOT NULL,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            avatar VARCHAR(255), role VARCHAR(20) DEFAULT 'user',
            created_at $timestamp
        )");

        $this->pdo->exec("CREATE TABLE user_github_accounts (
            github_account_id $pk,
            user_id INT NOT NULL,
            github_username VARCHAR(255) NOT NULL,
            github_token VARCHAR(255) NOT NULL,
            created_at $timestamp,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            UNIQUE(user_id, github_username)
        )");

        $this->pdo->exec("CREATE TABLE projects (
            project_id $pk,
            user_id INT NOT NULL,
            github_account_id INT NOT NULL,
            github_repo VARCHAR(255) NOT NULL,
            webhook_secret VARCHAR(255),
            created_at $timestamp,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            FOREIGN KEY (github_account_id) REFERENCES user_github_accounts(github_account_id) ON DELETE CASCADE
        )");

        $this->db = $this->createMock(Database::class);
        $this->db->method('getConnection')->willReturn($this->pdo);

        $this->projectModel = new Project($this->db);
        $this->userModel = new User($this->db);

        $this->userModel->createOrUpdate([
            'google_id' => 'google-123',
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);
        $this->userModel->addGitHubAccount(1, 'token-123', 'github-user');
    }

    public function testCreateProjectWithValidRepo()
    {
        $githubService = $this->createMock(GitHubService::class);
        $githubService->expects($this->once())
            ->method('getRepository')
            ->with('owner/repo')
            ->willReturn(['id' => 123]);

        $result = $this->projectModel->create(1, 1, 'owner/repo', $githubService);
        $this->assertIsArray($result);
        $this->assertGreaterThan(0, $result['project_id']);
    }

    public function testCreateProjectWithInvalidRepo()
    {
        $githubService = $this->createMock(GitHubService::class);
        $githubService->expects($this->once())
            ->method('getRepository')
            ->with('owner/invalid')
            ->willThrowException(new Exception("Not Found", 404));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Not Found");

        $this->projectModel->create(1, 1, 'owner/invalid', $githubService);
    }

    public function testUpdateProjectWithValidRepo()
    {
        $githubService = $this->createMock(GitHubService::class);
        $githubService->method('getRepository')
            ->willReturn(['id' => 123]);

        $res = $this->projectModel->create(1, 1, 'owner/old', $githubService);
        $projectId = $res['project_id'];

        $githubService = $this->createMock(GitHubService::class);
        $githubService->expects($this->once())
            ->method('getRepository')
            ->with('owner/new')
            ->willReturn(['id' => 456]);

        $result = $this->projectModel->update($projectId, 1, 1, 'owner/new', $githubService);
        $this->assertTrue($result);

        $project = $this->projectModel->findById($projectId);
        $this->assertEquals('owner/new', $project['github_repo']);
    }

    public function testUpdateProjectWithInvalidRepo()
    {
        $githubService = $this->createMock(GitHubService::class);
        $githubService->method('getRepository')
            ->willReturn(['id' => 123]);

        $res = $this->projectModel->create(1, 1, 'owner/old', $githubService);
        $projectId = $res['project_id'];

        $githubService = $this->createMock(GitHubService::class);
        $githubService->expects($this->once())
            ->method('getRepository')
            ->with('owner/invalid')
            ->willThrowException(new Exception("Not Found", 404));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Not Found");

        $this->projectModel->update($projectId, 1, 1, 'owner/invalid', $githubService);
    }
}
