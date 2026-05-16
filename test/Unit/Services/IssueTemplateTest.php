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
            parameter_config TEXT,
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

    public function testUpdateTemplate()
    {
        $userId = 1;
        $this->templateModel->create($userId, 'Original Name', 'Original Title', 'Original Body');
        $templates = $this->templateModel->findByUserId($userId);
        $id = $templates[0]['issue_template_id'];

        $result = $this->templateModel->update($id, $userId, 'Updated Name', 'Updated Title', 'Updated Body');
        $this->assertTrue($result);

        $template = $this->templateModel->findById($id);
        $this->assertEquals('Updated Name', $template['name']);
        $this->assertEquals('Updated Title', $template['title_template']);
        $this->assertEquals('Updated Body', $template['body_template']);
    }

    public function testParameterConfig()
    {
        $userId = 1;
        $config = json_encode(['%1' => 'Module', '%2' => 'Feature']);
        $this->templateModel->create($userId, 'Config Template', 'Bug in %1', 'Fix %2', $config);

        $templates = $this->templateModel->findByUserId($userId);
        $this->assertCount(1, $templates);
        $this->assertEquals(['%1' => 'Module', '%2' => 'Feature'], $templates[0]['parameter_config']);

        $id = $templates[0]['issue_template_id'];
        $newConfig = json_encode(['%1' => 'New Module']);
        $this->templateModel->update($id, $userId, 'Config Template', 'Bug in %1', 'Fix %2', $newConfig);

        $template = $this->templateModel->findById($id);
        $this->assertEquals(['%1' => 'New Module'], $template['parameter_config']);
    }

    public function testExportToSql()
    {
        $userId = 1;
        $this->templateModel->create($userId, "O'Reilly Template", 'Bug: %1', 'Body with "quotes"');

        $sql = $this->templateModel->exportToSql($userId);

        $this->assertStringContainsString("-- Issue Templates Export for User $userId", $sql);
        $this->assertStringContainsString("INSERT INTO issue_templates", $sql);
        // SQLite quote for O'Reilly is O''Reilly, MySQL is 'O\'Reilly' or 'O''Reilly' depending on mode.
        // PDO::quote for sqlite uses ''
        $this->assertStringContainsString("'O''Reilly Template'", $sql);
        $this->assertStringContainsString("'Bug: %1'", $sql);
        $this->assertStringContainsString("'Body with \"quotes\"'", $sql);
    }
}
