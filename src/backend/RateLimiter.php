<?php

namespace App;

use PDO;

class RateLimiter
{
    public function __construct(private Database $db)
    {
    }

    /**
     * Checks if the request is allowed under the rate limit.
     *
     * @param string $key Unique key for the rate limit (e.g., IP + action)
     * @param int $limit Maximum number of requests allowed in the window
     * @param int $windowSeconds Window size in seconds
     * @return bool True if allowed, false if rate limit exceeded
     */
    public function check(string $key, int $limit, int $windowSeconds): bool
    {
        $now = time();
        $conn = $this->db->getConnection();

        $stmt = $conn->prepare("SELECT request_count, reset_at FROM rate_limits WHERE rate_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();

        if ($row) {
            $resetAt = strtotime($row['reset_at']);
            if ($now > $resetAt) {
                // Window expired, reset
                $newResetAt = date('Y-m-d H:i:s', $now + $windowSeconds);
                $updateStmt = $conn->prepare(
                    "UPDATE rate_limits SET request_count = 1, reset_at = ? WHERE rate_key = ?"
                );
                $updateStmt->execute([$newResetAt, $key]);
                return true;
            } else {
                if ($row['request_count'] < $limit) {
                    $updateStmt = $conn->prepare(
                        "UPDATE rate_limits SET request_count = request_count + 1 WHERE rate_key = ?"
                    );
                    $updateStmt->execute([$key]);
                    return true;
                } else {
                    return false;
                }
            }
        } else {
            $resetAt = date('Y-m-d H:i:s', $now + $windowSeconds);
            $insertStmt = $conn->prepare(
                "INSERT INTO rate_limits (rate_key, request_count, reset_at) VALUES (?, 1, ?)"
            );
            $insertStmt->execute([$key, $resetAt]);
            return true;
        }
    }

    /**
     * Helper to get the client IP address.
     */
    public function getIpAddress(): string
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}
