<?php

namespace App;

use PDO;
use Exception;
use Ramsey\Uuid\Uuid;

class Project
{
    public function __construct(private Database $db)
    {
    }

    public function create(string $userId, string $githubAccountId, string $githubRepo): bool
    {
        // Verify that the github account belongs to the user
        $stmt = $this->db->getConnection()->prepare(
            "SELECT github_account_id FROM user_github_accounts WHERE github_account_id = ? AND user_id = ?"
        );
        $stmt->execute([$githubAccountId, $userId]);
        if (!$stmt->fetch()) {
            throw new Exception("Invalid GitHub account selected.");
        }

        $projectId = Uuid::uuid4()->toString();
        $webhookSecret = bin2hex(random_bytes(16));
        $stmt = $this->db->getConnection()->prepare(
            "INSERT INTO projects (project_id, user_id, github_account_id, github_repo, webhook_secret) VALUES (?, ?, ?, ?, ?)"
        );
        return $stmt->execute([$projectId, $userId, $githubAccountId, $githubRepo, $webhookSecret]);
    }

    public function findByRepo(string $githubRepo): array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT p.*, a.github_token, a.github_username
             FROM projects p
             JOIN user_github_accounts a ON p.github_account_id = a.github_account_id
             WHERE p.github_repo = ?"
        );
        $stmt->execute([$githubRepo]);
        return $stmt->fetchAll();
    }

    public function findByUserId(string $userId): array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT p.*, a.github_username
             FROM projects p
             JOIN user_github_accounts a ON p.github_account_id = a.github_account_id
             WHERE p.user_id = ?
             ORDER BY p.created_at DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function findById(string $id): ?array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT p.*, a.github_token, a.github_username
             FROM projects p
             JOIN user_github_accounts a ON p.github_account_id = a.github_account_id
             WHERE p.project_id = ?"
        );
        $stmt->execute([$id]);
        $project = $stmt->fetch();
        return $project ?: null;
    }

    public function delete(string $id, string $userId): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "DELETE FROM projects WHERE project_id = ? AND user_id = ?"
        );
        return $stmt->execute([$id, $userId]);
    }
}
