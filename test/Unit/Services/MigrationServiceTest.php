<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Database;
use App\MigrationService;
use PDO;

class MigrationServiceTest extends TestCase
{
    private string $dbPath;
    private string $patchesDir;
    private Database $db;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/test_migration.sqlite';
        if (file_exists($this->dbPath)) {
            unlink($this->dbPath);
        }
        $this->db = new Database(null, $this->dbPath);

        $this->patchesDir = sys_get_temp_dir() . '/test_patches/';
        if (!is_dir($this->patchesDir)) {
            mkdir($this->patchesDir);
        }
        // Clean up any existing patches
        array_map('unlink', glob($this->patchesDir . "*.sql"));
    }

    protected function tearDown(): void
    {
        if (file_exists($this->dbPath)) {
            unlink($this->dbPath);
        }
        array_map('unlink', glob($this->patchesDir . "*.sql"));
        rmdir($this->patchesDir);
        Database::resetConnection();
    }

    public function testEnsureMigrationsTableExists(): void
    {
        $migrationService = new MigrationService($this->db, $this->patchesDir);
        $migrationService->migrate();

        $conn = $this->db->getConnection();
        $stmt = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='migrations'");
        $this->assertNotEmpty($stmt->fetch());
    }

    public function testApplyPatches(): void
    {
        file_put_contents($this->patchesDir . '001_test.sql', "CREATE TABLE test_table (id TEXT PRIMARY KEY);");
        file_put_contents($this->patchesDir . '002_test.sql', "INSERT INTO test_table (id) VALUES (1);");

        $migrationService = new MigrationService($this->db, $this->patchesDir);
        $logs = $migrationService->migrate();

        $this->assertContains("Applying patch: 001_test.sql", $logs);
        $this->assertContains("Applying patch: 002_test.sql", $logs);

        $conn = $this->db->getConnection();
        $stmt = $conn->query("SELECT * FROM test_table");
        $this->assertCount(1, $stmt->fetchAll());

        $stmt = $conn->query("SELECT patch_name FROM migrations");
        $applied = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('001_test.sql', $applied);
        $this->assertContains('002_test.sql', $applied);
    }

    public function testDoNotReapplyPatches(): void
    {
        file_put_contents($this->patchesDir . '001_test.sql', "CREATE TABLE test_table (id TEXT PRIMARY KEY);");

        $migrationService = new MigrationService($this->db, $this->patchesDir);
        $migrationService->migrate();

        $logs = $migrationService->migrate();
        $this->assertContains("Database is already up to date.", $logs);
        $this->assertNotContains("Applying patch: 001_test.sql", $logs);
    }
}
