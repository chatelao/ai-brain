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

        if ($task['status'] === 'failed') {
            return 'red';
        }

        if ($task['status'] === 'in_progress') {
            return 'yellow';
        }

        if ($task['status'] === 'completed') {
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

    public function refreshJulesStatus(int $userId, GitHubService $githubService, JulesService $julesService): void
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT t.*, p.github_repo
             FROM tasks t
             JOIN projects p ON t.project_id = p.project_id
             WHERE t.user_id = ?
             AND (t.last_synced_at IS NULL OR t.last_synced_at < datetime('now', '-5 minutes'))
             AND (t.status NOT IN ('completed', 'failed') OR t.jules_status NOT IN ('completed', 'failed'))"
        );
        $stmt->execute([$userId]);
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

            if (!$sessionId) {
                try {
                    $comments = $githubService->getIssueComments($task['github_repo'], $task['issue_number']);
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
                } catch (\Exception $e) {
                    continue;
                }
            }

            if ($sessionId && $apiKey) {
                $julesData = $julesService->fetchSessionStatus($sessionId, $apiKey);
                if ($julesData) {
                    $newStatus = $julesData['status'];
                    $mappedStatus = $task['status'];

                    if (in_array($newStatus, ['in-progress', 'coding', 'testing', 'researching', 'planning'])) {
                        $mappedStatus = 'in_progress';
                    } elseif ($newStatus === 'completed' || $newStatus === 'finished') {
                        $mappedStatus = 'completed';
                    } elseif ($newStatus === 'failed' || $newStatus === 'error') {
                        $mappedStatus = 'failed';
                    }

                    $updateStmt = $this->db->getConnection()->prepare(
                        "UPDATE tasks SET jules_status = ?, status = ?, last_synced_at = datetime('now') WHERE task_id = ?"
                    );
                    $updateStmt->execute([$newStatus, $mappedStatus, $task['task_id']]);
                }
            } else {
                // Still update last_synced_at even if no sessionId or apiKey to avoid constant retries
                $updateStmt = $this->db->getConnection()->prepare(
                    "UPDATE tasks SET last_synced_at = datetime('now') WHERE task_id = ?"
                );
                $updateStmt->execute([$task['task_id']]);
            }
        }
    }
}
