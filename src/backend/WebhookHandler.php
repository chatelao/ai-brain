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

    public function handle(array $project, array $event, ?GitHubService $githubService = null, ?NotificationService $notificationService = null): bool
    {
        $action = $event['action'] ?? '';
        $issue = $event['issue'] ?? null;
        $checkSuite = $event['check_suite'] ?? null;

        $taskModel = new Task($this->db);

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

        if ($result && $notificationService) {
            if ($action === 'opened') {
                $notificationService->notify($project['user_id'], 'github_issue', "🆕 Issue Opened: #" . $issue['number'], "Issue \"" . $issue['title'] . "\" was opened in " . $project['github_repo'], [
                    'project_id' => $project['project_id'],
                    'issue_number' => $issue['number'],
                    'source_url' => $issue['html_url']
                ]);
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
            $stateReason = $issue['state_reason'] ?? '';
            $labels = $issue['labels'] ?? [];
            $hasAutorepeat = false;
            foreach ($labels as $label) {
                if (($label['name'] ?? '') === 'autorepeat') {
                    $hasAutorepeat = true;
                    break;
                }
            }

            if ($stateReason === 'completed' && $hasAutorepeat) {
                $labelNames = array_map(fn($l) => $l['name'], $labels);
                $repo = $event['repository']['full_name'] ?? '';
                if ($repo) {
                    $githubService->createIssue($repo, $issue['title'], $issue['body'], $labelNames);
                    $githubService->removeLabel($repo, $issue['number'], 'autorepeat');
                }
            }
        }

        return $result;
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
            if (!$prUrl) continue;

            // GitHub API PR URL is usually api.github.com/repos/.../pulls/...
            // But we store html_url in tasks table. Let's try to convert or match.
            // Example PR URL stored: https://github.com/owner/repo/pull/123
            $htmlUrl = str_replace(['api.github.com/repos', '/pulls/'], ['github.com', '/pull/'], $prUrl);

            $task = $taskModel->findByPrUrl($htmlUrl);
            if (!$task) continue;

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

                    $notificationService->notify($project['user_id'], 'task_status', $title, $message, [
                        'task_id' => $task['task_id'],
                        'project_id' => $task['project_id'],
                        'status' => $newStatus,
                        'source_url' => $taskModel->getTargetUrl(array_merge($task, ['status' => $newStatus]))
                    ]);
                }
            }
        }

        return true;
    }
}
