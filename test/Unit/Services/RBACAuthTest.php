<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Auth;

class RBACAuthTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_unset();
    }

    public function testIsAdminReturnsFalseWhenNotLoggedIn()
    {
        $auth = new Auth();
        $this->assertFalse($auth->isAdmin());
    }

    public function testIsAdminReturnsFalseForNormalUser()
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['user_role'] = 'user';
        $auth = new Auth();
        $this->assertFalse($auth->isAdmin());
    }

    public function testIsAdminReturnsTrueForAdminUser()
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['user_role'] = 'admin';
        $auth = new Auth();
        $this->assertTrue($auth->isAdmin());
    }

    public function testLoginSetsRoleInSession()
    {
        $auth = new Auth();
        $user = [
            'id' => 123,
            'role' => 'admin'
        ];
        $auth->login($user);
        $this->assertEquals('admin', $_SESSION['user_role']);
        $this->assertTrue($auth->isAdmin());
    }

    public function testLoginDefaultsToUserRole()
    {
        $auth = new Auth();
        $user = [
            'id' => 123
        ];
        $auth->login($user);
        $this->assertEquals('user', $_SESSION['user_role']);
        $this->assertFalse($auth->isAdmin());
    }
}
