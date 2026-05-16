<?php

namespace App;

use PDO;
use Exception;

class MigrationService
{
    private string $patchesDir;

    public function __construct(private Database $db, ?string $patchesDir = null)
    {
        $this->patchesDir = $patchesDir ?? __DIR__ . '/../sql/patches/';
    }

    public function getMigrationStatus(): array
    {
        $connection = $this->db->getConnection();
        $this->ensureMigrationsTableExists($connection);

        $patches = glob($this->patchesDir . '*.sql');
        sort($patches);

        $stmt = $connection->query("SELECT patch_name FROM migrations");
        $appliedPatches = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $pending = [];
        $applied = [];

        foreach ($patches as $patchPath) {
            $patchName = basename($patchPath);
            if (in_array($patchName, $appliedPatches)) {
                $applied[] = $patchName;
            } else {
                $pending[] = $patchName;
            }
        }

        return [
            'applied' => $applied,
            'pending' => $pending
        ];
    }

    public function migrate(): array
    {
        $logs = [];
        $connection = $this->db->getConnection();

        // 1. Ensure migrations table exists
        $logs[] = "Checking for migrations table...";
        $this->ensureMigrationsTableExists($connection);

        // 2. Scan for patches
        $patches = glob($this->patchesDir . '*.sql');
        sort($patches);

        // 3. Get applied patches
        $stmt = $connection->query("SELECT patch_name FROM migrations");
        $appliedPatches = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // 4. Apply new patches
        foreach ($patches as $patchPath) {
            $patchName = basename($patchPath);
            if (in_array($patchName, $appliedPatches)) {
                continue;
            }

            $logs[] = "Applying patch: $patchName";
            try {
                $sql = file_get_contents($patchPath);
                if ($sql === false) {
                    throw new Exception("Could not read patch file: $patchName");
                }

                $connection->beginTransaction();

                // Execute the SQL. Some drivers might not support multi-statement execute
                // so we might need to split it if it becomes an issue.
                $connection->exec($sql);

                $stmt = $connection->prepare("INSERT INTO migrations (patch_name) VALUES (?)");
                $stmt->execute([$patchName]);

                $connection->commit();
                $logs[] = "Successfully applied patch: $patchName";
            } catch (Exception $e) {
                if ($connection->inTransaction()) {
                    $connection->rollBack();
                }
                $logs[] = "ERROR applying patch $patchName: " . $e->getMessage();
                return $logs; // Stop execution on error
            }
        }

        if (count($logs) === 1) {
            $logs[] = "Database is already up to date.";
        }

        return $logs;
    }

    private function ensureMigrationsTableExists(PDO $connection): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS migrations (
            migration_id INT AUTO_INCREMENT PRIMARY KEY,
            patch_name VARCHAR(255) UNIQUE NOT NULL,
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        // Check if we are using SQLite
        if ($connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS migrations (
                migration_id INTEGER PRIMARY KEY AUTOINCREMENT,
                patch_name TEXT UNIQUE NOT NULL,
                applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );";
        }

        $connection->exec($sql);
    }
}
