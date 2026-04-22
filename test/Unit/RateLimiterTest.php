<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Database;
use App\RateLimiter;
use PDO;

class RateLimiterTest extends TestCase
{
    private Database $db;
    private RateLimiter $rateLimiter;

    protected function setUp(): void
    {
        $this->db = new Database(null, ':memory:');
        Database::resetConnection();
        $this->rateLimiter = new RateLimiter($this->db);

        $this->createSchema();
    }

    private function createSchema(): void
    {
        $conn = $this->db->getConnection();
        $conn->exec("
            CREATE TABLE rate_limits (
                rate_key VARCHAR(255) PRIMARY KEY,
                request_count INT DEFAULT 1,
                reset_at TIMESTAMP NOT NULL
            )
        ");
    }

    public function testCheckAllowsFirstRequest(): void
    {
        $this->assertTrue($this->rateLimiter->check('test_key', 2, 60));
    }

    public function testCheckLimitsRequests(): void
    {
        $this->assertTrue($this->rateLimiter->check('test_key', 2, 60));
        $this->assertTrue($this->rateLimiter->check('test_key', 2, 60));
        $this->assertFalse($this->rateLimiter->check('test_key', 2, 60));
    }

    public function testCheckResetsAfterWindow(): void
    {
        $this->assertTrue($this->rateLimiter->check('test_key', 1, 1));
        $this->assertFalse($this->rateLimiter->check('test_key', 1, 1));

        // Manually update the reset_at to the past
        $conn = $this->db->getConnection();
        $past = date('Y-m-d H:i:s', time() - 10);
        $stmt = $conn->prepare("UPDATE rate_limits SET reset_at = ? WHERE rate_key = 'test_key'");
        $stmt->execute([$past]);

        $this->assertTrue($this->rateLimiter->check('test_key', 1, 1));
    }

    public function testGetIpAddressReturnsDefault(): void
    {
        unset($_SERVER['REMOTE_ADDR']);
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        $this->assertEquals('127.0.0.1', $this->rateLimiter->getIpAddress());
    }

    public function testGetIpAddressFromRemoteAddr(): void
    {
        $_SERVER['REMOTE_ADDR'] = '1.2.3.4';
        $this->assertEquals('1.2.3.4', $this->rateLimiter->getIpAddress());
    }

    public function testGetIpAddressFromForwardedFor(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '5.6.7.8, 1.2.3.4';
        $this->assertEquals('5.6.7.8', $this->rateLimiter->getIpAddress());
    }
}
