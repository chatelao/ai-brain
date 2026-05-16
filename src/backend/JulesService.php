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
     */
    public function fetchSessionStatus(string $sessionId, ?string $apiKey = null): ?array
    {
        $key = $apiKey ?: $this->apiKey;
        if (empty($key)) {
            return null;
        }

        $headers = [
            'Accept' => 'application/json',
        ];

        // The endpoint is https://jules.googleapis.com/v1alpha/sessions/
        // We might need a separate client or use the absolute URL
        if (str_starts_with($key, 'AIza') || str_starts_with($key, 'AQ.')) {
            $headers['X-Goog-Api-Key'] = $key;
            $url = "https://jules.googleapis.com/v1alpha/sessions/{$sessionId}?key={$key}";
        } else {
            $headers['Authorization'] = "Bearer {$key}";
            $url = "https://jules.googleapis.com/v1alpha/sessions/{$sessionId}";
        }

        try {
            $response = $this->client->get($url, [
                'headers' => $headers
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['state'])) {
                return [
                    'status' => strtolower(str_replace(['STATE_', '_'], ['', '-'], $data['state'])),
                    'url' => $data['url'] ?? null,
                    'title' => $data['title'] ?? null,
                    'reason' => $data['failureReason'] ?? $data['failureMessage'] ?? $data['reason'] ?? $data['error']['message'] ?? $data['message'] ?? null
                ];
            }
        } catch (GuzzleException $e) {
            // Log or handle error
        }

        return null;
    }

    /**
     * @throws GuzzleException
     */
    public function fetchQuota(?string $apiKey = null): ?array
    {
        $key = $apiKey ?: $this->apiKey;
        if (empty($key)) {
            return null;
        }

        $headers = [
            'Accept' => 'application/json',
        ];

        if (str_starts_with($key, 'AIza') || str_starts_with($key, 'AQ.')) {
            $headers['X-Goog-Api-Key'] = $key;
            $url = "https://jules.googleapis.com/v1alpha/users/me?key={$key}";
        } else {
            $headers['Authorization'] = "Bearer {$key}";
            $url = "https://jules.googleapis.com/v1alpha/users/me";
        }

        try {
            $response = $this->client->get($url, [
                'headers' => $headers
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['sessionUsage'])) {
                return [
                    'usage' => $data['sessionUsage']['usage'] ?? 0,
                    'limit' => $data['sessionUsage']['limit'] ?? 0
                ];
            }
        } catch (GuzzleException $e) {
            // Log or handle error
        }

        return null;
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    public function triggerAgent(array $task): string
    {
        if (empty($this->apiKey)) {
            throw new Exception("API Key not configured.");
        }

        $prompt = "Task Title: " . $task['title'] . "\n" .
                  "Task Body: " . ($task['body'] ?? 'No description provided.') . "\n\n" .
                  "Please analyze this GitHub issue and suggest a plan of action.";

        $response = $this->client->post("v1beta/models/gemini-pro:generateContent?key=" . $this->apiKey, [
            'json' => [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ]
            ]
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
