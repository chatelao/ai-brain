<?php

namespace Tests\Integration;

use Tests\TestDatabaseTrait;
use PHPUnit\Framework\TestCase;
use App\TelegramWebhookHandler;
use App\User;
use App\TelegramService;
use App\GitHubService;
use App\Database;
use PDO;

class TelegramWebhookHandlerIntegrationTest extends TestCase
{
    use TestDatabaseTrait;

    private $userModel;
    private $telegramService;
    private $githubService;
    private $handler;
    private $db;
    private $pdo;

    protected function setUp(): void
    {
        $this->pdo = $this->getTestPdo();
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $pk = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';

        $this->pdo->exec("DROP TABLE IF EXISTS user_telegram_accounts");
        $this->pdo->exec("DROP TABLE IF EXISTS tasks");
        $this->pdo->exec("DROP TABLE IF EXISTS projects");
        $this->pdo->exec("DROP TABLE IF EXISTS user_github_accounts");
        $this->pdo->exec("DROP TABLE IF EXISTS notifications");
        $this->pdo->exec("DROP TABLE IF EXISTS users");

        $this->pdo->exec("CREATE TABLE users (user_id $pk, email VARCHAR(255), telegram_bot_token VARCHAR(255), jules_api_key VARCHAR(255), jules_quota_updated_at TIMESTAMP NULL)");
        $this->pdo->exec("CREATE TABLE notifications (notification_id $pk, user_id INT, project_id INT, type VARCHAR(50), title VARCHAR(255), message TEXT, data TEXT, is_read TINYINT DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
        $this->pdo->exec("CREATE TABLE user_github_accounts (github_account_id $pk, user_id INT, github_username VARCHAR(255), github_token VARCHAR(255))");
        $this->pdo->exec("CREATE TABLE projects (project_id $pk, user_id INT, github_account_id INT, github_repo VARCHAR(255), github_token VARCHAR(255))");
        $this->pdo->exec("CREATE TABLE tasks (task_id $pk, user_id INT, project_id INT, issue_number INT, title VARCHAR(255), body TEXT, pr_url VARCHAR(255), jules_url VARCHAR(255), jules_status VARCHAR(50), status VARCHAR(50), github_state VARCHAR(50), jules_session_id VARCHAR(255), last_synced_at TIMESTAMP NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, github_data TEXT, autorepeat_remaining INT DEFAULT 0)");
        $this->pdo->exec("CREATE TABLE user_telegram_accounts (telegram_account_id $pk, user_id INT, telegram_chat_id BIGINT UNIQUE)");

        $this->db = $this->createMock(Database::class);
        $this->db->method('getConnection')->willReturn($this->pdo);

        $this->userModel = new User($this->db);
        $this->telegramService = $this->createMock(TelegramService::class);
        $this->githubService = $this->createMock(GitHubService::class);

        $this->handler = new TelegramWebhookHandler(
            $this->userModel,
            $this->telegramService,
            $this->githubService,
            'secret'
        );
    }

    public function testHandleMergeAction()
    {
        $this->pdo->exec("INSERT INTO users (user_id, email) VALUES (1, 'user@example.com')");
        $this->pdo->exec("INSERT INTO user_telegram_accounts (user_id, telegram_chat_id) VALUES (1, 123)");
        $this->pdo->exec("INSERT INTO user_github_accounts (user_id, github_username, github_token) VALUES (1, 'ghuser', 'ghtoken')");
        $this->pdo->exec("INSERT INTO projects (project_id, user_id, github_account_id, github_repo, github_token) VALUES (1, 1, 1, 'owner/repo', 'token')");
        $this->pdo->exec("INSERT INTO tasks (task_id, user_id, project_id, issue_number, title, pr_url) VALUES (10, 1, 1, 100, 'Test Task', 'https://github.com/owner/repo/pull/50')");

        $update = [
            'callback_query' => [
                'id' => 'cb123',
                'data' => 'merge:10',
                'message' => [
                    'chat' => ['id' => 123],
                    'message_id' => 456,
                    'text' => 'Original Message'
                ]
            ]
        ];

        $this->githubService->expects($this->once())
            ->method('extractPrNumber')
            ->with('https://github.com/owner/repo/pull/50')
            ->willReturn(50);

        $this->githubService->expects($this->once())
            ->method('mergePullRequest')
            ->with('owner/repo', 50, $this->stringContains('Merged via Telegram'));

        $this->githubService->expects($this->once())
            ->method('closeIssue')
            ->with('owner/repo', 100);

        $this->telegramService->expects($this->once())
            ->method('answerCallbackQuery');

        $this->telegramService->expects($this->once())
            ->method('editMessageText')
            ->with(123, 456, $this->stringContains('✅ PR #50 merged'));

        $this->assertTrue($this->handler->handle($update));
    }

    public function testHandleRestartAction()
    {
        $this->pdo->exec("INSERT INTO users (user_id, email) VALUES (1, 'user@example.com')");
        $this->pdo->exec("INSERT INTO user_telegram_accounts (user_id, telegram_chat_id) VALUES (1, 123)");
        $this->pdo->exec("INSERT INTO user_github_accounts (user_id, github_username, github_token) VALUES (1, 'ghuser', 'ghtoken')");
        $this->pdo->exec("INSERT INTO projects (project_id, user_id, github_account_id, github_repo, github_token) VALUES (1, 1, 1, 'owner/repo', 'token')");
        $this->pdo->exec("INSERT INTO tasks (task_id, user_id, project_id, issue_number, title) VALUES (11, 1, 1, 101, 'Test Task')");

        $update = [
            'callback_query' => [
                'id' => 'cb124',
                'data' => 'restart:11',
                'message' => [
                    'chat' => ['id' => 123],
                    'message_id' => 457,
                    'text' => 'Original Message'
                ]
            ]
        ];

        $this->githubService->expects($this->once())
            ->method('removeLabel')
            ->with('owner/repo', 101, 'Jules');

        $this->githubService->expects($this->once())
            ->method('addLabel')
            ->with('owner/repo', 101, 'Jules');

        $this->telegramService->expects($this->once())
            ->method('editMessageText')
            ->with(123, 457, $this->stringContains('🔄 Jules session restarted'));

        $this->assertTrue($this->handler->handle($update));
    }

    public function testHandleRetryAction()
    {
        $this->pdo->exec("INSERT INTO users (user_id, email) VALUES (1, 'user@example.com')");
        $this->pdo->exec("INSERT INTO user_telegram_accounts (user_id, telegram_chat_id) VALUES (1, 123)");
        $this->pdo->exec("INSERT INTO user_github_accounts (user_id, github_username, github_token) VALUES (1, 'ghuser', 'ghtoken')");
        $this->pdo->exec("INSERT INTO projects (project_id, user_id, github_account_id, github_repo, github_token) VALUES (1, 1, 1, 'owner/repo', 'token')");
        $this->pdo->exec("INSERT INTO tasks (task_id, user_id, project_id, issue_number, title) VALUES (12, 1, 1, 102, 'Test Task')");

        $update = [
            'callback_query' => [
                'id' => 'cb125',
                'data' => 'retry:12',
                'message' => [
                    'chat' => ['id' => 123],
                    'message_id' => 458,
                    'text' => 'Original Message'
                ]
            ]
        ];

        $this->githubService->expects($this->once())
            ->method('postComment')
            ->with('owner/repo', 102, 'retry');

        $this->telegramService->expects($this->once())
            ->method('editMessageText')
            ->with(123, 458, $this->stringContains('🚀 Retry signal sent'));

        $this->assertTrue($this->handler->handle($update));
    }

    public function testHandleApprovePlanAction()
    {
        $this->pdo->exec("INSERT INTO users (user_id, email) VALUES (1, 'user@example.com')");
        $this->pdo->exec("INSERT INTO user_telegram_accounts (user_id, telegram_chat_id) VALUES (1, 123)");
        $this->pdo->exec("INSERT INTO user_github_accounts (user_id, github_username, github_token) VALUES (1, 'ghuser', 'ghtoken')");
        $this->pdo->exec("INSERT INTO projects (project_id, user_id, github_account_id, github_repo, github_token) VALUES (1, 1, 1, 'owner/repo', 'token')");
        $this->pdo->exec("INSERT INTO tasks (task_id, user_id, project_id, issue_number, title) VALUES (13, 1, 1, 103, 'Test Task')");

        $update = [
            'callback_query' => [
                'id' => 'cb126',
                'data' => 'approve_plan:13',
                'message' => [
                    'chat' => ['id' => 123],
                    'message_id' => 459,
                    'text' => 'Original Message'
                ]
            ]
        ];

        $this->githubService->expects($this->once())
            ->method('postComment')
            ->with('owner/repo', 103, 'approve plan');

        $this->telegramService->expects($this->once())
            ->method('editMessageText')
            ->with(123, 459, $this->stringContains('✅ Plan approved'));

        $this->assertTrue($this->handler->handle($update));
    }

    public function testHandleFixBugAction()
    {
        $this->pdo->exec("INSERT INTO users (user_id, email) VALUES (1, 'user@example.com')");
        $this->pdo->exec("INSERT INTO user_telegram_accounts (user_id, telegram_chat_id) VALUES (1, 123)");
        $this->pdo->exec("INSERT INTO user_github_accounts (user_id, github_username, github_token) VALUES (1, 'ghuser', 'ghtoken')");
        $this->pdo->exec("INSERT INTO projects (project_id, user_id, github_account_id, github_repo, github_token) VALUES (1, 1, 1, 'owner/repo', 'token')");
        $this->pdo->exec("INSERT INTO tasks (task_id, user_id, project_id, issue_number, title) VALUES (14, 1, 1, 104, 'Test Task')");

        $update = [
            'callback_query' => [
                'id' => 'cb127',
                'data' => 'fix_bug:14',
                'message' => [
                    'chat' => ['id' => 123],
                    'message_id' => 460,
                    'text' => 'Original Message'
                ]
            ]
        ];

        $this->githubService->expects($this->exactly(2))
            ->method('addLabel')
            ->with($this->equalTo('owner/repo'), $this->equalTo(104), $this->callback(function($label) {
                return in_array($label, ['bug', 'Jules']);
            }));

        $this->telegramService->expects($this->once())
            ->method('editMessageText')
            ->with(123, 460, $this->stringContains('🐛 Bug label added'));

        $this->assertTrue($this->handler->handle($update));
    }

    public function testHandleAcknowledgeAction()
    {
        $this->pdo->exec("INSERT INTO users (user_id, email) VALUES (1, 'user@example.com')");
        $this->pdo->exec("INSERT INTO user_telegram_accounts (user_id, telegram_chat_id) VALUES (1, 123)");
        $this->pdo->exec("INSERT INTO user_github_accounts (user_id, github_username, github_token) VALUES (1, 'ghuser', 'ghtoken')");
        $this->pdo->exec("INSERT INTO projects (project_id, user_id, github_account_id, github_repo, github_token) VALUES (1, 1, 1, 'owner/repo', 'token')");
        $this->pdo->exec("INSERT INTO tasks (task_id, user_id, project_id, issue_number, title) VALUES (15, 1, 1, 105, 'Test Task')");

        $update = [
            'callback_query' => [
                'id' => 'cb128',
                'data' => 'acknowledge:15',
                'message' => [
                    'chat' => ['id' => 123],
                    'message_id' => 461,
                    'text' => 'Original Message'
                ]
            ]
        ];

        $this->telegramService->expects($this->once())
            ->method('editMessageText')
            ->with(123, 461, $this->stringContains('✅ Acknowledged'));

        $this->assertTrue($this->handler->handle($update));
    }

    public function testHandleHelpCommand()
    {
        $this->pdo->exec("INSERT INTO users (user_id, email) VALUES (1, 'user@example.com')");
        $this->pdo->exec("INSERT INTO user_telegram_accounts (user_id, telegram_chat_id) VALUES (1, 123)");

        $update = [
            'message' => [
                'chat' => ['id' => 123],
                'text' => '/help'
            ]
        ];

        $this->telegramService->expects($this->once())
            ->method('sendMessage')
            ->with(123, $this->stringContains('Available Commands'));

        $this->assertTrue($this->handler->handle($update));
    }

    public function testHandleTasksCommand()
    {
        $this->pdo->exec("INSERT INTO users (user_id, email) VALUES (1, 'user@example.com')");
        $this->pdo->exec("INSERT INTO user_telegram_accounts (user_id, telegram_chat_id) VALUES (1, 123)");
        $this->pdo->exec("INSERT INTO user_github_accounts (user_id, github_username, github_token) VALUES (1, 'ghuser', 'ghtoken')");
        $this->pdo->exec("INSERT INTO projects (project_id, user_id, github_account_id, github_repo) VALUES (1, 1, 1, 'owner/repo')");
        $this->pdo->exec("INSERT INTO tasks (task_id, user_id, project_id, issue_number, title, status, github_state) VALUES (16, 1, 1, 106, 'Active Task', 'executing', 'open')");

        $update = [
            'message' => [
                'chat' => ['id' => 123],
                'text' => '/tasks'
            ]
        ];

        $this->telegramService->expects($this->once())
            ->method('sendMessage')
            ->with(123, $this->logicalAnd(
                $this->stringContains('Active Tasks'),
                $this->stringContains('#106'),
                $this->stringContains('Active Task')
            ));

        $this->assertTrue($this->handler->handle($update));
    }

    public function testHandleCleanupCommand()
    {
        $this->pdo->exec("INSERT INTO users (user_id, email) VALUES (1, 'user@example.com')");
        $this->pdo->exec("INSERT INTO user_telegram_accounts (user_id, telegram_chat_id) VALUES (1, 123)");

        $update = [
            'message' => [
                'chat' => ['id' => 123],
                'text' => '/cleanup'
            ]
        ];

        $this->telegramService->expects($this->once())
            ->method('sendMessage')
            ->with(123, $this->stringContains('Cleanup complete'));

        $this->assertTrue($this->handler->handle($update));
    }
}
