<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Database;
use PDOException;

class DatabaseTest extends TestCase
{
    public function testDatabaseInitialization()
    {
        $db = new Database('localhost', 'test_db', 'user', 'pass');
        $this->assertInstanceOf(Database::class, $db);
    }

    public function testGetConnectionThrowsExceptionWhenNoDbName()
    {
        Database::resetConnection();
        $db = new Database('localhost', null, 'user', 'pass');
        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('Database name not configured.');
        $db->getConnection();
    }
}
