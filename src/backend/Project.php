<?php

namespace App;

use PDO;

class Project
{
    public function __construct(private Database $db)
    {
    }

    public function create(int $userId, string $githubRepo): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "INSERT INTO projects (user_id, github_repo) VALUES (?, ?)"
        );
        return $stmt->execute([$userId, $githubRepo]);
    }

    public function findByUserId(int $userId): array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT * FROM projects WHERE user_id = ? ORDER BY created_at DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function delete(int $id, int $userId): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "DELETE FROM projects WHERE id = ? AND user_id = ?"
        );
        return $stmt->execute([$id, $userId]);
    }
}
