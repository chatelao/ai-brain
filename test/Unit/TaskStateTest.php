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
        // null means no check suite data fetched yet
        $this->assertEquals(Task::STATUS_CHECKING, $this->taskModel->resolveStatus($task, null, null));
    }

    public function testResolveStatusJulesImplementedWithPrEmptyChecks()
    {
        $task = ['github_state' => 'open', 'jules_status' => 'finished', 'pr_url' => 'https://github.com/owner/repo/pull/1'];
        // empty array means data was fetched and there are no check suites
        $checkSuitesData = ['total_count' => 0, 'check_suites' => []];
        $this->assertEquals(Task::STATUS_READY, $this->taskModel->resolveStatus($task, null, $checkSuitesData));
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

    public function testExtractSessionId()
    {
        // Markdown links
        $this->assertEquals('123456789', $this->taskModel->extractSessionId('Jules session: [link](https://jules.google.com/task/123456789)'));
        $this->assertEquals('abc-def', $this->taskModel->extractSessionId('https://jules.googleapis.com/v1alpha/sessions/abc-def'));

        // Case insensitivity
        $this->assertEquals('123', $this->taskModel->extractSessionId('JULES.GOOGLE.COM/TASK/123'));

        // Explicit labels
        $this->assertEquals('xyz', $this->taskModel->extractSessionId('session_id: xyz'));
        $this->assertEquals('xyz', $this->taskModel->extractSessionId('taskId=xyz'));
        $this->assertEquals('xyz', $this->taskModel->extractSessionId('sessionId : xyz'));

        // Long numeric IDs
        $this->assertEquals('12345678901234567890', $this->taskModel->extractSessionId('Session 12345678901234567890 started'));
    }

    public function testExtractPrUrl()
    {
        // Full URL
        $url = 'https://github.com/owner/repo/pull/123';
        $this->assertEquals($url, $this->taskModel->extractPrUrl("PR is at $url"));
        $this->assertEquals($url, $this->taskModel->extractPrUrl($url));

        // Relative reference with repo context
        $repo = 'owner/repo';
        $this->assertEquals("https://github.com/$repo/pull/456", $this->taskModel->extractPrUrl('Fixes #456', $repo));
        $this->assertEquals("https://github.com/$repo/pull/789", $this->taskModel->extractPrUrl('See pull/789', $repo));

        // No repo context, relative reference should return null
        $this->assertNull($this->taskModel->extractPrUrl('Fixes #456'));
    }
}
