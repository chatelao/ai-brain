<?php

namespace Tests\Unit\DB;

use PHPUnit\Framework\TestCase;
use PDO;

class SchemaTest extends TestCase
{
    private $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $schema = file_get_contents(__DIR__ . '/../../../src/sql/schema.sql');

        // SQLite doesn't support some MySQL syntax like AUTO_INCREMENT (use AUTOINCREMENT)
        // and ENGINE=InnoDB. We'll do some basic transformations for testing schema in SQLite
        // or just verify it works if we avoid MySQL specificities in the SQL if possible.
        // Actually, for a pure "Unit DB test" verifying the schema definition,
        // we should ideally test against MySQL if that's the target.
        // But since we only have SQLite, we'll verify it can at least be parsed or
        // we verify the intent of the schema.

        $queries = explode(';', $schema);
        foreach ($queries as $query) {
            $query = trim($query);
            if (empty($query)) continue;

            // Minimal transformation for SQLite compatibility in tests
            $query = str_ireplace('AUTO_INCREMENT', '', $query);
            $query = preg_replace('/ENGINE=InnoDB.*/i', '', $query);
            $query = str_ireplace('TIMESTAMP DEFAULT CURRENT_TIMESTAMP', 'DATETIME DEFAULT CURRENT_TIMESTAMP', $query);
            $query = preg_replace('/ENUM\([^)]+\)/i', 'TEXT', $query);
            $query = str_ireplace('ON UPDATE CURRENT_TIMESTAMP', '', $query);
            $query = preg_replace('/UNIQUE KEY \w+ \(/i', 'UNIQUE(', $query);

            $this->pdo->exec($query);
        }
    }

    public function testUsersTableHasCorrectColumns()
    {
        $stmt = $this->pdo->query("PRAGMA table_info(users)");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $columnNames = array_column($columns, 'name');
        $this->assertContains('user_id', $columnNames);
        $this->assertContains('google_id', $columnNames);
        $this->assertContains('name', $columnNames);
        $this->assertContains('email', $columnNames);
        $this->assertContains('avatar', $columnNames);
        $this->assertContains('created_at', $columnNames);
    }

    public function testProjectsTableHasCorrectColumns()
    {
        $stmt = $this->pdo->query("PRAGMA table_info(projects)");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $columnNames = array_column($columns, 'name');
        $this->assertContains('project_id', $columnNames);
        $this->assertContains('user_id', $columnNames);
        $this->assertContains('github_repo', $columnNames);
        $this->assertContains('webhook_secret', $columnNames);
        $this->assertContains('created_at', $columnNames);
    }

    public function testTasksTableHasCorrectColumns()
    {
        $stmt = $this->pdo->query("PRAGMA table_info(tasks)");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $columnNames = array_column($columns, 'name');
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
