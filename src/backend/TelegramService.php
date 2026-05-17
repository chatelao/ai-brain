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
        $this->botToken = $botToken ?? '';
    }

    public function withToken(string $botToken): self
    {
        $new = clone $this;
        $new->botToken = $botToken;
        return $new;
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    public function sendMessage(int $chatId, string $text, array $extraParams = []): bool
    {
        $params = array_merge([
            'chat_id' => $chatId,
            'text'    => $text,
            'parse_mode' => 'HTML'
        ], $extraParams);

        $this->apiRequest('sendMessage', $params);

        return true;
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    public function setWebhook(string $url, ?string $secretToken = null): bool
    {
        $params = ['url' => $url];
        if ($secretToken) {
            $params['secret_token'] = $secretToken;
        }

        $this->apiRequest('setWebhook', $params);

        return true;
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    public function deleteWebhook(): bool
    {
        $this->apiRequest('deleteWebhook');

        return true;
    }

    /**
     * @throws Exception
     */
    private function apiRequest(string $method, array $params = []): array
    {
        if (empty($this->botToken)) {
            throw new Exception("Telegram Bot Token not configured.");
        }

        $options = [];
        if (!empty($params)) {
            $options['json'] = $params;
        }

        try {
            $response = $this->client->post("/bot" . $this->botToken . "/" . $method, $options);
            $data = json_decode($response->getBody()->getContents(), true);

            if (!isset($data['ok']) || $data['ok'] !== true) {
                throw new Exception("Telegram API Error: " . ($data['description'] ?? 'Unknown error'));
            }

            return $data;
        } catch (GuzzleException $e) {
            $message = $e->getMessage();
            // Sanitize potential token leaks in Guzzle error messages (which often include the URL)
            $message = preg_replace('/bot\d+:[a-zA-Z0-9_-]+/', 'bot[REDACTED]', $message);
            throw new Exception("Telegram Connection Error: " . $message);
        }
    }
}
