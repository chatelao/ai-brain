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

    public function handle(array $project, array $event, ?GitHubService $githubService = null, ?NotificationService $notificationService = null, ?JulesService $julesService = null, ?SandboxService $sandboxService = null, string $githubEvent = ''): bool
    {
        if (empty($event['repository'])) {
            return false;
        }

        $action = $event['action'] ?? '';
        $issue = $event['issue'] ?? null;
        $pullRequest = $event['pull_request'] ?? null;
        $checkSuite = $event['check_suite'] ?? null;
        $checkRun = $event['check_run'] ?? null;
        $status = $event['status'] ?? null;
        $comment = $event['comment'] ?? null;

        // Skip events without an action, except for check_suite which might have different triggers
        if (empty($action) && !$checkSuite && !$checkRun && !$status) {
            return true;
        }

        $taskModel = new Task($this->db);

        // Use the provided notification service directly
        $effectiveNotifService = $notificationService;

        if ($comment && $issue) {
            return $this->handleIssueComment($project, $event, $taskModel, $githubService, $julesService, $effectiveNotifService, $sandboxService, $githubEvent);
        }

        if ($pullRequest) {
            return $this->handlePullRequest($project, $event, $effectiveNotifService, $githubService, $sandboxService, $githubEvent);
        }

        if ($checkSuite || $checkRun || $status) {
            return $this->handleCheckSuite($project, $event, $taskModel, $effectiveNotifService, $githubService, $sandboxService, $githubEvent);
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

        $task = $taskModel->findByIssueNumber($project['project_id'], $issue['number']);
        $oldStatus = $task['status'] ?? null;

        $result = $taskModel->upsert($project['user_id'], $project['project_id'], $issue);
        $task = $taskModel->findByIssueNumber($project['project_id'], $issue['number']);

        if ($result && $task && $julesService && $githubService) {
            $taskModel->refreshJulesStatus($project['user_id'], $githubService, $julesService, $effectiveNotifService, (int)$task['task_id']);

            // Re-fetch task to get updated status
            $task = $taskModel->findByIssueNumber((int)$project['project_id'], (int)$issue['number']);

            if ($task && $oldStatus !== null && $task['status'] !== $oldStatus) {
                $this->runBlocklyAutomations($project, $event, $githubEvent, (int)$task['task_id'], $sandboxService, 'STATUS_CHANGED');
            }

            if ($task && ($task['status'] ?? '') === Task::STATUS_FAILED_JULES) {
                $this->runBlocklyAutomations($project, $event, $githubEvent, (int)$task['task_id'], $sandboxService, 'AGENT_ERROR');
            }
        }

        if ($result && $task) {
            $this->runBlocklyAutomations($project, $event, $githubEvent, (int)$task['task_id'], $sandboxService);
        }

        if ($result && $task && $effectiveNotifService) {
            $notificationData = [
                'task_id' => $task['task_id'],
                'project_id' => $project['project_id'],
                'issue_number' => $issue['number'] ?? 0,
                'source_url' => $issue['html_url'] ?? '',
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

    public function autoMergeAndDuplicate(array $project, array $task, GitHubService $githubService, ?NotificationService $notificationService = null): void
    {
        $prNumber = $githubService->extractPrNumber($task['pr_url'] ?? '');
        if (!$prNumber) {
            return;
        }

        try {
            $logger = Logger::getInstance($this->db);
            $pr = $githubService->getPullRequest($project['github_repo'], $prNumber);
            $mergeableState = $pr['mergeable_state'] ?? 'unknown';

            if ($mergeableState !== 'clean' && $mergeableState !== 'unstable' && $mergeableState !== 'has_hooks') {
                $logger->log($project['user_id'], $task['task_id'], "Auto-merge skipped: PR #$prNumber is in state '$mergeableState'", 'warning');
                return;
            }

            $logger->log($project['user_id'], $task['task_id'], "Auto-merging PR #$prNumber (state: $mergeableState)...", 'info');

            // 1. Update autorepeat labels if requested
            $count = $task['autorepeat_remaining'] ?? 0;
            $githubService->updateAutorepeatLabels($project['github_repo'], $task['issue_number'], $count);

            // 2. Merge PR
            $githubService->mergePullRequest($project['github_repo'], $prNumber, "Merged automatically via Auto-Repeat: " . $task['title']);

            // 3. Close Issue
            $githubService->closeIssue($project['github_repo'], $task['issue_number'], 'completed');

            // 4. Mark as merged in DB
            $taskModel = new Task($this->db);
            $taskModel->markAsMerged($task['task_id']);

        } catch (\Exception $e) {
            Logger::getInstance($this->db)->log($project['user_id'], $task['task_id'], "Auto-merge failed for PR #$prNumber: " . $e->getMessage(), 'error');
        }
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

        $labels = $issue['labels'] ?? [];

        $labelNames = array_filter(
            array_map(fn($l) => $l['name'], $labels),
            fn($name) => strtolower($name) !== 'autorepeat' && strtolower($name) !== 'auto-repeat' && !str_starts_with(strtolower($name), 'autorepeat:')
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

            $newIssue = $githubService->createIssue($repo, $issue['title'], $issue['body'] ?? null, array_values($labelNames));

            if (!empty($newIssue['number'])) {
                $newAutorepeatRemaining = max(0, ($task['autorepeat_remaining'] ?? 0) - 1);
                if ($newAutorepeatRemaining > 0) {
                    $taskModel->upsert($project['user_id'], $project['project_id'], $newIssue, $newAutorepeatRemaining);
                    $githubService->updateAutorepeatLabels($repo, $newIssue['number'], $newAutorepeatRemaining);
                }
            }

            $githubService->updateAutorepeatLabels($repo, $issue['number'], 0);

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

    private function handlePullRequest(array $project, array $event, ?NotificationService $notificationService, ?GitHubService $githubService = null, ?SandboxService $sandboxService = null, string $githubEvent = ''): bool
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

        // Sync task data (including labels) during PR events
        $taskModel = new Task($this->db);
        $task = $taskModel->findByPrUrl($pr['html_url']);
        if ($task && $githubService && !empty($project['github_repo'])) {
            try {
                $issue = $githubService->getIssue($project['github_repo'], $task['issue_number']);
                $taskModel->upsert($project['user_id'], $project['project_id'], $issue);

                $userModel = new User($this->db);
                $user = $userModel->findById($project['user_id']);
                $julesService = new JulesService(null, $user['jules_api_key'] ?? null);

                // Call refreshJulesStatus to ensure status is updated and auto-merge is attempted
                $taskModel->refreshJulesStatus($project['user_id'], $githubService, $julesService, $notificationService, (int)$task['task_id']);

                $task = $taskModel->findById($task['task_id']); // Refresh local task object
            } catch (\Exception $e) {
                // Ignore sync errors during PR events
            }
        }

        // Handle auto-repeat if PR is merged
        if ($action === 'closed' && ($pr['merged'] ?? false) && $githubService) {
            if ($task && $task['issue_number']) {
                $this->runBlocklyAutomations($project, $event, $githubEvent, (int)$task['task_id'], $sandboxService);

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
        } elseif ($action !== 'closed') {
            if ($task) {
                $this->runBlocklyAutomations($project, $event, $githubEvent, (int)$task['task_id'], $sandboxService);
            }
        }

        return true;
    }

    private function handleIssueComment(array $project, array $event, Task $taskModel, ?GitHubService $githubService, ?JulesService $julesService, ?NotificationService $notificationService, ?SandboxService $sandboxService = null, string $githubEvent = ''): bool
    {
        $issue = $event['issue'] ?? null;
        $comment = $event['comment'] ?? null;

        if (!$issue || !$comment) {
            return true;
        }

        $login = strtolower($comment['user']['login'] ?? '');
        $isJulesComment = ($login === 'google-labs-jules[bot]' || $login === 'jules');
        $body = $comment['body'] ?? '';

        $task = $taskModel->findByIssueNumber($project['project_id'], $issue['number']);

        if ($isJulesComment) {
            $sessionId = $taskModel->extractSessionId($body);
            if ($sessionId && $task) {
                $connection = $this->db->getConnection();
                $stmt = $connection->prepare("UPDATE tasks SET jules_session_id = ? WHERE task_id = ?");
                $stmt->execute([$sessionId, $task['task_id']]);
            }

            // Always refresh status when Jules comments to capture state transitions immediately
            if ($task && $githubService && $julesService) {
                $taskModel->refreshJulesStatus($project['user_id'], $githubService, $julesService, $notificationService, (int)$task['task_id']);

                // Re-fetch task to get updated status
                $task = $taskModel->findByIssueNumber((int)$project['project_id'], (int)$issue['number']);
                if ($task && ($task['status'] ?? '') === Task::STATUS_FAILED_JULES) {
                    $this->runBlocklyAutomations($project, $event, $githubEvent, (int)$task['task_id'], $sandboxService, 'AGENT_ERROR');
                }
            }
        }

        if ($task) {
            $this->runBlocklyAutomations($project, $event, $githubEvent, (int)$task['task_id'], $sandboxService);
        }

        // Notify about the comment
        if ($notificationService) {
            $userName = $comment['user']['login'] ?? 'Unknown';
            $title = ($isJulesComment ? "🤖 Jules" : "💬 $userName") . " commented on #" . $issue['number'];

            $notificationData = [
                'project_id' => $project['project_id'],
                'issue_number' => $issue['number'],
                'source_url' => $comment['html_url'] ?? $issue['html_url'],
                'is_system' => $isJulesComment || !$this->isUserTriggered($event)
            ];

            if ($task) {
                $notificationData['task_id'] = $task['task_id'];
            }

            $notificationService->notify(
                $project['user_id'],
                'github_comment',
                $title,
                $body,
                $notificationData
            );
        }

        return true;
    }

    private function handleCheckSuite(array $project, array $event, Task $taskModel, ?NotificationService $notificationService, ?GitHubService $githubService = null, ?SandboxService $sandboxService = null, string $githubEvent = ''): bool
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

            // Fetch latest PR and Check Suites for a complete view
            $prData = null;
            $checkSuitesData = null;
            if ($githubService) {
                try {
                    $prNumber = $githubService->extractPrNumber($task['pr_url'] ?? '');
                    if ($prNumber) {
                        $prData = $githubService->getPullRequest($project['github_repo'], $prNumber);
                        if (!empty($prData['head']['sha'])) {
                            $checkSuitesData = $githubService->getCheckSuites($project['github_repo'], $prData['head']['sha']);
                        }
                    }
                } catch (\Exception $e) {
                    Logger::getInstance($this->db)->log($project['user_id'], $task['task_id'], "Error fetching PR status in handleCheckSuite: " . $e->getMessage(), 'error');
                }
            }

            // Fetch combined status as well
            $commitStatusesData = null;
            if ($githubService) {
                try {
                    $prNumber = $githubService->extractPrNumber($task['pr_url'] ?? '');
                    if ($prNumber) {
                        $prData = $prData ?: $githubService->getPullRequest($project['github_repo'], $prNumber);
                        $sha = $prData['head']['sha'] ?? null;
                        if ($sha) {
                            $commitStatusesData = $githubService->getCombinedStatus($project['github_repo'], $sha);
                        }
                    }
                } catch (\Exception $e) {
                    // Ignore commit status fetch errors
                }
            }

            // Fallback to the single check suite from the event if API fetch fails or is not available
            $newStatus = $taskModel->resolveStatus($task, $prData, $checkSuitesData ?: $checkSuite, $commitStatusesData);

            if ($newStatus !== $task['status']) {
                $taskModel->updateStatus($task['task_id'], $newStatus);
                $task['status'] = $newStatus; // Update local object for Blockly

                $this->runBlocklyAutomations($project, $event, $githubEvent, (int)$task['task_id'], $sandboxService, 'STATUS_CHANGED');

                if ($notificationService) {
                    $title = $newStatus === Task::STATUS_FAILED_PR ? "❌ PR Failed: #" . $task['issue_number'] : "✅ PR Fixed: #" . $task['issue_number'];
                    $message = $newStatus === Task::STATUS_FAILED_PR ? "PR checks for \"" . $task['title'] . "\" failed." : "PR checks for \"" . $task['title'] . "\" are now passing.";

                    if ($newStatus === Task::STATUS_READY && ($task['autorepeat_remaining'] ?? 0) > 0 && $githubService) {
                        $this->autoMergeAndDuplicate($project, array_merge($task, ['status' => $newStatus]), $githubService, $notificationService);
                        $message .= " (Auto-merging...)";
                    }

                    $actions = [];
                    if ($newStatus === Task::STATUS_READY) {
                        $actions = ['merge'];
                    } elseif ($newStatus === Task::STATUS_FAILED_PR) {
                        $actions = ['fix_bug', 'retry', 'restart'];
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
            } elseif ($newStatus === Task::STATUS_READY && ($task['autorepeat_remaining'] ?? 0) > 0 && $githubService) {
                // Even if status didn't change (still READY), attempt auto-merge if it's an autorepeat task
                // This handles cases where a previous merge attempt might have failed but is now possible.
                $this->autoMergeAndDuplicate($project, array_merge($task, ['status' => $newStatus]), $githubService, $notificationService);
            }

            $this->runBlocklyAutomations($project, $event, $githubEvent, (int)$task['task_id'], $sandboxService);
        }

        return true;
    }

    private function mapToBlocklyEvent(string $githubEvent, array $payload): ?string
    {
        $action = $payload['action'] ?? '';

        switch ($githubEvent) {
            case 'issues':
                if ($action === 'labeled') return 'ISSUE_LABELED';
                if ($action === 'closed') return 'ISSUE_CLOSED';
                if ($action === 'opened') return 'ISSUE_OPENED';
                if ($action === 'reopened') return 'ISSUE_REOPENED';
                break;
            case 'issue_comment':
                if ($action === 'created') return 'COMMENT_CREATED';
                break;
            case 'pull_request':
                if ($action === 'opened') return 'PR_CREATED';
                if ($action === 'closed' && ($payload['pull_request']['merged'] ?? false)) return 'PR_MERGED';
                break;
            case 'check_suite':
            case 'check_run':
            case 'status':
                if ($action === 'completed' || empty($action)) return 'CHECKS_COMPLETED';
                break;
        }

        return null;
    }

    private function runBlocklyAutomations(array $project, array $event, string $githubEvent, ?int $taskId, ?SandboxService $sandboxService, ?string $overrideEvent = null): void
    {
        if (!$sandboxService || !$taskId) {
            return;
        }

        $userId = (int)$project['user_id'];
        Logger::getInstance($this->db)->log($userId, $taskId, "Starting Blockly automation sequence for task #$taskId", 'info');

        $userModel = new User($this->db);
        $user = $userModel->findById($userId);

        $blocklyEvent = $overrideEvent ?: $this->mapToBlocklyEvent($githubEvent, $event);
        $eventContext = [
            'type' => $blocklyEvent ?: $githubEvent, // Fallback to raw github event if not mapped
            'github_event' => $githubEvent,
            'payload' => $event
        ];

        $handledEvents = [];

        // 1. Run Local Automations (Project level) first to allow precedence
        if (!empty($project['blockly_config'])) {
            $config = json_decode($project['blockly_config'], true);
            if (!empty($config['js'])) {
                $result = $sandboxService->execute($userId, $taskId, $config['js'], $eventContext, 'Local');
                $handledEvents = $result['handledEvents'] ?? [];
            }
        }

        // 2. Run Global Automations (User level), suppressing events already handled by Local
        if (!empty($user['blockly_config'])) {
            $config = json_decode($user['blockly_config'], true);
            if (!empty($config['js'])) {
                $sandboxService->execute($userId, $taskId, $config['js'], $eventContext, 'Global', false, $handledEvents);
            }
        }
    }
}
