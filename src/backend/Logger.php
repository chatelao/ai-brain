<?php

namespace App;

use Ramsey\Uuid\Uuid;

class Logger
{
    public function __construct(private Database $db)
    {
    }

    public function log(string $taskId, string $message, string $level = 'info'): bool
    {
        $logId = Uuid::uuid4()->toString();
        $stmt = $this->db->getConnection()->prepare(
            "INSERT INTO task_logs (task_log_id, task_id, message, level) VALUES (?, ?, ?, ?)"
        );
        return $stmt->execute([$logId, $taskId, $message, $level]);
    }

    public function getLogsByTaskId(string $taskId): array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT * FROM task_logs WHERE task_id = ? ORDER BY created_at ASC"
        );
        $stmt->execute([$taskId]);
        return $stmt->fetchAll();
    }
}
