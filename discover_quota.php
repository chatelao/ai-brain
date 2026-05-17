<?php

require 'vendor/autoload.php';

use GuzzleHttp\Client;

if ($argc < 3) {
    echo "Usage: php discover_quota.php <API_KEY> <SESSION_ID>\n";
    exit(1);
}

$apiKey = $argv[1];
$sessionId = $argv[2];

$client = new Client();
$url = "https://jules.googleapis.com/v1alpha/sessions/{$sessionId}?key={$apiKey}";

echo "Fetching session status from: $url\n";

try {
    $response = $client->get($url, [
        'headers' => [
            'Accept' => 'application/json',
            'X-Goog-Api-Key' => $apiKey
        ]
    ]);

    echo "Status Code: " . $response->getStatusCode() . "\n";
    echo "Headers:\n";
    foreach ($response->getHeaders() as $name => $values) {
        echo "$name: " . implode(", ", $values) . "\n";
    }
    echo "\nBody:\n";
    echo $response->getBody()->getContents() . "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    if (method_exists($e, 'getResponse') && $e->getResponse()) {
        echo "Response Body: " . $e->getResponse()->getBody()->getContents() . "\n";
    }
}
