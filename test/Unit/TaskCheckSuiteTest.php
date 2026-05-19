<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Task;
use App\Database;

class TaskCheckSuiteTest extends TestCase
{
    private $taskModel;

    protected function setUp(): void
    {
        $db = $this->createMock(Database::class);
        $this->taskModel = new Task($db);
    }

    public function testResolveStatusWithStaleCheckSuite()
    {
        $task = [
            'github_state' => 'open',
            'jules_status' => 'finished',
            'pr_url' => 'https://github.com/owner/repo/pull/1',
            'status' => Task::STATUS_CHECKING
        ];

        $checkSuitesData = [
            'check_suites' => [
                [
                    'status' => 'completed',
                    'conclusion' => 'success'
                ],
                [
                    'status' => 'completed',
                    'conclusion' => 'stale'
                ]
            ]
        ];

        // After fix, 'stale' is handled as successful/non-blocking
        $status = $this->taskModel->resolveStatus($task, null, $checkSuitesData);
        $this->assertEquals(Task::STATUS_READY, $status, "Stale check suite should now allow STATUS_READY");
    }

    public function testResolveStatusWithMultipleSuccessfulSuites()
    {
        $task = [
            'github_state' => 'open',
            'jules_status' => 'finished',
            'pr_url' => 'https://github.com/owner/repo/pull/1',
            'status' => Task::STATUS_CHECKING
        ];

        $checkSuitesData = [
            'check_suites' => [
                [
                    'status' => 'completed',
                    'conclusion' => 'success'
                ],
                [
                    'status' => 'completed',
                    'conclusion' => 'neutral'
                ]
            ]
        ];

        $status = $this->taskModel->resolveStatus($task, null, $checkSuitesData);
        $this->assertEquals(Task::STATUS_READY, $status);
    }
}
