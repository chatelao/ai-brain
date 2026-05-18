<?php

namespace App;

use PDO;

class WebhookHandler
{
    public function __construct(private Database $db)
    {
    }

    public function verifySignature(string $payload, string $signature, string $secret): bool
    {
        $hash = 'sha256=' . hash_hmac('sha256', $payload, $secret);
        return hash_equals($hash, $signature);
    }

    public function handle(array $project, array $event, ?GitHubService $githubService = null, ?NotificationService $notificationService = null, ?JulesService $julesService = null): bool
    {
        $action = $event['action'] ?? '';
        $issue = $event['issue'] ?? null;
        $pullRequest = $event['pull_request'] ?? null;
        $checkSuite = $event['check_suite'] ?? null;

        $taskModel = new Task($this->db);

        if ($pullRequest) {
            return $this->handlePullRequest($project, $event, $notificationService, $githubService);
        }

        if ($checkSuite) {
            return $this->handleCheckSuite($project, $event, $taskModel, $notificationService);
        }

        if (!$issue) {
            return true;
        }

        if ($action === 'deleted') {
            $result = $taskModel->deleteByIssueNumber($project['project_id'], $issue['number']);
            if ($result && $notificationService) {
                $notificationService->notify($project['user_id'], 'github_issue', "🗑️ Issue Deleted: #" . $issue['number'], "Issue \"" . ($issue['title'] ?? 'Unknown') . "\" was deleted in " . $project['github_repo'], [
                    'project_id' => $project['project_id'],
                    'issue_number' => $issue['number'],
                    'source_url' => $issue['html_url'] ?? ''
                ]);
            }
            return $result;
        }

        if (!in_array($action, ['opened', 'reopened', 'edited', 'closed', 'labeled', 'unlabeled'])) {
            return true;
        }

        $result = $taskModel->upsert($project['user_id'], $project['project_id'], $issue);

        if ($result && $julesService && $githubService) {
            $task = $taskModel->findByIssueNumber($project['project_id'], $issue['number']);
            if ($task) {
                $taskModel->refreshJulesStatus($project['user_id'], $githubService, $julesService, $notificationService, (int)$task['task_id']);
            }
        }

        if ($result && $notificationService) {
            $task = $taskModel->findByIssueNumber($project['project_id'], $issue['number']);

            if ($action === 'opened') {
                $notificationService->notify($project['user_id'], 'github_issue', "🆕 Issue Opened: #" . $issue['number'], "Issue \"" . $issue['title'] . "\" was opened in " . $project['github_repo'], [
                    'task_id' => $task ? $task['task_id'] : null,
                    'project_id' => $project['project_id'],
                    'issue_number' => $issue['number'],
                    'source_url' => $issue['html_url']
                ], ['acknowledge']);
            } elseif ($action === 'closed') {
                $notificationService->notify($project['user_id'], 'github_issue', "🔒 Issue Closed: #" . $issue['number'], "Issue \"" . $issue['title'] . "\" was closed in " . $project['github_repo'], [
                    'project_id' => $project['project_id'],
                    'issue_number' => $issue['number'],
                    'source_url' => $issue['html_url']
                ]);
            } elseif ($action === 'reopened') {
                $notificationService->notify($project['user_id'], 'github_issue', "🔓 Issue Reopened: #" . $issue['number'], "Issue \"" . $issue['title'] . "\" was reopened in " . $project['github_repo'], [
                    'project_id' => $project['project_id'],
                    'issue_number' => $issue['number'],
                    'source_url' => $issue['html_url']
                ]);
            }
        }

        if ($result && $action === 'closed' && $githubService) {
            $this->maybeDuplicateTask($project, $event, $githubService);
        }

        return $result;
    }

    private function maybeDuplicateTask(array $project, array $event, GitHubService $githubService): void
    {
        $issue = $event['issue'] ?? null;
        if (!$issue) {
            return;
        }

        $taskModel = new Task($this->db);
        $task = $taskModel->findByIssueNumber($project['project_id'], $issue['number']);

        if (!$task) {
            $task = [
                'github_data' => json_encode($issue),
                'status' => 'pending',
                'agent_response' => ''
            ];
        }

        if (!$taskModel->hasAutorepeatLabel($task)) {
            return;
        }

        $stateReason = $issue['state_reason'] ?? '';
        if ($stateReason !== 'completed') {
            return;
        }

        // Avoid double duplication using a marker in agent_response
        if (strpos($task['agent_response'] ?? '', '<!-- autorepeat_triggered -->') !== false) {
            return;
        }

        $autorepeatLabelName = '';
        $labels = $issue['labels'] ?? [];
        foreach ($labels as $label) {
            $name = strtolower($label['name'] ?? '');
            if ($name === 'autorepeat' || $name === 'auto-repeat') {
                $autorepeatLabelName = $label['name'];
                break;
            }
        }

        $labelNames = array_filter(
            array_map(fn($l) => $l['name'], $labels),
            fn($name) => strtolower($name) !== 'autorepeat' && strtolower($name) !== 'auto-repeat'
        );

        // Ensure 'Jules' label is present to trigger the agent for the new issue
        $hasJules = false;
        foreach ($labelNames as $ln) {
            if (strtolower($ln) === 'jules') {
                $hasJules = true;
                break;
            }
        }
        if (!$hasJules) {
            $labelNames[] = 'Jules';
        }

        $repo = $event['repository']['full_name'] ?? ($event['repository']['name'] ?? '');
        if ($repo && strpos($repo, '/') === false && isset($event['repository']['owner']['login'])) {
            $repo = $event['repository']['owner']['login'] . '/' . $repo;
        }

        if ($repo) {
            // Mark as triggered BEFORE calling GitHub to minimize race condition window
            if (isset($task['task_id'])) {
                $taskModel->updateAgentResponse(
                    $task['task_id'],
                    ($task['agent_response'] ?? '') . "\n<!-- autorepeat_triggered -->",
                    $task['status']
                );
            }

            $githubService->createIssue($repo, $issue['title'], $issue['body'], array_values($labelNames));
            $githubService->removeLabel($repo, $issue['number'], $autorepeatLabelName);
        }
    }

