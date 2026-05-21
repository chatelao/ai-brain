<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Task;
use App\Database;

class TaskSkippedTest extends TestCase
{
    private $taskModel;

    protected function setUp(): void
    {
        $db = $this->createMock(Database::class);
        $this->taskModel = new Task($db);
    }

    public function testResolveStatusWithSkippedCheckSuite()
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
                    'conclusion' => 'skipped'
                ]
            ]
        ];

        $status = $this->taskModel->resolveStatus($task, null, $checkSuitesData);
        $this->assertEquals(Task::STATUS_READY, $status, "A single skipped check suite should result in STATUS_READY");
    }

    public function testResolveStatusWithMixedSuccessAndSkipped()
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
                    'conclusion' => 'skipped'
                ]
            ]
        ];

        $status = $this->taskModel->resolveStatus($task, null, $checkSuitesData);
        $this->assertEquals(Task::STATUS_READY, $status, "Mixed success and skipped should result in STATUS_READY");
    }

    public function testResolveStatusWithStatusSkipped()
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
                    'status' => 'skipped',
                    'conclusion' => 'neutral'
                ]
            ]
        ];

        $status = $this->taskModel->resolveStatus($task, null, $checkSuitesData);
        $this->assertEquals(Task::STATUS_READY, $status, "Status 'skipped' should also be considered okay and result in STATUS_READY");
    }

    public function testResolveStatusWithNoSuitesAndFinishedJules()
    {
        // This is the case where jules finished but no check suites were reported yet
        $task = [
            'github_state' => 'open',
            'jules_status' => 'finished',
            'pr_url' => 'https://github.com/owner/repo/pull/1',
            'status' => Task::STATUS_CHECKING
        ];

        $checkSuitesData = null; // Or empty

        $status = $this->taskModel->resolveStatus($task, null, $checkSuitesData);
        // Current implementation returns STATUS_CHECKING if $checkSuitesData is null
        $this->assertEquals(Task::STATUS_CHECKING, $status);
    }
}
