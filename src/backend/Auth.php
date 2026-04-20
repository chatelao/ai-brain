<?php

namespace App;

use Google\Client as GoogleClient;
use Google\Service\Oauth2 as GoogleServiceOauth2;
use Exception;

class Auth
{
    private GoogleClient $client;

    public function __construct()
    {
        $this->client = new GoogleClient();
        $this->client->setClientId(getenv('GOOGLE_CLIENT_ID'));
        $this->client->setClientSecret(getenv('GOOGLE_CLIENT_SECRET'));
        $this->client->setRedirectUri(getenv('GOOGLE_REDIRECT_URI'));
        $this->client->addScope("email");
        $this->client->addScope("profile");

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function getAuthUrl(): string
    {
        return $this->client->createAuthUrl();
    }

    public function authenticate(string $code): array
    {
        $token = $this->client->fetchAccessTokenWithAuthCode($code);
        if (isset($token['error'])) {
            throw new Exception("Authentication failed: " . $token['error']);
        }
        $this->client->setAccessToken($token);

        $oauth2 = new GoogleServiceOauth2($this->client);
        $userInfo = $oauth2->userinfo->get();

        return [
            'google_id' => $userInfo->id,
            'email'     => $userInfo->email,
            'name'      => $userInfo->name,
            'avatar'    => $userInfo->picture
        ];
    }

    public function login(array $user): void
    {
        $_SESSION['user_id'] = $user['id'];
    }

    public function logout(): void
    {
        unset($_SESSION['user_id']);
        session_destroy();
    }

    public function getUserId(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    public function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public function getCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public function validateCsrfToken(?string $token): bool
    {
        return !empty($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }
}
