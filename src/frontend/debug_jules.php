<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;
use App\Auth;
use App\User;
use App\Project;
use App\Task;
use App\JulesService;
use App\GitHubService;

$auth = new Auth();
$db = new Database();
$userModel = new User($db);

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = $userModel->findById($auth->getUserId());
$apiKey = $user['jules_api_key'] ?? null;

if (!$apiKey) {
    die("Jules API Key not configured.");
}

$sessionId = $_GET['session_id'] ?? null;
if (!$sessionId) {
    die("Session ID required.");
}

$client = new \GuzzleHttp\Client();

if (str_starts_with($apiKey, 'AIza') || str_starts_with($apiKey, 'AQ.')) {
    $headers = ['X-Goog-Api-Key' => $apiKey];
    $url = "https://jules.googleapis.com/v1alpha/sessions/{$sessionId}?key={$apiKey}";
} else {
    $headers = ['Authorization' => "Bearer {$apiKey}"];
    $url = "https://jules.googleapis.com/v1alpha/sessions/{$sessionId}";
}

try {
    $response = $client->get($url, [
        'headers' => array_merge($headers, ['Accept' => 'application/json'])
    ]);

    header('Content-Type: application/json');
    echo json_encode([
        'status' => $response->getStatusCode(),
        'headers' => $response->getHeaders(),
        'body' => json_decode($response->getBody()->getContents(), true)
    ], JSON_PRETTY_PRINT);

} catch (\Exception $e) {
    header('Content-Type: text/plain');
    echo "Error: " . $e->getMessage() . "\n";
    if (method_exists($e, 'getResponse') && $e->getResponse()) {
        echo "Response Body: " . $e->getResponse()->getBody()->getContents() . "\n";
    }
}
