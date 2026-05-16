<?php

namespace Tests;

use PDO;
use App\Database;

trait TestDatabaseTrait
{
    protected function getTestPdo(): PDO
    {
        $host = getenv('TEST_DB_HOST');
        $dbName = getenv('TEST_DB_NAME');
        $user = getenv('TEST_DB_USER');
        $pass = getenv('TEST_DB_PASS');

        if ($host && $dbName) {
            $dsn = "mysql:host=$host;dbname=$dbName;charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            return $pdo;
        }

        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }

    protected function createTestDatabase(): Database
    {
        $host = getenv('TEST_DB_HOST');
        $dbName = getenv('TEST_DB_NAME');
        $user = getenv('TEST_DB_USER');
        $pass = getenv('TEST_DB_PASS');

        $db = new Database($host, $dbName, $user, $pass);
        Database::resetConnection();
        return $db;
    }
}
