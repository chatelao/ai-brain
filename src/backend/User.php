<?php

namespace App;

use PDO;

class User
{
    public function __construct(private Database $db)
    {
    }

    public function getDb(): Database
    {
        return $this->db;
    }

    public function findByGoogleId(string $googleId): ?array
    {
        $stmt = $this->db->getConnection()->prepare("SELECT * FROM users WHERE google_id = ?");
        $stmt->execute([$googleId]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findByGithubId(string $githubId): ?array
    {
        $stmt = $this->db->getConnection()->prepare("SELECT * FROM users WHERE github_id = ?");
        $stmt->execute([$githubId]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->getConnection()->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->getConnection()->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function createOrUpdate(array $data): ?array
    {
        $user = null;
        if (isset($data['google_id'])) {
            $user = $this->findByGoogleId($data['google_id']);
        } elseif (isset($data['github_id'])) {
            $user = $this->findByGithubId($data['github_id']);
        }

        if (!$user && isset($data['email'])) {
            $user = $this->findByEmail($data['email']);
        }

        if ($user) {
            $updateFields = [];
            $params = [];

            if (isset($data['name'])) {
                $updateFields[] = "name = ?";
                $params[] = $data['name'];
            }
            if (isset($data['email'])) {
                $updateFields[] = "email = ?";
                $params[] = $data['email'];
            }
            if (isset($data['avatar'])) {
                $updateFields[] = "avatar = ?";
                $params[] = $data['avatar'];
            }
            if (isset($data['google_id'])) {
                $updateFields[] = "google_id = ?";
                $params[] = $data['google_id'];
            }
            if (isset($data['github_id'])) {
                $updateFields[] = "github_id = ?";
                $params[] = $data['github_id'];
            }

            if (!empty($updateFields)) {
                $sql = "UPDATE users SET " . implode(", ", $updateFields) . " WHERE user_id = ?";
                $params[] = $user['user_id'];
                $stmt = $this->db->getConnection()->prepare($sql);
                $stmt->execute($params);
            }

            return $this->findById($user['user_id']);
        } else {
            $fields = ['name', 'email', 'avatar', 'google_id', 'github_id', 'role'];
            $insertFields = [];
            $placeholders = [];
            $params = [];

            foreach ($fields as $field) {
                if (isset($data[$field])) {
                    $insertFields[] = $field;
                    $placeholders[] = "?";
                    $params[] = $data[$field];
                }
            }

            if (!in_array('role', $insertFields)) {
                $insertFields[] = 'role';
                $placeholders[] = "?";
                $params[] = 'user';
            }

            $sql = "INSERT INTO users (" . implode(", ", $insertFields) . ") VALUES (" . implode(", ", $placeholders) . ")";
            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->execute($params);
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
            "SELECT u.*, COUNT(p.project_id) as project_count
             FROM users u
             LEFT JOIN projects p ON u.user_id = p.user_id
             GROUP BY u.user_id
             ORDER BY u.created_at DESC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function generateTelegramLinkToken(int $userId): string
    {
        $token = bin2hex(random_bytes(16));
        $stmt = $this->db->getConnection()->prepare(
            "UPDATE users SET telegram_link_token = ? WHERE user_id = ?"
        );
        $stmt->execute([$token, $userId]);
        return $token;
    }

    public function linkTelegramAccount(string $token, int $chatId): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT user_id FROM users WHERE telegram_link_token = ?"
        );
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if (!$user) {
            return false;
        }

        $stmt = $this->db->getConnection()->prepare(
            "INSERT INTO user_telegram_accounts (user_id, telegram_chat_id) VALUES (?, ?)"
        );
        $success = $stmt->execute([$user['user_id'], $chatId]);

        if ($success) {
            // Clear the token after successful linking
            $stmt = $this->db->getConnection()->prepare(
                "UPDATE users SET telegram_link_token = NULL WHERE user_id = ?"
            );
            $stmt->execute([$user['user_id']]);
        }

        return $success;
    }

    public function getTelegramChatId(int $userId): ?int
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT telegram_chat_id FROM user_telegram_accounts WHERE user_id = ?"
        );
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        return $result ? (int)$result['telegram_chat_id'] : null;
    }

    public function findByTelegramChatId(int $chatId): ?array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT u.* FROM users u
             JOIN user_telegram_accounts uta ON u.user_id = uta.user_id
             WHERE uta.telegram_chat_id = ?"
        );
        $stmt->execute([$chatId]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function updateJulesApiKey(int $userId, ?string $apiKey): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "UPDATE users SET jules_api_key = ? WHERE user_id = ?"
        );
        return $stmt->execute([$apiKey, $userId]);
    }

    public function updateTelegramConfig(int $userId, ?string $botToken, ?string $webhookSecret, ?string $botName = null): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "UPDATE users SET telegram_bot_token = ?, telegram_webhook_secret = ?, telegram_bot_name = ? WHERE user_id = ?"
        );
        return $stmt->execute([$botToken, $webhookSecret, $botName, $userId]);
    }

    public function updateJulesQuota(int $userId, int $usage, int $limit): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "UPDATE users SET jules_quota_usage = ?, jules_quota_limit = ?, jules_quota_updated_at = ? WHERE user_id = ?"
        );
        return $stmt->execute([$usage, $limit, date('Y-m-d H:i:s'), $userId]);
    }

    public function updateBlocklyConfig(int $userId, ?string $config): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "UPDATE users SET blockly_config = ? WHERE user_id = ?"
        );
        return $stmt->execute([$config, $userId]);
    }
}
