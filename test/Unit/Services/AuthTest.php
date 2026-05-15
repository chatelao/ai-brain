<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Auth;
use Google\Client as GoogleClient;
use Google\Service\Oauth2 as GoogleServiceOauth2;
use Google\Service\Oauth2\Resource\Userinfo;
use Google\Service\Oauth2\Userinfo as UserinfoModel;

class AuthTest extends TestCase
{
    private $auth;

    protected function setUp(): void
    {
        // We need to be careful because Auth.php starts a session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        // Mock environment variables
        putenv('GOOGLE_CLIENT_ID=test_id');
        putenv('GOOGLE_CLIENT_SECRET=test_secret');
        putenv('GOOGLE_REDIRECT_URI=http://localhost/callback');

        $this->auth = new Auth();
    }

    public function testGetAuthUrl()
    {
        $url = $this->auth->getAuthUrl();
        $this->assertStringContainsString('test_id', $url);
        $this->assertStringContainsString(urlencode('http://localhost/callback'), $url);
    }

    public function testLoginAndLogout()
    {
        $user = ['user_id' => 123];
        $this->auth->login($user);

        $this->assertTrue($this->auth->isLoggedIn());
        $this->assertEquals(123, $this->auth->getUserId());

        $this->auth->logout();
        $this->assertFalse($this->auth->isLoggedIn());
        $this->assertNull($this->auth->getUserId());
    }
}
