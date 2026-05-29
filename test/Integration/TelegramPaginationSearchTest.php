<?php

namespace Test\Integration;

use App\TelegramWebhookHandler;
use App\User;
use App\TelegramService;
use App\GitHubService;
use App\Database;
use App\Task;
use PHPUnit\Framework\TestCase;

class TelegramPaginationSearchTest extends TestCase
{
    private $db;
    private $userModel;
    private $telegramService;
    private $githubService;
    private $handler;

    protected function setUp(): void
    {
        Database::resetConnection();
        $this->db = new Database(null, ':memory:');
        $this->db->getConnection()->exec("
            CREATE TABLE users (
                user_id INTEGER PRIMARY KEY AUTOINCREMENT,
                github_token TEXT,
                jules_api_key TEXT,
                jules_quota_usage INTEGER DEFAULT 0,
                jules_quota_limit INTEGER DEFAULT 0,
                jules_quota_updated_at DATETIME,
                automations_enabled INTEGER DEFAULT 1
            );
            CREATE TABLE projects (
                project_id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                github_repo TEXT,
                github_token TEXT
            );
            CREATE TABLE tasks (
                task_id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                project_id INTEGER,
                issue_number INTEGER,
                title TEXT,
                body TEXT,
                status TEXT,
                github_state TEXT,
                pr_url TEXT,
                jules_session_id TEXT,
                jules_status TEXT,
                jules_url TEXT,
                agent_response TEXT,
                autorepeat_remaining INTEGER DEFAULT 0,
                last_synced_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(project_id, issue_number)
            );
            CREATE TABLE notifications (
                notification_id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                type TEXT,
                title TEXT,
                message TEXT,
                metadata TEXT,
                is_read INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE user_notification_settings (
                user_id INTEGER PRIMARY KEY,
                settings TEXT
            );
            CREATE TABLE user_telegram_accounts (
                user_id INTEGER PRIMARY KEY,
                telegram_chat_id INTEGER UNIQUE,
                telegram_username TEXT,
                linked_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $this->userModel = new User($this->db);
        $this->telegramService = $this->createMock(TelegramService::class);
        $this->githubService = $this->createMock(GitHubService::class);

        $this->handler = new class($this->userModel, $this->telegramService, $this->githubService, null) extends TelegramWebhookHandler {
             public function setDb(Database $db) {
                 // The base class uses the userModel's DB, which is already set
             }
        };

        // Create a test user linked to Telegram
        $this->db->getConnection()->prepare("INSERT INTO users (github_token) VALUES (?)")
            ->execute(['fake_token']);
        $this->db->getConnection()->prepare("INSERT INTO user_telegram_accounts (user_id, telegram_chat_id) VALUES (?, ?)")
            ->execute([1, 12345]);

        $this->db->getConnection()->prepare("INSERT INTO projects (user_id, github_repo) VALUES (?, ?)")
            ->execute([1, 'owner/repo']);
    }

    private function createTasks(int $count, string $titlePrefix = 'Task', int $userId = 1, int $projectId = 1, int $startIssueNumber = 1)
    {
        $stmt = $this->db->getConnection()->prepare("
            INSERT INTO tasks (user_id, project_id, issue_number, title, status, github_state)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        for ($i = 0; $i < $count; $i++) {
            $issueNumber = $startIssueNumber + $i;
            $stmt->execute([$userId, $projectId, $issueNumber, "$titlePrefix $issueNumber", Task::STATUS_CREATED, 'open']);
        }
    }

    public function testPaginationInTasksList()
    {
        $this->createTasks(15); // 15 tasks, so 2 pages

        $this->telegramService->expects($this->once())
            ->method('sendMessage')
            ->with(
                $this->equalTo(12345),
                $this->stringContains('Showing 1-10 of 15 tasks'),
                $this->callback(function($options) {
                    $keyboard = $options['reply_markup']['inline_keyboard'];
                    $lastRow = end($keyboard);
                    return count($lastRow) === 1 && $lastRow[0]['text'] === 'Next ➡️';
                })
            );

        $update = [
            'message' => [
                'chat' => ['id' => 12345],
                'text' => '/tasks'
            ]
        ];

        $this->handler->handle($update);
    }

    public function testNextPageNavigation()
    {
        $this->createTasks(15);

        $this->telegramService->expects($this->once())
            ->method('editMessageText')
            ->with(
                $this->equalTo(12345),
                $this->equalTo(678),
                $this->stringContains('Showing 11-15 of 15 tasks'),
                $this->callback(function($options) {
                    $keyboard = $options['reply_markup']['inline_keyboard'];
                    $lastRow = end($keyboard);
                    return count($lastRow) === 1 && $lastRow[0]['text'] === '⬅️ Previous';
                })
            );

        $callbackUpdate = [
            'callback_query' => [
                'id' => 'cb123',
                'data' => 'list_tasks:0:2', // 0 means all projects, page 2
                'message' => [
                    'chat' => ['id' => 12345],
                    'message_id' => 678,
                    'text' => 'Original Text'
                ]
            ]
        ];

        $this->handler->handle($callbackUpdate);
    }

    public function testSearchCommand()
    {
        $this->createTasks(5, 'Apple');
        $this->createTasks(5, 'Banana', 1, 1, 6); // 6 to 10
        // Total 10 tasks now

        $this->telegramService->expects($this->once())
            ->method('sendMessage')
            ->with(
                $this->equalTo(12345),
                $this->stringContains('Search Results for "Apple"'),
                $this->callback(function($options) {
                    return count($options['reply_markup']['inline_keyboard']) === 5;
                })
            );

        $update = [
            'message' => [
                'chat' => ['id' => 12345],
                'text' => '/search Apple'
            ]
        ];

        $this->handler->handle($update);
    }

    public function testSearchPagination()
    {
        $this->createTasks(12, 'Cherry');

        $this->telegramService->expects($this->once())
            ->method('sendMessage')
            ->with(
                $this->equalTo(12345),
                $this->stringContains('Showing 1-10 of 12 results'),
                $this->callback(function($options) {
                    $keyboard = $options['reply_markup']['inline_keyboard'];
                    $lastRow = end($keyboard);
                    return $lastRow[0]['text'] === 'Next ➡️';
                })
            );

        $update = [
            'message' => [
                'chat' => ['id' => 12345],
                'text' => '/search Cherry'
            ]
        ];

        $this->handler->handle($update);
    }
}
