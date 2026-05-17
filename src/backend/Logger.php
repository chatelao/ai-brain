<?php

namespace App;

class Logger
{
    private static ?Logger $instance = null;

    public function __construct(private Database $db)
    {
        self::$instance = $this;
    }

    public static function getInstance(?Database $db = null): Logger
    {
        if (self::$instance === null) {
            if ($db === null) {
                $db = new Database();
            }
            self::$instance = new Logger($db);
        }
        return self::$instance;
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

    public function logPerformance(?int $userId, string $type, string $target, float $duration, ?array $context = null): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "INSERT INTO performance_logs (user_id, type, target, duration, context) VALUES (?, ?, ?, ?, ?)"
        );
        return $stmt->execute([
            $userId,
            $type,
            $target,
            $duration,
            $context ? json_encode($context) : null
        ]);
    }

    public function getPerformanceLogs(?int $userId = null, int $limit = 100): array
    {
        $query = "SELECT p.*, u.email as user_email FROM performance_logs p
                  LEFT JOIN users u ON p.user_id = u.user_id";
        $params = [];

        if ($userId !== null) {
            $query .= " WHERE p.user_id = ?";
            $params[] = $userId;
        }

        $query .= " ORDER BY p.created_at DESC LIMIT " . (int)$limit;

        $stmt = $this->db->getConnection()->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
