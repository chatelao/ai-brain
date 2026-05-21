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

    private function isUserTriggered(array $event): bool
    {
        return ($event['sender']['type'] ?? '') === 'User';
    }

    public function handle(array $project, array $event, ?GitHubService $githubService = null, ?NotificationService $notificationService = null, ?JulesService $julesService = null): bool
    {
        if (empty($event['repository'])) {
            return false;
        }

        $action = $event['action'] ?? '';
        $issue = $event['issue'] ?? null;
        $pullRequest = $event['pull_request'] ?? null;
        $checkSuite = $event['check_suite'] ?? null;
        $comment = $event['comment'] ?? null;

        // Skip events without an action, except for check_suite which might have different triggers
        if (empty($action) && !$checkSuite) {
            return true;
        }

        $taskModel = new Task($this->db);

        // Use the provided notification service directly
        $effectiveNotifService = $notificationService;

        if ($comment && $issue) {
            return $this->handleIssueComment($project, $event, $taskModel, $githubService, $julesService, $effectiveNotifService);
        }

        if ($pullRequest) {
            return $this->handlePullRequest($project, $event, $effectiveNotifService, $githubService);
        }

        if ($checkSuite) {
            return $this->handleCheckSuite($project, $event, $taskModel, $effectiveNotifService);
        }

        if (!$issue) {
            return true;
        }

        if ($action === 'deleted') {
            $result = $taskModel->deleteByIssueNumber($project['project_id'], $issue['number']);
            if ($result && $effectiveNotifService) {
                $effectiveNotifService->notify($project['user_id'], 'github_issue', "🗑️ Issue Deleted: #" . $issue['number'], "Issue \"" . ($issue['title'] ?? 'Unknown') . "\" was deleted in " . $project['github_repo'], [
                    'project_id' => $project['project_id'],
                    'issue_number' => $issue['number'],
                    'status' => Task::STATUS_FINISHED,
                    'source_url' => $issue['html_url'] ?? '',
                    'is_system' => !$this->isUserTriggered($event)
                ]);
            }
            return $result;
        }

        if (!in_array($action, ['opened', 'reopened', 'edited', 'closed', 'labeled', 'unlabeled'])) {
            return true;
        }

        $result = $taskModel->upsert($project['user_id'], $project['project_id'], $issue);
        $task = $taskModel->findByIssueNumber($project['project_id'], $issue['number']);

        if ($result && $task && $julesService && $githubService) {
            $taskModel->refreshJulesStatus($project['user_id'], $githubService, $julesService, $effectiveNotifService, (int)$task['task_id']);
        }

        if ($result && $task && $effectiveNotifService) {
            $notificationData = [
                'task_id' => $task['task_id'],
                'project_id' => $project['project_id'],
                'issue_number' => $issue['number'],
                'source_url' => $issue['html_url'],
                'is_system' => !$this->isUserTriggered($event)
            ];

            if ($action === 'opened') {
                $notificationData['status'] = Task::STATUS_CREATED;
                $effectiveNotifService->notify($project['user_id'], 'github_issue', "🆕 Issue Opened: #" . $issue['number'], "Issue \"" . $issue['title'] . "\" was opened in " . $project['github_repo'], $notificationData, ['acknowledge']);
            } elseif ($action === 'closed') {
                $notificationData['status'] = Task::STATUS_FINISHED;
                $effectiveNotifService->notify($project['user_id'], 'github_issue', "🔒 Issue Closed: #" . $issue['number'], "Issue \"" . $issue['title'] . "\" was closed in " . $project['github_repo'], $notificationData);
            } elseif ($action === 'reopened') {
                $notificationData['status'] = Task::STATUS_CREATED;
                $effectiveNotifService->notify($project['user_id'], 'github_issue', "🔓 Issue Reopened: #" . $issue['number'], "Issue \"" . $issue['title'] . "\" was reopened in " . $project['github_repo'], $notificationData, ['acknowledge']);
            }
        }

        if ($result && $action === 'closed' && $githubService) {
            $this->maybeDuplicateTask($project, $event, $githubService, $effectiveNotifService);
        }

        return $result;
    }

    private function maybeDuplicateTask(array $project, array $event, GitHubService $githubService, ?NotificationService $notificationService = null): void
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

            $githubService->createIssue($repo, $issue['title'], $issue['body'] ?? null, array_values($labelNames));
            $githubService->removeLabel($repo, $issue['number'], $autorepeatLabelName);

            if ($notificationService) {
                $notificationService->notify($project['user_id'], 'github_issue', "🔁 Auto-Repeat: #" . $issue['number'], "Task \"" . $issue['title'] . "\" was merged/closed with Auto-Repeat. A new issue has been created.", [
                    'project_id' => $project['project_id'],
                    'issue_number' => $issue['number'],
                    'status' => Task::STATUS_CREATED,
                    'source_url' => $issue['html_url'] ?? '',
                    'is_system' => true
                ]);
            }
        }
    }

    private function handlePullRequest(array $project, array $event, ?NotificationService $notificationService, ?GitHubService $githubService = null): bool
    {
        $action = $event['action'] ?? '';
        $pr = $event['pull_request'] ?? null;

        if (!$pr) {
            return true;
        }

        if ($notificationService && in_array($action, ['opened', 'closed', 'reopened', 'synchronize'])) {
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
                'status' => ($action === 'closed' && ($pr['merged'] ?? false)) ? Task::STATUS_FINISHED : Task::STATUS_CHECKING,
                'source_url' => $pr['html_url'],
                'is_system' => !$this->isUserTriggered($event)
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
                    $this->maybeDuplicateTask($project, $pseudoEvent, $githubService, $notificationService);
                }
            }
        }

        return true;
    }

    private function handleIssueComment(array $project, array $event, Task $taskModel, ?GitHubService $githubService, ?JulesService $julesService, ?NotificationService $notificationService): bool
    {
        $issue = $event['issue'] ?? null;
        $comment = $event['comment'] ?? null;

        if (!$issue || !$comment) {
            return true;
        }

        $login = strtolower($comment['user']['login'] ?? '');
        $isJulesComment = ($login === 'google-labs-jules[bot]' || $login === 'jules');
        $body = $comment['body'] ?? '';

        if ($isJulesComment && stripos($body, 'on it') !== false) {
            $sessionId = $taskModel->extractSessionId($body);
            if ($sessionId) {
                $task = $taskModel->findByIssueNumber($project['project_id'], $issue['number']);
                if ($task) {
                    $connection = $this->db->getConnection();
                    $stmt = $connection->prepare("UPDATE tasks SET jules_session_id = ? WHERE task_id = ?");
                    $stmt->execute([$sessionId, $task['task_id']]);

                    if ($githubService && $julesService) {
                        $taskModel->refreshJulesStatus($project['user_id'], $githubService, $julesService, $notificationService, (int)$task['task_id']);
                    }
                }
            }
        }

        return true;
    }

    private function handleCheckSuite(array $project, array $event, Task $taskModel, ?NotificationService $notificationService): bool
    {
        $action = $event['action'] ?? '';
        $checkSuite = $event['check_suite'] ?? null;

        if (!$checkSuite || (!empty($action) && !in_array($action, ['requested', 'rerequested', 'completed']))) {
            return true;
        }

        $pullRequests = $checkSuite['pull_requests'] ?? [];

        if (empty($pullRequests)) {
            // Some check suites might not be associated with a PR yet or at all
            return true;
        }

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

            $newStatus = $taskModel->resolveStatus($task, null, $checkSuite);

            if ($newStatus !== $task['status']) {
                $taskModel->updateStatus($task['task_id'], $newStatus);

                if ($notificationService) {
                    $title = $newStatus === Task::STATUS_FAILED_PR ? "❌ PR Failed: #" . $task['issue_number'] : "✅ PR Fixed: #" . $task['issue_number'];
                    $message = $newStatus === Task::STATUS_FAILED_PR ? "PR checks for \"" . $task['title'] . "\" failed." : "PR checks for \"" . $task['title'] . "\" are now passing.";

                    $actions = [];
                    if ($newStatus === Task::STATUS_READY) {
                        $actions = ['merge'];
                    } elseif ($newStatus === Task::STATUS_FAILED_PR) {
                        $actions = ['retry', 'restart'];
                    } else {
                        $actions = ['acknowledge'];
                    }

                    $notificationService->notify($project['user_id'], 'task_status', $title, $message, [
                        'task_id' => $task['task_id'],
                        'project_id' => $task['project_id'],
                        'status' => $newStatus,
                        'source_url' => $taskModel->getTargetUrl(array_merge($task, ['status' => $newStatus])),
                        'is_system' => true // Check suite events are always system-driven
                    ], $actions);
                }
            }
        }

        return true;
    }
}
