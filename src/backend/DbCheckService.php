<?php

namespace App;

use PDO;
use Exception;

class DbCheckService
{
    private MigrationService $migrationService;

    public function __construct(private Database $db)
    {
        $this->migrationService = new MigrationService($db);
    }

    public function getMissingPatches(): array
    {
        $status = $this->migrationService->getMigrationStatus();
        return $status['pending'];
    }

    public function validateTables(): array
    {
        $expectedTables = [
            'users',
            'user_github_accounts',
            'projects',
            'tasks',
            'task_logs',
            'rate_limits',
            'user_telegram_accounts',
            'issue_templates',
            'notifications',
            'user_notification_settings',
            'project_notification_settings',
            'task_notification_settings',
            'performance_logs',
            'migrations',
            'webhook_logs',
            'project_status_notification_settings',
            'user_event_notification_settings'
        ];

        $results = [];
        $connection = $this->db->getConnection();

        foreach ($expectedTables as $table) {
            $status = [
                'table' => $table,
                'exists' => false,
                'rows' => 0,
                'error' => null
            ];

            try {
                $stmt = $connection->query("SELECT COUNT(*) FROM `$table`");
                $status['exists'] = true;
                $status['rows'] = (int)$stmt->fetchColumn();
            } catch (Exception $e) {
                $status['exists'] = false;
                $status['error'] = $e->getMessage();
            }

            $results[] = $status;
        }

        return $results;
    }

    public function validateBasicData(): array
    {
        $connection = $this->db->getConnection();
        $checks = [];

        // Check if any user exists
        try {
            $stmt = $connection->query("SELECT COUNT(*) FROM users");
            $userCount = (int)$stmt->fetchColumn();
            $checks[] = [
                'name' => 'At least one user exists',
                'status' => $userCount > 0 ? 'OK' : 'WARNING',
                'message' => "$userCount users found."
            ];
        } catch (Exception $e) {
            $checks[] = [
                'name' => 'At least one user exists',
                'status' => 'ERROR',
                'message' => $e->getMessage()
            ];
        }

        // Check for admin user
        try {
            $stmt = $connection->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
            $adminCount = (int)$stmt->fetchColumn();
            $checks[] = [
                'name' => 'Admin user configured',
                'status' => $adminCount > 0 ? 'OK' : 'WARNING',
                'message' => "$adminCount admins found. Ensure UPGRADE_ALLOWED_EMAIL is set and matches an admin user."
            ];
        } catch (Exception $e) {
            $checks[] = [
                'name' => 'Admin user configured',
                'status' => 'ERROR',
                'message' => $e->getMessage()
            ];
        }

        return $checks;
    }

    public function checkConnection(): array
    {
        try {
            $pdo = $this->db->getConnection();
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $serverVersion = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
            return [
                'status' => 'OK',
                'driver' => $driver,
                'version' => $serverVersion
            ];
        } catch (Exception $e) {
            return [
                'status' => 'ERROR',
                'message' => $e->getMessage()
            ];
        }
    }
}
