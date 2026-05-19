<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\TelegramService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Exception;

class TelegramServiceTest extends TestCase
{
    public function testSendMessageSuccess()
    {
        $responseBody = ['ok' => true, 'result' => ['message_id' => 999]];
        $mock = new MockHandler([
            new Response(200, [], json_encode($responseBody)),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $service = new TelegramService($client, 'fake-token');
        $result = $service->sendMessage(123456, 'Hello World');

        $this->assertIsArray($result);
        $this->assertTrue($result['ok']);
        $this->assertEquals(999, $result['result']['message_id']);
    }

    public function testSendMessageApiError()
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['ok' => false, 'description' => 'Unauthorized'])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $service = new TelegramService($client, 'fake-token');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Telegram API Error: Unauthorized');

        $service->sendMessage(123456, 'Hello World');
    }

    public function testSendMessageMissingToken()
    {
        $service = new TelegramService(null, '');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Telegram Bot Token not configured.');

        $service->sendMessage(123456, 'Hello World');
    }

    public function testSendMessageWithExtraParams()
    {
        $mock = new MockHandler([
            function ($request) {
                $body = json_decode($request->getBody()->getContents(), true);
                if ($body['reply_markup']['force_reply'] === true) {
                    return new Response(200, [], json_encode(['ok' => true, 'result' => []]));
                }
                return new Response(200, [], json_encode(['ok' => false]));
            },
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $service = new TelegramService($client, 'fake-token');
        $result = $service->sendMessage(123456, 'Hello World', [
            'reply_markup' => ['force_reply' => true]
        ]);

        $this->assertIsArray($result);
        $this->assertTrue($result['ok']);
    }

    public function testEditMessageTextSuccess()
    {
        $responseBody = ['ok' => true, 'result' => ['message_id' => 123, 'text' => 'Updated']];
        $mock = new MockHandler([
            new Response(200, [], json_encode($responseBody)),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $service = new TelegramService($client, 'fake-token');
        $result = $service->editMessageText(123456, 123, 'Updated');

        $this->assertIsArray($result);
        $this->assertTrue($result['ok']);
        $this->assertEquals('Updated', $result['result']['text']);
    }

    public function testDeleteMessageSuccess()
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['ok' => true, 'result' => true])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $service = new TelegramService($client, 'fake-token');
        $result = $service->deleteMessage(123456, 123);

        $this->assertTrue($result);
    }

    public function testSetWebhookSuccess()
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['ok' => true])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $service = new TelegramService($client, 'fake-token');
        $result = $service->setWebhook('https://example.com/webhook', 'secret123');

        $this->assertTrue($result);
    }

    public function testDeleteWebhookSuccess()
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['ok' => true])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $service = new TelegramService($client, 'fake-token');
        $result = $service->deleteWebhook();

        $this->assertTrue($result);
    }
}
