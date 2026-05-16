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
}
