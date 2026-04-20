<?php

namespace App;

use PDO;
use PDOException;

class Database
{
    private ?PDO $pdo = null;

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

    public function getConnection(): PDO
    {
        if ($this->pdo === null) {
            if (empty($this->db)) {
                throw new PDOException("Database name not configured.");
            }

            $dsn = "mysql:host=$this->host;dbname=$this->db;charset=$this->charset";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                $this->pdo = new PDO($dsn, $this->user, $this->pass, $options);
            } catch (PDOException $e) {
                throw new PDOException($e->getMessage(), (int)$e->getCode());
            }
        }

        return $this->pdo;
    }
}
