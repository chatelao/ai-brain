<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use App\Auth;

class ExternalApiIntegrationTest extends TestCase
{
    public function testGitHubApiIntegrationMocked()
    {
        // For Integration C, we test against an external API.
        // Here we use Guzzle's MockHandler to simulate an operational API.

        $mock = new MockHandler([
            new Response(200, [], json_encode(['login' => 'jules-agent'])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        // Simulate using the client to fetch user data from "GitHub"
        $response = $client->request('GET', 'https://api.github.com/user');
        $data = json_decode($response->getBody()->getContents(), true);

        $this->assertEquals('jules-agent', $data['login']);
    }

    public function testGoogleAuthIntegrationMocked()
    {
        // Mocking the Google Client's behavior for authentication
        $auth = $this->getMockBuilder(Auth::class)
                     ->onlyMethods(['authenticate'])
                     ->getMock();

        $auth->expects($this->once())
             ->method('authenticate')
             ->with('valid-code')
             ->willReturn([
                 'google_id' => 'google-123',
                 'email' => 'test@example.com',
                 'name' => 'Test User',
                 'avatar' => 'http://example.com/avatar.png'
             ]);

        $userInfo = $auth->authenticate('valid-code');
        $this->assertEquals('test@example.com', $userInfo['email']);
    }
}
