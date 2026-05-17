<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\GitHubService;
use App\JulesService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Exception;
use Github\Client as GithubClient;
use Github\HttpClient\Builder;

class ExternalApiFailureIntegrationTest extends TestCase
{
    public function testGitHubServiceHandlesApiError()
    {
        $mock = new MockHandler([
            new Response(500, [], 'Internal Server Error'),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $guzzleClient = new Client(['handler' => $handlerStack]);

        $builder = new Builder($guzzleClient);
        $githubClient = new GithubClient($builder);

        $service = new GitHubService($githubClient, 'fake-token');

        $this->expectException(Exception::class);
        $service->listIssues('owner/repo');
    }

    public function testJulesServiceHandlesApiError()
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['error' => ['message' => 'Quota exceeded']])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $service = new JulesService($client, 'fake-key');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Gemini API Error: Quota exceeded');

        $service->triggerAgent(['title' => 'Test', 'body' => 'Test body']);
    }

    public function testJulesServiceHandlesNetworkFailure()
    {
        $mock = new MockHandler([
            new RequestException('Error Communicating with Server', new Request('GET', 'test')),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $service = new JulesService($client, 'fake-key');

        // fetchQuota returns null on GuzzleException
        $result = $service->fetchQuota();
        $this->assertNull($result);
    }
}
