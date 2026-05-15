<?php

namespace Test\Integration;

use App\Database;
use App\IssueTemplate;
use App\GitHubService;
use PHPUnit\Framework\TestCase;
use Github\Client;
use Github\Api\Issue;

class IssueTemplateIntegrationTest extends TestCase
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

    public function testApplyTemplateAndCreateIssue()
    {
        $userId = 1;
        $this->templateModel->create($userId, 'Bug Template', 'Bug: %1 in %2', 'Found %1');
        $template = $this->templateModel->findByUserId($userId)[0];

        $params = ['%1' => 'Crash', '%2' => 'Login'];
        $renderedTitle = strtr($template['title_template'], $params);
        $renderedBody = strtr($template['body_template'], $params);

        $this->assertEquals('Bug: Crash in Login', $renderedTitle);
        $this->assertEquals('Found Crash', $renderedBody);

        // Mock GitHubService
        $mockIssueApi = $this->createMock(Issue::class);
        $mockIssueApi->expects($this->once())
            ->method('create')
            ->with('owner', 'repo', [
                'title' => 'Bug: Crash in Login',
                'body' => 'Found Crash',
                'labels' => []
            ])
            ->willReturn(['number' => 1]);

        $mockClient = $this->createMock(Client::class);
        $mockClient->method('api')->with('issue')->willReturn($mockIssueApi);

        $githubService = new GitHubService($mockClient);
        $result = $githubService->createIssue('owner/repo', $renderedTitle, $renderedBody, []);

        $this->assertEquals(1, $result['number']);
    }
}
