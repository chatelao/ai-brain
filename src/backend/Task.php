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

    public function findById(int $id): ?array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT * FROM tasks WHERE id = ?"
        );
        $stmt->execute([$id]);
        $task = $stmt->fetch();
        return $task ?: null;
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "UPDATE tasks SET status = ? WHERE id = ?"
        );
        return $stmt->execute([$status, $id]);
    }

    public function updateAgentResponse(int $id, string $response, string $status = 'completed'): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "UPDATE tasks SET agent_response = ?, status = ? WHERE id = ?"
        );
        return $stmt->execute([$response, $status, $id]);
    }

    public function create(array $data): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "INSERT INTO tasks (project_id, issue_number, title, body, github_data, status) VALUES (?, ?, ?, ?, ?, ?)"
        );
        return $stmt->execute([
            $data['project_id'],
            $data['issue_number'],
            $data['title'],
            $data['body'] ?? '',
            $data['github_data'] ?? null,
            $data['status'] ?? 'pending'
        ]);
    }

    public function getLogs(int $taskId): array
    {
        $logger = new Logger($this->db);
        return $logger->getLogsByTaskId($taskId);
    }
}
