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
                "UPDATE users SET name = ?, email = ?, avatar = ? WHERE google_id = ?"
            );
            $stmt->execute([
                $data['name'],
                $data['email'],
                $data['avatar'] ?? null,
                $data['google_id']
            ]);
            return array_merge($user, $data);
        } else {
            $stmt = $this->db->getConnection()->prepare(
                "INSERT INTO users (google_id, name, email, avatar) VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([
                $data['google_id'],
                $data['name'],
                $data['email'],
                $data['avatar'] ?? null
            ]);
            $id = $this->db->getConnection()->lastInsertId();
            return $this->findById((int)$id);
        }
    }

    public function updateGitHubCredentials(int $id, string $token, string $username): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "UPDATE users SET github_token = ?, github_username = ? WHERE id = ?"
        );
        return $stmt->execute([$token, $username, $id]);
    }
}
