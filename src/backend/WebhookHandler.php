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

        if (!$issue) {
            return true;
        }

        $taskModel = new Task($this->db);

        if ($action === 'deleted') {
            return $taskModel->deleteByIssueNumber($project['project_id'], $issue['number']);
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
}
