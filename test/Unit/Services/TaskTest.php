<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Database;
use App\Task;
use PDO;
use PDOStatement;

class TaskTest extends TestCase
{
    private $db;
    private $pdo;
    private $taskModel;

    protected function setUp(): void
    {
        $this->pdo = $this->createMock(PDO::class);
        $this->db = $this->createMock(Database::class);
        $this->db->method('getConnection')->willReturn($this->pdo);
        $this->taskModel = new Task($this->db);
    }

    public function testFindByProjectId()
    {
        $projectId = 1;
        $expected = [['id' => 1, 'project_id' => $projectId]];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchAll')->willReturn($expected);

        $this->pdo->method('prepare')->willReturn($stmt);

        $result = $this->taskModel->findByProjectId($projectId);
        $this->assertEquals($expected, $result);
    }

    public function testFindById()
    {
        $id = 1;
        $expected = ['id' => $id, 'title' => 'Test Task'];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetch')->willReturn($expected);

        $this->pdo->method('prepare')->willReturn($stmt);

        $result = $this->taskModel->findById($id);
        $this->assertEquals($expected, $result);
    }

    public function testFindByIdNotFound()
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetch')->willReturn(false);

        $this->pdo->method('prepare')->willReturn($stmt);

        $result = $this->taskModel->findById(999);
        $this->assertNull($result);
    }

    public function testUpdateStatus()
    {
        $id = 1;
        $status = 'completed';

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $this->pdo->method('prepare')->willReturn($stmt);

        $result = $this->taskModel->updateStatus($id, $status);
        $this->assertTrue($result);
    }

    public function testUpdateAgentResponse()
    {
        $id = 1;
        $response = 'Agent result';

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $this->pdo->method('prepare')->willReturn($stmt);

        $result = $this->taskModel->updateAgentResponse($id, $response);
        $this->assertTrue($result);
    }

    public function testCreate()
    {
        $data = [
            'project_id' => 1,
            'issue_number' => 101,
            'title' => 'Test Issue'
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $this->pdo->method('prepare')->willReturn($stmt);

        $result = $this->taskModel->create($data);
        $this->assertTrue($result);
    }
}
