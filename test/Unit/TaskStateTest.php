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
}
