<?php

namespace App;

use PDO;

class User
{
    public function __construct(private Database $db)
    {
    }

    public function findByGoogleId(string $googleId): ?array
    {
        $stmt = $this->db->getConnection()->prepare("SELECT * FROM users WHERE google_id = ?");
        $stmt->execute([$googleId]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->getConnection()->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function createOrUpdate(array $data): ?array
    {
        $user = $this->findByGoogleId($data['google_id']);

        if ($user) {
            $stmt = $this->db->getConnection()->prepare(
                "UPDATE users SET name = ?, email = ?, avatar = ?, role = ? WHERE google_id = ?"
            );
            $stmt->execute([
                $data['name'],
                $data['email'],
                $data['avatar'] ?? null,
                $data['role'] ?? $user['role'],
                $data['google_id']
            ]);
            return array_merge($user, $data);
        } else {
            $stmt = $this->db->getConnection()->prepare(
                "INSERT INTO users (google_id, name, email, avatar, role) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $data['google_id'],
                $data['name'],
                $data['email'],
                $data['avatar'] ?? null,
                $data['role'] ?? 'user'
            ]);
            $id = $this->db->getConnection()->lastInsertId();
            return $this->findById((int)$id);
        }
    }

    public function addGitHubAccount(int $userId, string $token, string $username): bool
    {
        // Check database type first
        $dbType = $this->db->getConnection()->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($dbType === 'sqlite') {
            $stmt = $this->db->getConnection()->prepare(
                "INSERT INTO user_github_accounts (user_id, github_token, github_username)
                 VALUES (?, ?, ?)
                 ON CONFLICT(user_id, github_username) DO UPDATE SET github_token = excluded.github_token"
            );
            return $stmt->execute([$userId, $token, $username]);
        }

        $stmt = $this->db->getConnection()->prepare(
            "INSERT INTO user_github_accounts (user_id, github_token, github_username)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE github_token = ?"
        );
        return $stmt->execute([$userId, $token, $username, $token]);
    }

    public function getGitHubAccounts(int $userId): array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT * FROM user_github_accounts WHERE user_id = ?"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function getAllUsersWithProjectCount(): array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT u.*, COUNT(p.id) as project_count
             FROM users u
             LEFT JOIN projects p ON u.id = p.user_id
             GROUP BY u.id
             ORDER BY u.created_at DESC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
