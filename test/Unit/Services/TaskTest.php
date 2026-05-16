<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Database;
use App\Task;
use App\GitHubService;
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
        $expected = [['task_id' => 1, 'project_id' => $projectId]];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchAll')->willReturn($expected);

        $this->pdo->method('prepare')->willReturn($stmt);

        $result = $this->taskModel->findByProjectId($projectId);
        $this->assertEquals($expected, $result);
    }

    public function testFindById()
    {
        $id = 1;
        $expected = ['task_id' => $id, 'title' => 'Test Task'];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetch')->willReturn($expected);

        $this->pdo->method('prepare')->willReturn($stmt);

        $result = $this->taskModel->findById($id);
        $this->assertEquals($expected, $result);
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

    public function testSyncProjectTasks()
    {
        $userId = 1;
        $projectId = 1;
        $repo = 'owner/repo';

        $issues = [
            ['number' => 1, 'title' => 'Issue 1', 'body' => 'Body 1'],
            ['number' => 2, 'title' => 'PR 1', 'body' => 'Body 2', 'pull_request' => []],
            ['number' => 3, 'title' => 'Issue 2', 'body' => 'Body 3'],
        ];

        $githubService = $this->createMock(GitHubService::class);
        $githubService->method('listIssues')->with($repo)->willReturn($issues);

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $this->pdo->method('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->willReturn('mysql');
        $this->pdo->method('prepare')->willReturn($stmt);

        // Expect 2 calls for issues, skip PR
        $stmt->expects($this->exactly(2))->method('execute');

        $result = $this->taskModel->syncProjectTasks($userId, $projectId, $repo, $githubService);
        $this->assertTrue($result);
    }
}