    private function handlePullRequest(array $project, array $event, ?NotificationService $notificationService, ?GitHubService $githubService = null): bool
    {
        $action = $event['action'] ?? '';
        $pr = $event['pull_request'] ?? null;

        if (!$pr || !$notificationService) {
            return true;
        }

        if (in_array($action, ['opened', 'closed', 'reopened', 'synchronize'])) {
            $emoji = '🔗';
            $actionText = 'Updated';

            if ($action === 'opened') {
                $emoji = '🆕';
                $actionText = 'Opened';
            } elseif ($action === 'closed') {
                $emoji = $pr['merged'] ? '💜' : '❌';
                $actionText = $pr['merged'] ? 'Merged' : 'Closed';
            } elseif ($action === 'reopened') {
                $emoji = '🔓';
                $actionText = 'Reopened';
            } elseif ($action === 'synchronize') {
                $emoji = '🔄';
                $actionText = 'Pushed';
            }

            $notificationService->notify($project['user_id'], 'github_pr', "$emoji PR $actionText: #" . $pr['number'], "Pull Request \"" . $pr['title'] . "\" was $actionText in " . $project['github_repo'], [
                'project_id' => $project['project_id'],
                'pr_number' => $pr['number'],
                'source_url' => $pr['html_url']
            ]);
        }

        // Handle auto-repeat if PR is merged
        if ($action === 'closed' && ($pr['merged'] ?? false) && $githubService) {
            $taskModel = new Task($this->db);
            $task = $taskModel->findByPrUrl($pr['html_url']);
            if ($task && $task['issue_number']) {
                $githubData = json_decode($task['github_data'] ?? '{}', true);
                if ($githubData) {
                    // Normalize event for maybeDuplicateTask
                    $pseudoEvent = $event;
                    $pseudoEvent['issue'] = $githubData;
                    // Force state_reason to 'completed' because it was merged
                    $pseudoEvent['issue']['state_reason'] = 'completed';
                    $this->maybeDuplicateTask($project, $pseudoEvent, $githubService);
                }
            }
        }

        return true;
    }

    private function handleCheckSuite(array $project, array $event, Task $taskModel, ?NotificationService $notificationService): bool
    {
        $action = $event['action'] ?? '';
        $checkSuite = $event['check_suite'] ?? null;

        if ($action !== 'completed' || !$checkSuite) {
            return true;
        }

        $conclusion = $checkSuite['conclusion'] ?? '';
        $pullRequests = $checkSuite['pull_requests'] ?? [];

        foreach ($pullRequests as $pr) {
            $prUrl = $pr['url'] ?? '';
            if (!$prUrl) {
                continue;
            }

            // GitHub API PR URL is usually api.github.com/repos/.../pulls/...
            // But we store html_url in tasks table. Let's try to convert or match.
            // Example PR URL stored: https://github.com/owner/repo/pull/123
            $htmlUrl = str_replace(['api.github.com/repos', '/pulls/'], ['github.com', '/pull/'], $prUrl);

            $task = $taskModel->findByPrUrl($htmlUrl);
            if (!$task) {
                continue;
            }

            $newStatus = $task['status'];
            if ($conclusion === 'failure' || $conclusion === 'timed_out' || $conclusion === 'cancelled') {
                $newStatus = 'failed_pr';
            } elseif ($conclusion === 'success' && $task['status'] === 'failed_pr') {
                $newStatus = 'completed';
            }

            if ($newStatus !== $task['status']) {
                $taskModel->updateStatus($task['task_id'], $newStatus);

                if ($notificationService) {
                    $title = $newStatus === 'failed_pr' ? "❌ PR Failed: #" . $task['issue_number'] : "✅ PR Fixed: #" . $task['issue_number'];
                    $message = $newStatus === 'failed_pr' ? "PR checks for \"" . $task['title'] . "\" failed." : "PR checks for \"" . $task['title'] . "\" are now passing.";

                    $actions = [];
                    if ($newStatus === 'completed') {
                        $actions[] = 'merge';
                    }

                    $notificationService->notify($project['user_id'], 'task_status', $title, $message, [
                        'task_id' => $task['task_id'],
                        'project_id' => $task['project_id'],
                        'status' => $newStatus,
                        'source_url' => $taskModel->getTargetUrl(array_merge($task, ['status' => $newStatus]))
                    ], $actions);
                }
            }
        }

        return true;
    }
}
