<?php

namespace App;

use PDO;

class IssueTemplate
{
    public function __construct(private Database $db)
    {
    }

    public function create(int $userId, string $name, string $title, ?string $body, ?string $parameterConfig = null): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "INSERT INTO issue_templates (user_id, name, title_template, body_template, parameter_config) VALUES (?, ?, ?, ?, ?)"
        );
        return $stmt->execute([$userId, $name, $title, $body, $parameterConfig]);
    }

    public function findByUserId(int $userId): array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT * FROM issue_templates WHERE user_id = ? ORDER BY created_at DESC"
        );
        $stmt->execute([$userId]);
        $templates = $stmt->fetchAll();

        foreach ($templates as &$template) {
            $template['parameter_config'] = $template['parameter_config'] ? json_decode($template['parameter_config'], true) : [];
        }

        return $templates;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT * FROM issue_templates WHERE issue_template_id = ?"
        );
        $stmt->execute([$id]);
        $template = $stmt->fetch();

        if ($template) {
            $template['parameter_config'] = $template['parameter_config'] ? json_decode($template['parameter_config'], true) : [];
            return $template;
        }

        return null;
    }

    public function delete(int $id, int $userId): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "DELETE FROM issue_templates WHERE issue_template_id = ? AND user_id = ?"
        );
        return $stmt->execute([$id, $userId]);
    }

    public function update(int $id, int $userId, string $name, string $title, ?string $body, ?string $parameterConfig = null): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "UPDATE issue_templates SET name = ?, title_template = ?, body_template = ?, parameter_config = ? WHERE issue_template_id = ? AND user_id = ?"
        );
        return $stmt->execute([$name, $title, $body, $parameterConfig, $id, $userId]);
    }
}
