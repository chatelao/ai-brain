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

        $redirectUri = getenv('GITHUB_REDIRECT_URI') ?: '';
        if (isset($_SERVER['HTTP_HOST']) && !empty($redirectUri)) {
            $parsedUrl = parse_url($redirectUri);
            if (isset($parsedUrl['scheme']) && isset($parsedUrl['path'])) {
                $redirectUri = $parsedUrl['scheme'] . '://' . $_SERVER['HTTP_HOST'] . $parsedUrl['path'];
            }
        }
        $this->redirectUri = $redirectUri;

        $this->httpClient = new Client();
    }

    public function getAuthUrl(): string
    {
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope' => 'repo,user,admin:repo_hook',
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

        // Fetch email if not public
        if (empty($userData['email'])) {
            $emailsResponse = $this->httpClient->get('https://api.github.com/user/emails', [
                'headers' => [
                    'Authorization' => 'token ' . $accessToken,
                    'Accept' => 'application/json',
                    'User-Agent' => 'Agent-Control-App'
                ],
            ]);
            $emails = json_decode($emailsResponse->getBody()->getContents(), true);
            foreach ($emails as $emailData) {
                if ($emailData['primary'] && $emailData['verified']) {
                    $userData['email'] = $emailData['email'];
                    break;
                }
            }
            // If still no email, just take the first one
            if (empty($userData['email']) && !empty($emails)) {
                $userData['email'] = $emails[0]['email'];
            }
        }

        return [
            'access_token' => $accessToken,
            'github_username' => $userData['login'],
            'github_id' => (string)$userData['id'],
            'name' => $userData['name'] ?? $userData['login'],
            'email' => $userData['email'] ?? null,
            'avatar' => $userData['avatar_url'] ?? null
        ];
    }
}
