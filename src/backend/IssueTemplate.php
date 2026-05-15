<?php

namespace App;

use PDO;

class IssueTemplate
{
    public function __construct(private Database $db)
    {
    }

    public function create(int $userId, string $name, string $title, ?string $body): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "INSERT INTO issue_templates (user_id, name, title_template, body_template) VALUES (?, ?, ?, ?)"
        );
        return $stmt->execute([$userId, $name, $title, $body]);
    }

    public function findByUserId(int $userId): array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT * FROM issue_templates WHERE user_id = ? ORDER BY created_at DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT * FROM issue_templates WHERE id = ?"
        );
        $stmt->execute([$id]);
        $template = $stmt->fetch();
        return $template ?: null;
    }

    public function delete(int $id, int $userId): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "DELETE FROM issue_templates WHERE id = ? AND user_id = ?"
        );
        return $stmt->execute([$id, $userId]);
    }

    public function update(int $id, int $userId, string $name, string $title, ?string $body): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "UPDATE issue_templates SET name = ?, title_template = ?, body_template = ? WHERE id = ? AND user_id = ?"
        );
        return $stmt->execute([$name, $title, $body, $id, $userId]);
    }
}
