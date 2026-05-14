<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Database;
use App\Project;
use PDO;
use PDOStatement;
use Exception;

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
        $userId = 1;
        $accountId = 10;
        $repo = 'owner/repo';

        $stmt1 = $this->createMock(PDOStatement::class);
        $stmt1->method('fetch')->willReturn(['user_github_account_id' => 10]);

        $stmt2 = $this->createMock(PDOStatement::class);
        $stmt2->method('execute')->willReturn(true);

        $this->pdo->method('prepare')
            ->willReturnCallback(function($sql) use ($stmt1, $stmt2) {
                if (str_contains($sql, "SELECT user_github_account_id FROM user_github_accounts")) return $stmt1;
                if (str_contains($sql, "INSERT INTO projects")) return $stmt2;
                return null;
            });

        $result = $this->projectModel->create($userId, $accountId, $repo);
        $this->assertTrue($result);
    }

    public function testCreateInvalidAccount()
    {
        $userId = 1;
        $accountId = 10;
        $repo = 'owner/repo';

        $stmt1 = $this->createMock(PDOStatement::class);
        $stmt1->method('fetch')->willReturn(false);

        $this->pdo->method('prepare')->willReturn($stmt1);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Invalid GitHub account selected.");

        $this->projectModel->create($userId, $accountId, $repo);
    }

    public function testFindByRepo()
    {
        $repo = 'owner/repo';
        $expected = [['project_id' => 1, 'github_repo' => $repo]];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchAll')->willReturn($expected);

        $this->pdo->method('prepare')->willReturn($stmt);

        $result = $this->projectModel->findByRepo($repo);
        $this->assertEquals($expected, $result);
    }

    public function testFindByUserId()
    {
        $userId = 1;
        $expected = [['project_id' => 1, 'user_id' => $userId]];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchAll')->willReturn($expected);

        $this->pdo->method('prepare')->willReturn($stmt);

        $result = $this->projectModel->findByUserId($userId);
        $this->assertEquals($expected, $result);
    }

    public function testFindById()
    {
        $id = 1;
        $expected = ['project_id' => $id, 'github_repo' => 'owner/repo'];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetch')->willReturn($expected);

        $this->pdo->method('prepare')->willReturn($stmt);

        $result = $this->projectModel->findById($id);
        $this->assertEquals($expected, $result);
    }

    public function testFindByIdNotFound()
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetch')->willReturn(false);

        $this->pdo->method('prepare')->willReturn($stmt);

        $result = $this->projectModel->findById(999);
        $this->assertNull($result);
    }

    public function testDelete()
    {
        $id = 1;
        $userId = 1;

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $this->pdo->method('prepare')->willReturn($stmt);

        $result = $this->projectModel->delete($id, $userId);
        $this->assertTrue($result);
    }
}
