<?php

namespace App;

use PDO;

class Task
{
    public function __construct(private Database $db)
    {
    }

    public function findByProjectId(int $projectId): array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT * FROM tasks WHERE project_id = ? ORDER BY created_at DESC"
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public function findByUserProjects(int $userId): array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT t.*, p.github_repo
             FROM tasks t
             JOIN projects p ON t.project_id = p.project_id
             WHERE p.user_id = ?
             ORDER BY t.created_at DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function getRunningAutorepeatTasks(int $userId): array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT t.*, p.github_repo
             FROM tasks t
             JOIN projects p ON t.project_id = p.project_id
             WHERE p.user_id = ?
             ORDER BY t.created_at DESC"
        );
        $stmt->execute([$userId]);
        $tasks = $stmt->fetchAll();

        return array_filter($tasks, function($task) {
            $githubData = json_decode($task['github_data'], true);
            if (!$githubData) return false;

            $isOpen = ($githubData['state'] ?? '') === 'open';
            $hasAutorepeat = false;
            $labels = $githubData['labels'] ?? [];
            foreach ($labels as $label) {
                if (($label['name'] ?? '') === 'autorepeat') {
                    $hasAutorepeat = true;
                    break;
                }
            }

            return $isOpen && $hasAutorepeat;
        });
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT * FROM tasks WHERE task_id = ?"
        );
        $stmt->execute([$id]);
        $task = $stmt->fetch();
        return $task ?: null;
    }

    public function findByIssueNumber(int $projectId, int $issueNumber): ?array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT * FROM tasks WHERE project_id = ? AND issue_number = ?"
        );
        $stmt->execute([$projectId, $issueNumber]);
        $task = $stmt->fetch();
        return $task ?: null;
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "UPDATE tasks SET status = ? WHERE task_id = ?"
        );
        return $stmt->execute([$status, $id]);
    }

    public function updateAgentResponse(int $id, string $response, string $status = 'completed'): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "UPDATE tasks SET agent_response = ?, status = ? WHERE task_id = ?"
        );
        return $stmt->execute([$response, $status, $id]);
    }

    public function create(array $data): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "INSERT INTO tasks (user_id, project_id, issue_number, title, body, github_data, status) VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        return $stmt->execute([
            $data['user_id'],
            $data['project_id'],
            $data['issue_number'],
            $data['title'],
            $data['body'] ?? '',
            $data['github_data'] ?? null,
            $data['status'] ?? 'pending'
        ]);
    }

    public function syncIssues(int $userId, int $projectId, string $repo, GitHubService $githubService): void
    {
        $issues = $githubService->listIssues($repo);

        foreach ($issues as $issue) {
            // Check if it's really an issue (not a PR)
            if (isset($issue['pull_request'])) {
                continue;
            }
            $this->upsert($userId, $projectId, $issue);
        }
    }

    public function upsert(int $userId, int $projectId, array $issue): bool
    {
        $connection = $this->db->getConnection();
        $driver = $connection->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $sql = "INSERT INTO tasks (user_id, project_id, issue_number, title, body, github_data, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON CONFLICT(project_id, issue_number) DO UPDATE SET
                        title = excluded.title,
                        body = excluded.body,
                        github_data = excluded.github_data";
        } else {
            $sql = "INSERT INTO tasks (user_id, project_id, issue_number, title, body, github_data, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        title = VALUES(title),
                        body = VALUES(body),
                        github_data = VALUES(github_data)";
        }

        $stmt = $connection->prepare($sql);

        return $stmt->execute([
            $userId,
            $projectId,
            $issue['number'],
            $issue['title'],
            $issue['body'],
            json_encode($issue),
            'pending'
        ]);
    }

    public function getLogs(int $taskId): array
    {
        $logger = new Logger($this->db);
        return $logger->getLogsByTaskId($taskId);
    }

    public function getStatusColor(array $task): string
    {
        $githubData = json_decode($task['github_data'] ?? '{}', true);
        $state = $githubData['state'] ?? 'open';

        if ($state === 'closed') {
            return 'purple';
        }

        $status = $task['status'] ?? 'pending';

        if ($status === 'failed') {
            return 'red';
        }

        if (in_array($status, ['in_progress', 'coding', 'testing'])) {
            return 'yellow';
        }

        if (in_array($status, ['researching', 'planning', 'awaiting-plan-approval', 'awaiting-user-feedback'])) {
            return 'blue';
        }

        if ($status === 'completed') {
            return 'green';
        }

        return 'grey';
    }

    public function hasAutorepeatLabel(array $task): bool
    {
        $githubData = json_decode($task['github_data'] ?? '{}', true);
        $labels = $githubData['labels'] ?? [];
        foreach ($labels as $label) {
            $name = strtolower($label['name'] ?? '');
            if ($name === 'autorepeat' || $name === 'auto-repeat') {
                return true;
            }
        }
        return false;
    }

    public function extractSessionId(string $text): ?string
    {
        // 1. Markdown links like [Jules Task](.../sessions/ID) or .../task/ID
        if (preg_match('/jules\.google\.com\/(?:sessions|task)\/([a-zA-Z0-9_-]+)/', $text, $matches)) {
            return $matches[1];
        }

        // 2. Explicit task_id or session_id labels
        if (preg_match('/(?:task_id|session_id|sessionId|taskId)[:=]\s*([a-zA-Z0-9_-]+)/i', $text, $matches)) {
            return $matches[1];
        }

        // 3. Look for a long numeric ID that looks like a session ID
        if (preg_match('/\b(\d{15,25})\b/', $text, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function extractPrUrl(string $text): ?string
    {
        if (preg_match('/https:\/\/github\.com\/[^\/]+\/[^\/]+\/pull\/\d+/', $text, $matches)) {
            return $matches[0];
        }
        return null;
    }

    public function getTargetUrl(array $task, ?string $repo = null): string
    {
        $issueUrl = "https://github.com/" . ($repo ?? $task['github_repo']) . "/issues/" . $task['issue_number'];
        $status = $task['status'] ?? 'pending';

        if ($status === 'completed') {
            return $task['pr_url'] ?: $issueUrl;
        }

        if ($status === 'in_progress') {
            return $task['jules_url'] ?: $issueUrl;
        }

        if ($status === 'failed') {
            if (!empty($task['pr_url'])) {
                return $task['pr_url'];
            }
            return $task['jules_url'] ?: $issueUrl;
        }

        return $issueUrl;
    }

    public function refreshJulesStatus(int $userId, GitHubService $githubService, JulesService $julesService): void
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT t.*, p.github_repo
             FROM tasks t
             JOIN projects p ON t.project_id = p.project_id
             WHERE t.user_id = ?
             AND (t.last_synced_at IS NULL OR t.last_synced_at < ?)
             AND (t.status NOT IN ('completed', 'failed') OR t.jules_status NOT IN ('completed', 'failed'))"
        );
        $fiveMinutesAgo = date('Y-m-d H:i:s', strtotime('-5 minutes'));
        $stmt->execute([$userId, $fiveMinutesAgo]);
        $tasks = $stmt->fetchAll();

        $userStmt = $this->db->getConnection()->prepare("SELECT jules_api_key FROM users WHERE user_id = ?");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();
        $apiKey = $user['jules_api_key'] ?? null;

        foreach ($tasks as $task) {
            $githubData = json_decode($task['github_data'] ?? '{}', true);
            $assignee = $githubData['assignee']['login'] ?? '';
            $labels = $githubData['labels'] ?? [];
            $hasJulesLabel = false;
            foreach ($labels as $label) {
                if (strtolower($label['name'] ?? '') === 'jules') {
                    $hasJulesLabel = true;
                    break;
                }
            }

            $isJulesRelated = (
                strtolower($assignee) === 'jules' ||
                strtolower($assignee) === 'google-labs-jules[bot]' ||
                $hasJulesLabel
            );

            if (!$isJulesRelated) {
                continue;
            }

            $sessionId = $task['jules_session_id'];
            $prUrl = $task['pr_url'];

            if (!$sessionId || !$prUrl) {
                try {
                    $comments = $githubService->getIssueComments($task['github_repo'], $task['issue_number']);

                    if (!$sessionId) {
                        // Reverse to find the latest "on it" comment
                        $julesComments = array_reverse(array_filter($comments, function($c) {
                            $login = strtolower($c['user']['login'] ?? '');
                            return ($login === 'google-labs-jules[bot]' || $login === 'jules') &&
                                stripos($c['body'] ?? '', 'on it') !== false;
                        }));

                        foreach ($julesComments as $comment) {
                            $sessionId = $this->extractSessionId($comment['body'] ?? '');
                            if ($sessionId) {
                                $updateStmt = $this->db->getConnection()->prepare(
                                    "UPDATE tasks SET jules_session_id = ? WHERE task_id = ?"
                                );
                                $updateStmt->execute([$sessionId, $task['task_id']]);
                                break;
                            }
                        }
                    }

                    if (!$prUrl) {
                        foreach ($comments as $comment) {
                            $prUrl = $this->extractPrUrl($comment['body'] ?? '');
                            if ($prUrl) {
                                $updateStmt = $this->db->getConnection()->prepare(
                                    "UPDATE tasks SET pr_url = ? WHERE task_id = ?"
                                );
                                $updateStmt->execute([$prUrl, $task['task_id']]);
                                break;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Log error if needed
                }
            }

            if ($sessionId && $apiKey) {
                $julesData = $julesService->fetchSessionStatus($sessionId, $apiKey);
                if ($julesData) {
                    $newStatus = $julesData['status'];
                    $mappedStatus = $task['status'];
                    $julesUrl = $julesData['url'] ?? null;

                    if (in_array($newStatus, ['in-progress', 'coding', 'testing', 'researching', 'planning'])) {
                        $mappedStatus = 'in_progress';
                    } elseif ($newStatus === 'completed' || $newStatus === 'finished') {
                        $mappedStatus = 'completed';
                    } elseif ($newStatus === 'failed' || $newStatus === 'error') {
                        $mappedStatus = 'failed';
                    }

                    $updateStmt = $this->db->getConnection()->prepare(
                        "UPDATE tasks SET jules_status = ?, status = ?, jules_url = ?, last_synced_at = ? WHERE task_id = ?"
                    );
                    $updateStmt->execute([$newStatus, $mappedStatus, $julesUrl, date('Y-m-d H:i:s'), $task['task_id']]);
                }
            } else {
                // Still update last_synced_at even if no sessionId or apiKey to avoid constant retries
                $updateStmt = $this->db->getConnection()->prepare(
                    "UPDATE tasks SET last_synced_at = ? WHERE task_id = ?"
                );
                $updateStmt->execute([date('Y-m-d H:i:s'), $task['task_id']]);
            }
        }
    }
}
