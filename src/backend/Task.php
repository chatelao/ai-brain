<?php

namespace App;

use PDO;
use Ramsey\Uuid\Uuid;

class Task
{
    public function __construct(private Database $db)
    {
    }

    public function findByProjectId(string $projectId): array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT * FROM tasks WHERE project_id = ? ORDER BY created_at DESC"
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public function findByUserProjects(string $userId): array
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

    public function getRunningAutorepeatTasks(string $userId): array
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

    public function findById(string $id): ?array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT * FROM tasks WHERE task_id = ?"
        );
        $stmt->execute([$id]);
        $task = $stmt->fetch();
        return $task ?: null;
    }

    public function updateStatus(string $id, string $status): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "UPDATE tasks SET status = ? WHERE task_id = ?"
        );
        return $stmt->execute([$status, $id]);
    }

    public function updateAgentResponse(string $id, string $response, string $status = 'completed'): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "UPDATE tasks SET agent_response = ?, status = ? WHERE task_id = ?"
        );
        return $stmt->execute([$response, $status, $id]);
    }

    public function create(array $data): bool
    {
        $taskId = Uuid::uuid4()->toString();
        $stmt = $this->db->getConnection()->prepare(
            "INSERT INTO tasks (task_id, project_id, issue_number, title, body, github_data, status) VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        return $stmt->execute([
            $taskId,
            $data['project_id'],
            $data['issue_number'],
            $data['title'],
            $data['body'] ?? '',
            $data['github_data'] ?? null,
            $data['status'] ?? 'pending'
        ]);
    }

    public function upsert(string $projectId, array $issue): bool
    {
        $taskId = Uuid::uuid4()->toString();
        $connection = $this->db->getConnection();
        $driver = $connection->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $sql = "INSERT INTO tasks (task_id, project_id, issue_number, title, body, github_data, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON CONFLICT(project_id, issue_number) DO UPDATE SET
                        title = excluded.title,
                        body = excluded.body,
                        github_data = excluded.github_data";
        } else {
            $sql = "INSERT INTO tasks (task_id, project_id, issue_number, title, body, github_data, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        title = VALUES(title),
                        body = VALUES(body),
                        github_data = VALUES(github_data)";
        }

        $stmt = $connection->prepare($sql);

        return $stmt->execute([
            $taskId,
            $projectId,
            $issue['number'],
            $issue['title'],
            $issue['body'],
            json_encode($issue),
            'pending'
        ]);
    }

    public function getLogs(string $taskId): array
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
