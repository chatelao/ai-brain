<?php

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Exception;

class JulesService
{
    private Client $client;
    private string $apiKey;

    public function __construct(?Client $client = null, ?string $apiKey = null)
    {
        $this->client = $client ?? new Client([
            'base_uri' => 'https://generativelanguage.googleapis.com/',
            'timeout'  => 30.0,
        ]);
        $this->apiKey = (!empty($apiKey)) ? $apiKey : (getenv('GOOGLE_JULES_API_KEY') ?: '');
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    public function triggerAgent(array $task, ?string $julesToken = null, ?string $webhookUrl = null): string
    {
        if (empty($this->apiKey)) {
            throw new Exception("API Key not configured.");
        }

        $prompt = "Task Title: " . $task['title'] . "\n" .
                  "Task Body: " . ($task['body'] ?? 'No description provided.') . "\n\n" .
                  "Please analyze this GitHub issue and suggest a plan of action.";

        $json = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ]
        ];

        if ($julesToken) {
            $json['jules_token'] = $julesToken;
        }
        if ($webhookUrl) {
            $json['webhook_url'] = $webhookUrl;
        }

        $response = $this->client->post("v1beta/models/gemini-pro:generateContent?key=" . $this->apiKey, [
            'json' => $json
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        if (isset($data['error'])) {
            throw new Exception("Gemini API Error: " . ($data['error']['message'] ?? 'Unknown error'));
        }

        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if ($text === null) {
            throw new Exception("No response from agent.");
        }

        return $text;
    }
}
