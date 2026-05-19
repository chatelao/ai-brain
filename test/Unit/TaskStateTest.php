<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Task;
use App\Database;

class TaskStateTest extends TestCase
{
    private $taskModel;

    protected function setUp(): void
    {
        $db = $this->createMock(Database::class);
        $this->taskModel = new Task($db);
    }

    public function testResolveStatusClosed()
    {
        $task = ['github_state' => 'closed', 'jules_status' => 'coding'];
        $this->assertEquals(Task::STATUS_FINISHED, $this->taskModel->resolveStatus($task));
    }

    public function testResolveStatusJulesFailed()
    {
        $task = ['github_state' => 'open', 'jules_status' => 'failed'];
        $this->assertEquals(Task::STATUS_FAILED_JULES, $this->taskModel->resolveStatus($task));
    }

    public function testResolveStatusJulesImplementedNoPr()
    {
        $task = ['github_state' => 'open', 'jules_status' => 'finished', 'pr_url' => null];
        $this->assertEquals(Task::STATUS_IMPLEMENTED, $this->taskModel->resolveStatus($task));
    }

    public function testResolveStatusJulesImplementedWithPrNoChecks()
    {
        $task = ['github_state' => 'open', 'jules_status' => 'finished', 'pr_url' => 'https://github.com/owner/repo/pull/1'];
        $this->assertEquals(Task::STATUS_CHECKING, $this->taskModel->resolveStatus($task));
    }

    public function testResolveStatusWithWebhookCheckSuiteSuccess()
    {
        $task = ['github_state' => 'open', 'jules_status' => 'finished', 'pr_url' => 'https://github.com/owner/repo/pull/1'];
        $checkSuitesData = [
            'check_suite' => [
                'status' => 'completed',
                'conclusion' => 'success'
            ]
        ];
        $this->assertEquals(Task::STATUS_READY, $this->taskModel->resolveStatus($task, null, $checkSuitesData));
    }

    public function testResolveStatusWithApiCheckSuitesFailure()
    {
        $task = ['github_state' => 'open', 'jules_status' => 'finished', 'pr_url' => 'https://github.com/owner/repo/pull/1'];
        $checkSuitesData = [
            'check_suites' => [
                [
                    'status' => 'completed',
                    'conclusion' => 'success'
                ],
                [
                    'status' => 'completed',
                    'conclusion' => 'failure'
                ]
            ]
        ];
        $this->assertEquals(Task::STATUS_FAILED_PR, $this->taskModel->resolveStatus($task, null, $checkSuitesData));
    }

    public function testResolveStatusJulesProcessing()
    {
        $task = ['github_state' => 'open', 'jules_status' => 'researching'];
        $this->assertEquals(Task::STATUS_ANALYZING, $this->taskModel->resolveStatus($task));

        $task['jules_status'] = 'coding';
        $this->assertEquals(Task::STATUS_EXECUTING, $this->taskModel->resolveStatus($task));

        $task['jules_status'] = 'testing';
        $this->assertEquals(Task::STATUS_VERIFYING, $this->taskModel->resolveStatus($task));
    }

    public function testGetStatusColor()
    {
        $this->assertEquals('gray', $this->taskModel->getStatusColor(['status' => Task::STATUS_CREATED]));
        $this->assertEquals('blue', $this->taskModel->getStatusColor(['status' => Task::STATUS_ANALYZING]));
        $this->assertEquals('blue', $this->taskModel->getStatusColor(['status' => Task::STATUS_PLANNING]));
        $this->assertEquals('yellow', $this->taskModel->getStatusColor(['status' => Task::STATUS_EXECUTING]));
        $this->assertEquals('yellow', $this->taskModel->getStatusColor(['status' => Task::STATUS_VERIFYING]));
        $this->assertEquals('yellow', $this->taskModel->getStatusColor(['status' => Task::STATUS_IMPLEMENTED]));
        $this->assertEquals('orange', $this->taskModel->getStatusColor(['status' => Task::STATUS_CHECKING]));
        $this->assertEquals('green', $this->taskModel->getStatusColor(['status' => Task::STATUS_READY]));
        $this->assertEquals('red', $this->taskModel->getStatusColor(['status' => Task::STATUS_FAILED_JULES]));
        $this->assertEquals('red', $this->taskModel->getStatusColor(['status' => Task::STATUS_FAILED_PR]));
        $this->assertEquals('purple', $this->taskModel->getStatusColor(['status' => Task::STATUS_FINISHED, 'github_state' => 'closed']));
        $this->assertEquals('green', $this->taskModel->getStatusColor(['status' => Task::STATUS_FINISHED, 'github_state' => 'open']));
    }

    public function testFindByProjectIdGeneratesCorrectSql()
    {
        $db = $this->createMock(Database::class);
        $pdo = $this->createMock(\PDO::class);
        $stmt = $this->createMock(\PDOStatement::class);

        $db->method('getConnection')->willReturn($pdo);
        $stmt->method('fetchAll')->willReturn([]);

        $capturedSql = '';
        $pdo->method('prepare')->willReturnCallback(function ($sql) use (&$capturedSql, $stmt) {
            $capturedSql = $sql;
            return $stmt;
        });

        $taskModel = new Task($db);
        $taskModel->findByProjectId(1, false);

        $this->assertStringContainsString("t1.status IN ('finished', 'completed')", $capturedSql);
    }

    public function testFindByUserProjectsGeneratesCorrectSql()
    {
        $db = $this->createMock(Database::class);
        $pdo = $this->createMock(\PDO::class);
        $stmt = $this->createMock(\PDOStatement::class);

        $db->method('getConnection')->willReturn($pdo);
        $stmt->method('fetchAll')->willReturn([]);

        $capturedSql = '';
        $pdo->method('prepare')->willReturnCallback(function ($sql) use (&$capturedSql, $stmt) {
            $capturedSql = $sql;
            return $stmt;
        });

        $taskModel = new Task($db);
        $taskModel->findByUserProjects(1, false);

        $this->assertStringContainsString("t.status IN ('finished', 'completed')", $capturedSql);
    }
}
