<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Task;
use App\Database;

class TaskStatusReproductionTest extends TestCase
{
    private $taskModel;

    protected function setUp(): void
    {
        $db = $this->createMock(Database::class);
        $this->taskModel = new Task($db);
    }

    public function testResolveStatusWithJulesFinishedButCheckSuitesNull()
    {
        $task = [
            'github_state' => 'open',
            'jules_status' => 'finished',
            'pr_url' => 'https://github.com/owner/repo/pull/1'
        ];

        // After fix, if check suites are null but Jules is finished, it should be READY
        $status = $this->taskModel->resolveStatus($task, null, null);
        $this->assertEquals(Task::STATUS_READY, $status, "If check suites are null but Jules is finished, it should be READY");
    }

    public function testResolveStatusWithJulesCompletedButCheckSuitesNull()
    {
        $task = [
            'github_state' => 'open',
            'jules_status' => 'completed',
            'pr_url' => 'https://github.com/owner/repo/pull/1'
        ];

        // After fix, if check suites are null but Jules is completed, it should be READY
        $status = $this->taskModel->resolveStatus($task, null, null);
        $this->assertEquals(Task::STATUS_READY, $status, "If check suites are null but Jules is completed, it should be READY");
    }
}
