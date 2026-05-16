<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Database;
use App\Project;
use PDO;
use PDOStatement;

class ProjectTest extends TestCase
{
    private $db;
    private $pdo;
    private $projectModel;

    protected function setUp(): void
    {
        $this->pdo = $this->createMock(PDO::class);
        $this->db = $this->createMock(Database::class);
        $this->db->method('getConnection')->willReturn($this->pdo);

        $this->projectModel = new Project($this->db);
    }

    public function testCreateSuccess()
    {
        $stmtCheck = $this->createMock(PDOStatement::class);
        $stmtCheck->method('fetch')->willReturn(['github_account_id' => 1]);

        $stmtInsert = $this->createMock(PDOStatement::class);
        $stmtInsert->method('execute')->willReturn(true);

        $this->pdo->method('prepare')->willReturnMap([
            ['SELECT github_account_id FROM user_github_accounts WHERE github_account_id = ? AND user_id = ?', $stmtCheck],
            ['INSERT INTO projects (user_id, github_account_id, github_repo, webhook_secret) VALUES (?, ?, ?, ?)', $stmtInsert]
        ]);

        $result = $this->projectModel->create(1, 1, 'owner/repo');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('project_id', $result);
        $this->assertArrayHasKey('webhook_secret', $result);
    }

    public function testCreateFailureInvalidAccount()
    {
        $stmtCheck = $this->createMock(PDOStatement::class);
        $stmtCheck->method('fetch')->willReturn(null);

        $this->pdo->method('prepare')->willReturn($stmtCheck);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Invalid GitHub account selected.");

        $this->projectModel->create(1, 1, 'owner/repo');
    }

    public function testFindById()
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetch')->willReturn(['project_id' => 1, 'github_repo' => 'owner/repo']);

        $this->pdo->method('prepare')->with($this->stringContains('SELECT p.*, a.github_token, a.github_username'))
            ->willReturn($stmt);

        $project = $this->projectModel->findById(1);
        $this->assertEquals('owner/repo', $project['github_repo']);
    }
}
