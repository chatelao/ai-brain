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

    public function testCreate()
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
             ->method('execute')
             ->with([1, 'owner/repo'])
             ->willReturn(true);

        $this->pdo->expects($this->once())
                  ->method('prepare')
                  ->with($this->stringContains('INSERT INTO projects'))
                  ->willReturn($stmt);

        $result = $this->projectModel->create(1, 'owner/repo');
        $this->assertTrue($result);
    }

    public function testFindByUserId()
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
             ->method('execute')
             ->with([1]);
        $stmt->expects($this->once())
             ->method('fetchAll')
             ->willReturn([['id' => 1, 'github_repo' => 'owner/repo']]);

        $this->pdo->expects($this->once())
                  ->method('prepare')
                  ->with($this->stringContains('SELECT * FROM projects WHERE user_id = ?'))
                  ->willReturn($stmt);

        $result = $this->projectModel->findByUserId(1);
        $this->assertCount(1, $result);
        $this->assertEquals('owner/repo', $result[0]['github_repo']);
    }
}
