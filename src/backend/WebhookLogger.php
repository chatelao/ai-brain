<?php

namespace App;

use PDO;

class WebhookLogger
{
    public function __construct(private Database $db)
    {
    }

    public function log(int $userId, string $endpoint, ?string $payload, ?string $headers, int $statusCode, ?string $errorMessage = null): bool
    {
        $conn = $this->db->getConnection();

        $stmt = $conn->prepare(
            "INSERT INTO webhook_logs (user_id, endpoint, payload, headers, status_code, error_message)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $result = $stmt->execute([$userId, $endpoint, $payload, $headers, $statusCode, $errorMessage]);

        if ($result) {
            $this->pruneLogs($userId, $endpoint);
        }

        return $result;
    }

    private function pruneLogs(int $userId, string $endpoint): void
    {
        $conn = $this->db->getConnection();

        // Find the 5th newest log ID for this user and endpoint
        $stmt = $conn->prepare(
            "SELECT log_id FROM webhook_logs
             WHERE user_id = ? AND endpoint = ?
             ORDER BY created_at DESC, log_id DESC
             LIMIT 1 OFFSET 4"
        );
        $stmt->execute([$userId, $endpoint]);
        $fifthLogId = $stmt->fetchColumn();

        if ($fifthLogId) {
            // Delete all older logs
            // Simplified approach: just delete logs with ID less than the fifth newest if we assume IDs are increasing.
            // But if timestamps are identical, we need to be careful.

            $stmt = $conn->prepare(
                "DELETE FROM webhook_logs
                 WHERE user_id = ? AND endpoint = ? AND log_id < ?"
            );
            $stmt->execute([$userId, $endpoint, $fifthLogId]);
        }
    }

    public function getLogsByUser(int $userId): array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT * FROM webhook_logs WHERE user_id = ? ORDER BY created_at DESC, log_id DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
}
