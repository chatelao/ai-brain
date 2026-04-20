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
        $webhookSecret = bin2hex(random_bytes(16));
        $stmt = $this->db->getConnection()->prepare(
            "INSERT INTO projects (user_id, github_repo, webhook_secret) VALUES (?, ?, ?)"
        );
        return $stmt->execute([$userId, $githubRepo, $webhookSecret]);
    }

    public function findByRepo(string $githubRepo): array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT * FROM projects WHERE github_repo = ?"
        );
        $stmt->execute([$githubRepo]);
        return $stmt->fetchAll();
    }

    public function findByUserId(int $userId): array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT * FROM projects WHERE user_id = ? ORDER BY created_at DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT * FROM projects WHERE id = ?"
        );
        $stmt->execute([$id]);
        $project = $stmt->fetch();
        return $project ?: null;
    }

    public function delete(int $id, int $userId): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "DELETE FROM projects WHERE id = ? AND user_id = ?"
        );
        return $stmt->execute([$id, $userId]);
    }
}
