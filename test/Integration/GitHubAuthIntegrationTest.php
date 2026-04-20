<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\GitHubAuth;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use ReflectionClass;

class GitHubAuthIntegrationTest extends TestCase
{
    public function testAuthenticateSuccess()
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['access_token' => 'test-token'])),
            new Response(200, [], json_encode(['login' => 'test-user'])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $githubAuth = new GitHubAuth();

        // Inject mock client
        $reflection = new ReflectionClass($githubAuth);
        $property = $reflection->getProperty('httpClient');
        $property->setAccessible(true);
        $property->setValue($githubAuth, $client);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['github_oauth_state'] = 'test-state';

        $result = $githubAuth->authenticate('test-code', 'test-state');

        $this->assertEquals('test-token', $result['access_token']);
        $this->assertEquals('test-user', $result['github_username']);
    }

    public function testAuthenticateInvalidState()
    {
        $githubAuth = new GitHubAuth();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['github_oauth_state'] = 'correct-state';

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Invalid OAuth state");

        $githubAuth->authenticate('test-code', 'wrong-state');
    }
}
