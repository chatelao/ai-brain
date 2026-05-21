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

        // Reverting to stay in CHECKING if null
        $status = $this->taskModel->resolveStatus($task, null, null);
        $this->assertEquals(Task::STATUS_CHECKING, $status, "If check suites are null, it stays in CHECKING");
    }

    public function testResolveStatusWithJulesCompletedButCheckSuitesNull()
    {
        $task = [
            'github_state' => 'open',
            'jules_status' => 'completed',
            'pr_url' => 'https://github.com/owner/repo/pull/1'
        ];

        // Reverting to stay in CHECKING if null
        $status = $this->taskModel->resolveStatus($task, null, null);
        $this->assertEquals(Task::STATUS_CHECKING, $status, "If check suites are null, it stays in CHECKING");
    }
}
