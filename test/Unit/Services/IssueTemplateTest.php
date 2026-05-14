<?php

namespace Test\Unit\Services;

use App\Database;
use App\IssueTemplate;
use PHPUnit\Framework\TestCase;
use PDO;

class IssueTemplateTest extends TestCase
{
    private $db;
    private $templateModel;

    protected function setUp(): void
    {
        Database::resetConnection();
        $this->db = new Database(null, ':memory:');
        $pdo = $this->db->getConnection();

        $pdo->exec("CREATE TABLE users (user_id INTEGER PRIMARY KEY AUTOINCREMENT, google_id TEXT, name TEXT, email TEXT)");
        $pdo->exec("CREATE TABLE issue_templates (
            issue_template_id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            name TEXT,
            title_template TEXT,
            body_template TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id)
        )");

        $pdo->exec("INSERT INTO users (google_id, name, email) VALUES ('123', 'Test User', 'test@example.com')");

        $this->templateModel = new IssueTemplate($this->db);
    }

    public function testCreateAndFindTemplate()
    {
        $userId = 1;
        $result = $this->templateModel->create($userId, 'Bug Template', 'Bug: %1', 'Details: %2');
        $this->assertTrue($result);

        $templates = $this->templateModel->findByUserId($userId);
        $this->assertCount(1, $templates);
        $this->assertEquals('Bug Template', $templates[0]['name']);
        $this->assertEquals('Bug: %1', $templates[0]['title_template']);
    }

    public function testFindById()
    {
        $userId = 1;
        $this->templateModel->create($userId, 'Template 1', 'Title 1', 'Body 1');
        $templates = $this->templateModel->findByUserId($userId);
        $id = $templates[0]['issue_template_id'];

        $template = $this->templateModel->findById($id);
        $this->assertNotNull($template);
        $this->assertEquals('Template 1', $template['name']);
    }

    public function testDeleteTemplate()
    {
        $userId = 1;
        $this->templateModel->create($userId, 'To Delete', 'Title', 'Body');
        $templates = $this->templateModel->findByUserId($userId);
        $id = $templates[0]['issue_template_id'];

        $result = $this->templateModel->delete($id, $userId);
        $this->assertTrue($result);

        $templatesAfter = $this->templateModel->findByUserId($userId);
        $this->assertCount(0, $templatesAfter);
    }
}
