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
        return new self($this->client, $botToken);
    }

    /**
     * @throws Exception
     */
    public function answerCallbackQuery(string $callbackQueryId, array $extraParams = []): bool
    {
        $params = array_merge([
            'callback_query_id' => $callbackQueryId,
        ], $extraParams);

        $this->apiRequest('answerCallbackQuery', $params);

        return true;
    }

    /**
     * @throws Exception
     */
    public function editMessageText(int $chatId, int $messageId, string $text, array $extraParams = []): array
    {
        $params = array_merge([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML'
        ], $extraParams);

        return $this->apiRequest('editMessageText', $params);
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    public function sendMessage(int $chatId, string $text, array $extraParams = []): array
    {
        $params = array_merge([
            'chat_id' => $chatId,
            'text'    => $text,
            'parse_mode' => 'HTML'
        ], $extraParams);

        return $this->apiRequest('sendMessage', $params);
    }

    /**
     * @throws Exception
     */
    public function deleteMessage(int $chatId, int $messageId): bool
    {
        $params = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ];

        $this->apiRequest('deleteMessage', $params);

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

        $start = microtime(true);
        $target = "POST $method";
        try {
            $response = $this->client->post("/bot" . $this->botToken . "/" . $method, $options);
            $duration = microtime(true) - $start;
            $data = json_decode($response->getBody()->getContents(), true);

            if (!isset($data['ok']) || $data['ok'] !== true) {
                $errorMessage = $data['description'] ?? 'Unknown error';
                Logger::getInstance()->logPerformance(null, 'Telegram API', $target, $duration, null, 400, $errorMessage);
                throw new Exception("Telegram API Error: " . $errorMessage);
            }

            if ($duration > 1.0) {
                Logger::getInstance()->logPerformance(null, 'Telegram API', $target, $duration, null, 200);
            }

            return $data;
        } catch (GuzzleException $e) {
            $duration = microtime(true) - $start;
            $message = $e->getMessage();
            // Sanitize potential token leaks in Guzzle error messages (which often include the URL)
            $message = preg_replace('/bot\d+:[a-zA-Z0-9_-]+/', 'bot[REDACTED]', $message);

            $statusCode = 500;
            if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
            }

            Logger::getInstance()->logPerformance(null, 'Telegram API', $target, $duration, null, $statusCode, $message);
            throw new Exception("Telegram Connection Error: " . $message);
        }
    }
}
