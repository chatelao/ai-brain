<?php

namespace Tests\Unit\DB;

use PHPUnit\Framework\TestCase;
use PDO;
use Tests\TestDatabaseTrait;

class SchemaTest extends TestCase
{
    use TestDatabaseTrait;

    private $pdo;

    protected function setUp(): void
    {
        $this->pdo = $this->getTestPdo();
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        $this->pdo->exec("DROP TABLE IF EXISTS issue_templates");
        $this->pdo->exec("DROP TABLE IF EXISTS user_telegram_accounts");
        $this->pdo->exec("DROP TABLE IF EXISTS rate_limits");
        $this->pdo->exec("DROP TABLE IF EXISTS task_logs");
        $this->pdo->exec("DROP TABLE IF EXISTS tasks");
        $this->pdo->exec("DROP TABLE IF EXISTS projects");
        $this->pdo->exec("DROP TABLE IF EXISTS user_github_accounts");
        $this->pdo->exec("DROP TABLE IF EXISTS users");
        $this->pdo->exec("DROP TABLE IF EXISTS migrations");

        $schema = file_get_contents(__DIR__ . '/../../../src/sql/schema.sql');

        $queries = explode(';', $schema);
        foreach ($queries as $query) {
            $query = trim($query);
            if (empty($query)) continue;

            if ($driver === 'sqlite') {
                // Minimal transformation for SQLite compatibility in tests
                $query = str_ireplace('AUTO_INCREMENT', '', $query);
                $query = preg_replace('/ENGINE=InnoDB.*/i', '', $query);
                $query = str_ireplace('TIMESTAMP DEFAULT CURRENT_TIMESTAMP', 'DATETIME DEFAULT CURRENT_TIMESTAMP', $query);
                $query = preg_replace('/ENUM\([^)]+\)/i', 'TEXT', $query);
                $query = str_ireplace('ON UPDATE CURRENT_TIMESTAMP', '', $query);
                $query = preg_replace('/UNIQUE KEY \w+ \(/i', 'UNIQUE(', $query);
            }

            $this->pdo->exec($query);
        }
    }

    public function testUsersTableHasCorrectColumns()
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $stmt = $this->pdo->query("DESCRIBE users");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $columnNames = array_column($columns, 'Field');
        } else {
            $stmt = $this->pdo->query("PRAGMA table_info(users)");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $columnNames = array_column($columns, 'name');
        }
        $this->assertContains('user_id', $columnNames);
        $this->assertContains('google_id', $columnNames);
        $this->assertContains('name', $columnNames);
        $this->assertContains('email', $columnNames);
        $this->assertContains('avatar', $columnNames);
        $this->assertContains('created_at', $columnNames);
    }

    public function testProjectsTableHasCorrectColumns()
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $stmt = $this->pdo->query("DESCRIBE projects");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $columnNames = array_column($columns, 'Field');
        } else {
            $stmt = $this->pdo->query("PRAGMA table_info(projects)");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $columnNames = array_column($columns, 'name');
        }
        $this->assertContains('project_id', $columnNames);
        $this->assertContains('user_id', $columnNames);
        $this->assertContains('github_repo', $columnNames);
        $this->assertContains('webhook_secret', $columnNames);
        $this->assertContains('created_at', $columnNames);
    }

    public function testTasksTableHasCorrectColumns()
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $stmt = $this->pdo->query("DESCRIBE tasks");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $columnNames = array_column($columns, 'Field');
        } else {
            $stmt = $this->pdo->query("PRAGMA table_info(tasks)");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $columnNames = array_column($columns, 'name');
        }
        $this->assertContains('task_id', $columnNames);
        $this->assertContains('project_id', $columnNames);
        $this->assertContains('issue_number', $columnNames);
        $this->assertContains('title', $columnNames);
        $this->assertContains('body', $columnNames);
        $this->assertContains('status', $columnNames);
        $this->assertContains('github_data', $columnNames);
        $this->assertContains('created_at', $columnNames);
        $this->assertContains('updated_at', $columnNames);
    }
}
