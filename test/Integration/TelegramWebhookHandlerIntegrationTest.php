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
        $this->pdo->exec("DROP TABLE IF EXISTS users");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS users (user_id $pk, email VARCHAR(255), telegram_bot_token VARCHAR(255), jules_api_key VARCHAR(255), jules_quota_updated_at TIMESTAMP NULL)");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS user_github_accounts (github_account_id $pk, user_id INT, github_username VARCHAR(255), github_token VARCHAR(255))");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS projects (project_id $pk, user_id INT, github_account_id INT, github_repo VARCHAR(255))");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS task_external_peers (
            peer_id INTEGER PRIMARY KEY AUTOINCREMENT,
            task_id INT NOT NULL,
            source VARCHAR(50) NOT NULL,
            id VARCHAR(255) NOT NULL,
            state VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(task_id, source, id)
        )");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS tasks (task_id $pk, user_id INT, project_id INT, issue_number INT, title VARCHAR(255), pr_url VARCHAR(255))");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS user_telegram_accounts (telegram_account_id $pk, user_id INT, telegram_chat_id BIGINT UNIQUE)");

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
        $this->pdo->exec("INSERT INTO projects (project_id, user_id, github_account_id, github_repo) VALUES (1, 1, 1, 'owner/repo')");
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
        $this->pdo->exec("INSERT INTO projects (project_id, user_id, github_account_id, github_repo) VALUES (1, 1, 1, 'owner/repo')");
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
        $this->pdo->exec("INSERT INTO projects (project_id, user_id, github_account_id, github_repo) VALUES (1, 1, 1, 'owner/repo')");
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
}
