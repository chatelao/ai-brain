<?php

namespace Tests\Integration;

require_once __DIR__ . '/../../vendor/autoload.php';

use PHPUnit\Framework\TestCase;
use App\Database;
use App\Task;
use App\GitHubService;
use App\NotificationService;
use App\NotificationChannelInterface;
use PDO;

class PollingParityTest extends TestCase
{
    private $db;
    private $pdo;
    private Task $taskModel;
    private $githubServiceMock;
    private $notificationService;
    private array $notificationsSent = [];

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Minimal schema for testing
        $this->pdo->exec("CREATE TABLE projects (project_id INTEGER PRIMARY KEY, user_id INT, github_repo VARCHAR(255))");
        $this->pdo->exec("CREATE TABLE tasks (
            task_id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INT NOT NULL,
            project_id INT NOT NULL,
            issue_number INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            body TEXT,
            status TEXT DEFAULT 'pending',
            github_state VARCHAR(20) DEFAULT 'open',
            github_data TEXT,
            pr_url TEXT,
            jules_session_id TEXT,
            jules_status TEXT,
            jules_url TEXT,
            last_synced_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(project_id, issue_number)
        )");
        $this->pdo->exec("CREATE TABLE notifications (
            notification_id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INT NOT NULL,
            project_id INT,
            type VARCHAR(50) NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            data TEXT,
            is_read BOOLEAN DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $this->pdo->exec("CREATE TABLE user_notification_settings (user_id INT, channel VARCHAR(20), is_enabled BOOLEAN, PRIMARY KEY (user_id, channel))");
        $this->pdo->exec("CREATE TABLE project_notification_settings (project_id INT, notification_type VARCHAR(50), is_enabled BOOLEAN, PRIMARY KEY (project_id, notification_type))");
        $this->pdo->exec("CREATE TABLE user_event_notification_settings (user_id INT, notification_type VARCHAR(50), is_enabled BOOLEAN, PRIMARY KEY (user_id, notification_type))");
        $this->pdo->exec("CREATE TABLE project_status_notification_settings (project_id INT, status VARCHAR(50), is_enabled BOOLEAN, PRIMARY KEY (project_id, status))");
        $this->pdo->exec("CREATE TABLE task_notification_settings (task_id INT PRIMARY KEY, is_muted BOOLEAN DEFAULT 0)");
        $this->pdo->exec("CREATE TABLE users (user_id INTEGER PRIMARY KEY, jules_api_key TEXT, jules_quota_updated_at DATETIME)");

        $this->db = $this->createMock(Database::class);
        $this->db->method('getConnection')->willReturn($this->pdo);

        $this->taskModel = new Task($this->db);
        $this->githubServiceMock = $this->createMock(GitHubService::class);
        $this->notificationService = new NotificationService($this->db);

        // Mock a channel to track notifications
        $channel = new class($this) implements NotificationChannelInterface {
            public function __construct(private $test) {}
            public function send(array $notification, array $actions = []): bool {
                $this->test->recordNotification($notification);
                return true;
            }
        };
        $this->notificationService->registerChannel('mock', $channel);

        // Enable 'mock' channel for user 1
        $this->pdo->exec("INSERT INTO user_notification_settings (user_id, channel, is_enabled) VALUES (1, 'mock', 1)");

        // Insert a user and project
        $this->pdo->exec("INSERT INTO users (user_id, jules_api_key) VALUES (1, 'fake-api-key')");
        $this->pdo->exec("INSERT INTO projects (project_id, user_id, github_repo) VALUES (1, 1, 'owner/repo')");
    }

    public function recordNotification(array $notification): void
    {
        $this->notificationsSent[] = $notification;
    }

