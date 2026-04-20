<?php

namespace App;

use GuzzleHttp\Client;
use Exception;

class GitHubAuth
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private Client $httpClient;

    public function __construct()
    {
        $this->clientId = getenv('GITHUB_CLIENT_ID') ?: '';
        $this->clientSecret = getenv('GITHUB_CLIENT_SECRET') ?: '';
        $this->redirectUri = getenv('GITHUB_REDIRECT_URI') ?: '';
        $this->httpClient = new Client();
    }

    public function getAuthUrl(): string
    {
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope' => 'repo,user',
            'state' => bin2hex(random_bytes(16))
        ];
        $_SESSION['github_oauth_state'] = $params['state'];

        return 'https://github.com/login/oauth/authorize?' . http_build_query($params);
    }

    public function authenticate(string $code, string $state): array
    {
        if (empty($state) || $state !== ($_SESSION['github_oauth_state'] ?? '')) {
            throw new Exception("Invalid OAuth state");
        }
        unset($_SESSION['github_oauth_state']);

        $response = $this->httpClient->post('https://github.com/login/oauth/access_token', [
            'form_params' => [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'code' => $code,
                'redirect_uri' => $this->redirectUri,
            ],
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        if (isset($data['error'])) {
            throw new Exception("GitHub Authentication failed: " . $data['error_description']);
        }

        $accessToken = $data['access_token'];

        // Fetch user info to get username
        $userResponse = $this->httpClient->get('https://api.github.com/user', [
            'headers' => [
                'Authorization' => 'token ' . $accessToken,
                'Accept' => 'application/json',
                'User-Agent' => 'Agent-Control-App'
            ],
        ]);

        $userData = json_decode($userResponse->getBody()->getContents(), true);

        return [
            'access_token' => $accessToken,
            'github_username' => $userData['login']
        ];
    }
}
