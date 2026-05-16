<?php

require 'vendor/autoload.php';

use GuzzleHttp\Client;

$apiKey = getenv('GOOGLE_JULES_API_KEY');
if (!$apiKey) {
    echo "GOOGLE_JULES_API_KEY not set\n";
    exit(1);
}

$sessionId = getenv('JULES_SESSION_ID');
if (!$sessionId) {
    echo "JULES_SESSION_ID not set\n";
    exit(1);
}

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
