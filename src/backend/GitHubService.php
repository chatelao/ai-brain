<?php

namespace App;

use Github\Client as GitHubClient;
use GuzzleHttp\Client as GuzzleClient;
use Exception;

class GitHubService
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;

    public function __construct()
    {
        $this->clientId = getenv('GITHUB_CLIENT_ID') ?: '';
        $this->clientSecret = getenv('GITHUB_CLIENT_SECRET') ?: '';
        $this->redirectUri = getenv('GITHUB_REDIRECT_URI') ?: '';
    }

    public function getAuthUrl(string $state): string
    {
        return "https://github.com/login/oauth/authorize?" . http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope' => 'repo,user',
            'state' => $state,
        ]);
    }

    public function getAccessToken(string $code): string
    {
        $client = new GuzzleClient();
        $response = $client->post('https://github.com/login/oauth/access_token', [
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

        return $data['access_token'];
    }

    public function getAuthenticatedClient(string $token): GitHubClient
    {
        $client = new GitHubClient();
        $client->authenticate($token, null, GitHubClient::AUTH_ACCESS_TOKEN);
        return $client;
    }

    public function getAuthenticatedUser(string $token): array
    {
        $client = $this->getAuthenticatedClient($token);
        return $client->api('user')->show();
    }
}
