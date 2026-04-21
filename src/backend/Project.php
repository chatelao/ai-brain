<?php

namespace App;

use PDO;
use Exception;

class Project
{
    public function __construct(private Database $db)
    {
    }

    public function create(int $userId, int $githubAccountId, string $githubRepo): bool
    {
        // Verify that the github account belongs to the user
        $stmt = $this->db->getConnection()->prepare(
            "SELECT id FROM user_github_accounts WHERE id = ? AND user_id = ?"
        );
        $stmt->execute([$githubAccountId, $userId]);
        if (!$stmt->fetch()) {
            throw new Exception("Invalid GitHub account selected.");
        }

        $webhookSecret = bin2hex(random_bytes(16));
        $stmt = $this->db->getConnection()->prepare(
            "INSERT INTO projects (user_id, github_account_id, github_repo, webhook_secret) VALUES (?, ?, ?, ?)"
        );
        return $stmt->execute([$userId, $githubAccountId, $githubRepo, $webhookSecret]);
    }

    public function findByRepo(string $githubRepo): array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT p.*, a.github_token, a.github_username
             FROM projects p
             JOIN user_github_accounts a ON p.github_account_id = a.id
             WHERE p.github_repo = ?"
        );
        $stmt->execute([$githubRepo]);
        return $stmt->fetchAll();
    }

    public function findByUserId(int $userId): array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT p.*, a.github_username
             FROM projects p
             JOIN user_github_accounts a ON p.github_account_id = a.id
             WHERE p.user_id = ?
             ORDER BY p.created_at DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT p.*, a.github_token, a.github_username
             FROM projects p
             JOIN user_github_accounts a ON p.github_account_id = a.id
             WHERE p.id = ?"
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
