<?php

namespace App;

use PDO;
use Exception;

class Project
{
    public function __construct(private Database $db)
    {
    }

    public function create(int $userId, int $userGithubAccountId, string $githubRepo): bool
    {
        // Verify that the github account belongs to the user
        $stmt = $this->db->getConnection()->prepare(
            "SELECT user_github_account_id FROM user_github_accounts WHERE user_github_account_id = ? AND user_id = ?"
        );
        $stmt->execute([$userGithubAccountId, $userId]);
        if (!$stmt->fetch()) {
            throw new Exception("Invalid GitHub account selected.");
        }

        $webhookSecret = bin2hex(random_bytes(16));
        $stmt = $this->db->getConnection()->prepare(
            "INSERT INTO projects (user_id, user_github_account_id, github_repo, webhook_secret) VALUES (?, ?, ?, ?)"
        );
        return $stmt->execute([$userId, $userGithubAccountId, $githubRepo, $webhookSecret]);
    }

    public function findByRepo(string $githubRepo): array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT p.*, a.github_token, a.github_username
             FROM projects p
             JOIN user_github_accounts a ON p.user_github_account_id = a.user_github_account_id
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
             JOIN user_github_accounts a ON p.user_github_account_id = a.user_github_account_id
             WHERE p.user_id = ?
             ORDER BY p.created_at DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function findById(int $projectId): ?array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT p.*, a.github_token, a.github_username
             FROM projects p
             JOIN user_github_accounts a ON p.user_github_account_id = a.user_github_account_id
             WHERE p.project_id = ?"
        );
        $stmt->execute([$projectId]);
        $project = $stmt->fetch();
        return $project ?: null;
    }

    public function delete(int $projectId, int $userId): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "DELETE FROM projects WHERE project_id = ? AND user_id = ?"
        );
        return $stmt->execute([$projectId, $userId]);
    }
}
