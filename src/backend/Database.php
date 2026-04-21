<?php

namespace App;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $pdo = null;

    public function __construct(
        private ?string $host = null,
        private ?string $db = null,
        private ?string $user = null,
        private ?string $pass = null,
        private ?string $charset = 'utf8mb4'
    ) {
        $this->host = $host ?? (getenv('DB_HOST') ?: 'localhost');
        $this->db   = $db   ?? (getenv('DB_NAME') ?: null);
        $this->user = $user ?? (getenv('DB_USER') ?: null);
        $this->pass = $pass ?? (getenv('DB_PASS') ?: null);
    }

    /**
     * For testing purposes to reset the static connection
     */
    public static function resetConnection(): void
    {
        self::$pdo = null;
    }

    public function getConnection(): PDO
    {
        if (self::$pdo === null) {
            if ($this->db === null || $this->db === '') {
                throw new PDOException("Database name not configured.");
            }

            if ($this->db === ':memory:' || str_ends_with($this->db, '.sqlite')) {
                $dsn = "sqlite:$this->db";
            } else {
                $dsn = "mysql:host=$this->host;dbname=$this->db;charset=$this->charset";
            }

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                self::$pdo = new PDO($dsn, $this->user, $this->pass, $options);
            } catch (PDOException $e) {
                throw new PDOException($e->getMessage(), (int)$e->getCode());
            }
        }

        return self::$pdo;
    }
}
