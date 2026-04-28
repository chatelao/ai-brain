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

    public function handle(int $projectId, array $event, ?GitHubService $githubService = null): bool
    {
        $action = $event['action'] ?? '';
        $issue = $event['issue'] ?? null;

        if (!$issue || !in_array($action, ['opened', 'reopened', 'edited', 'closed', 'labeled', 'unlabeled'])) {
            return false;
        }

        $connection = $this->db->getConnection();
        $driver = $connection->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $sql = "INSERT INTO tasks (project_id, issue_number, title, body, github_data, status)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON CONFLICT(project_id, issue_number) DO UPDATE SET
                        title = excluded.title,
                        body = excluded.body,
                        github_data = excluded.github_data";
        } else {
            $sql = "INSERT INTO tasks (project_id, issue_number, title, body, github_data, status)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        title = VALUES(title),
                        body = VALUES(body),
                        github_data = VALUES(github_data)";
        }

        $stmt = $connection->prepare($sql);

        $result = $stmt->execute([
            $projectId,
            $issue['number'],
            $issue['title'],
            $issue['body'],
            json_encode($issue),
            'pending'
        ]);

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
