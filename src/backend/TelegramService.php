<?php

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Exception;

class TelegramService
{
    private Client $client;
    private string $botToken;

    public function __construct(?Client $client = null, ?string $botToken = null)
    {
        $this->client = $client ?? new Client([
            'base_uri' => 'https://api.telegram.org/',
            'timeout'  => 10.0,
        ]);
        $this->botToken = $botToken ?? (getenv('TELEGRAM_BOT_TOKEN') ?: '');
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    public function sendMessage(int $chatId, string $text, array $extraParams = []): bool
    {
        if (empty($this->botToken)) {
            throw new Exception("Telegram Bot Token not configured.");
        }

        $params = array_merge([
            'chat_id' => $chatId,
            'text'    => $text,
            'parse_mode' => 'HTML'
        ], $extraParams);

        $response = $this->client->post("bot" . $this->botToken . "/sendMessage", [
            'json' => $params
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        if (!isset($data['ok']) || $data['ok'] !== true) {
            throw new Exception("Telegram API Error: " . ($data['description'] ?? 'Unknown error'));
        }

        return true;
    }
}
