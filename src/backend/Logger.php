<?php

namespace App;

class Logger
{
    public function __construct(private Database $db)
    {
    }

    public function log(int $userId, int $taskId, string $message, string $level = 'info'): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "INSERT INTO task_logs (user_id, task_id, message, level) VALUES (?, ?, ?, ?)"
        );
        return $stmt->execute([$userId, $taskId, $message, $level]);
    }

    public function getLogsByTaskId(int $taskId): array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT * FROM task_logs WHERE task_id = ? ORDER BY created_at ASC"
        );
        $stmt->execute([$taskId]);
        return $stmt->fetchAll();
    }
}
