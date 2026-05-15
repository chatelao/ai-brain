<?php

namespace App;

use PDO;
use Ramsey\Uuid\Uuid;

class IssueTemplate
{
    public function __construct(private Database $db)
    {
    }

    public function create(string $userId, string $name, string $title, ?string $body, ?string $parameterConfig = null): bool
    {
        $templateId = Uuid::uuid4()->toString();
        $stmt = $this->db->getConnection()->prepare(
            "INSERT INTO issue_templates (issue_template_id, user_id, name, title_template, body_template, parameter_config) VALUES (?, ?, ?, ?, ?, ?)"
        );
        return $stmt->execute([$templateId, $userId, $name, $title, $body, $parameterConfig]);
    }

    public function findByUserId(string $userId): array
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

    public function findById(string $id): ?array
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

    public function delete(string $id, string $userId): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "DELETE FROM issue_templates WHERE issue_template_id = ? AND user_id = ?"
        );
        return $stmt->execute([$id, $userId]);
    }

    public function update(string $id, string $userId, string $name, string $title, ?string $body, ?string $parameterConfig = null): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "UPDATE issue_templates SET name = ?, title_template = ?, body_template = ?, parameter_config = ? WHERE issue_template_id = ? AND user_id = ?"
        );
        return $stmt->execute([$name, $title, $body, $parameterConfig, $id, $userId]);
    }
}