    public function testIssueSyncTriggersNotifications()
    {
        $userId = 1;
        $projectId = 1;
        $repo = 'owner/repo';

        // 1. Initial sync (Open)
        $issue = [
            'number' => 123,
            'title' => 'Test Issue',
            'body' => 'Body',
            'state' => 'open',
            'html_url' => 'https://github.com/owner/repo/issues/123'
        ];

        $this->githubServiceMock->method('listIssues')->willReturn([$issue]);

        $this->taskModel->syncIssues($userId, $projectId, $repo, $this->githubServiceMock, $this->notificationService);

        $this->assertGreaterThanOrEqual(1, count($this->notificationsSent));
        $this->assertEquals('github_issue', $this->notificationsSent[0]['type']);
        $this->assertStringContainsString('Issue Opened', $this->notificationsSent[0]['title']);

        // 2. State change sync (Closed)
        $this->notificationsSent = [];
        $issue['state'] = 'closed';
        // Reset mock for next call
        $this->githubServiceMock = $this->createMock(GitHubService::class);
        $this->githubServiceMock->method('listIssues')->willReturn([$issue]);

        $this->taskModel->syncIssues($userId, $projectId, $repo, $this->githubServiceMock, $this->notificationService);

        $this->assertGreaterThanOrEqual(1, count($this->notificationsSent));
        $this->assertStringContainsString('Issue Closed', $this->notificationsSent[0]['title']);
    }

    public function testPrDiscoveryAndCheckPolling()
    {
        $userId = 1;
        $projectId = 1;

        // Setup initial task
        $this->pdo->exec("
            INSERT INTO tasks (task_id, user_id, project_id, issue_number, title, status, github_state, github_data, jules_session_id)
            VALUES (1, 1, 1, 123, 'Test Task', 'in_progress', 'open', '{\"assignee\":{\"login\":\"jules\"}}', 'session-123')
        ");

        $julesServiceMock = $this->createMock(\App\JulesService::class);

        // 1. PR Discovery
        $prUrl = 'https://github.com/owner/repo/pull/456';
        $comments = [
            ['body' => 'I have created a PR: ' . $prUrl, 'user' => ['login' => 'jules']]
        ];

        $this->githubServiceMock->method('getIssueComments')->willReturn($comments);
        $this->githubServiceMock->method('extractPrNumber')->willReturn(456);

        // Jules session status (remain in progress for now)
        $julesServiceMock->method('fetchSessionStatus')->willReturn(['status' => 'coding', 'url' => 'http://jules']);

        $this->notificationsSent = [];
        $this->taskModel->refreshJulesStatus($userId, $this->githubServiceMock, $julesServiceMock, $this->notificationService, 1);

        // Check for "PR Opened" notification
        $foundPrNotif = false;
        foreach ($this->notificationsSent as $notif) {
            if ($notif['type'] === 'github_pr' && strpos($notif['title'], 'PR Opened') !== false) {
                $foundPrNotif = true;
                break;
            }
        }
        $this->assertTrue($foundPrNotif, "Should have sent PR Opened notification");

        // 2. Check Suite Polling (Failure)
        // Ensure pr_url is now in DB for next run
        $this->pdo->exec("UPDATE tasks SET pr_url = '$prUrl' WHERE task_id = 1");

        $this->githubServiceMock = $this->createMock(GitHubService::class); // Reset
        $this->githubServiceMock->method('extractPrNumber')->willReturn(456);
        $this->githubServiceMock->method('getPullRequest')->willReturn(['head' => ['sha' => 'sha123']]);
        $this->githubServiceMock->method('getCheckSuites')->willReturn([
            'check_suites' => [
                ['status' => 'completed', 'conclusion' => 'failure']
            ]
        ]);

        // Jules still coding
        $julesServiceMock->method('fetchSessionStatus')->willReturn(['status' => 'coding', 'url' => 'http://jules']);

        $this->notificationsSent = [];
        $this->taskModel->refreshJulesStatus($userId, $this->githubServiceMock, $julesServiceMock, $this->notificationService, 1);

        $foundCheckNotif = false;
        foreach ($this->notificationsSent as $notif) {
            if ($notif['type'] === 'task_status' && strpos($notif['title'], 'PR Failed') !== false) {
                $foundCheckNotif = true;
                break;
            }
        }
        $this->assertTrue($foundCheckNotif, "Should have sent PR Failed notification");

        $task = $this->taskModel->findById(1);
        $this->assertEquals('failed_pr', $task['status']);
    }
}
