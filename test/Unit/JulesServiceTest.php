<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\JulesService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

class JulesServiceTest extends TestCase
{
    public function testTriggerAgent()
    {
        $mockResponse = [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => 'Suggested plan: Fix the bug.']
                        ]
                    ]
                ]
            ]
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode($mockResponse)),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $service = new JulesService($client, 'fake-api-key');

        $task = [
            'title' => 'Fix bug',
            'body' => 'There is a bug in login.'
        ];

        $response = $service->triggerAgent($task);
        $this->assertEquals('Suggested plan: Fix the bug.', $response);
    }

    public function testTriggerAgentMissingApiKey()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('API Key not configured.');

        $service = new JulesService(null, '');
        $task = ['title' => 'Test'];
        $service->triggerAgent($task);
    }
}
