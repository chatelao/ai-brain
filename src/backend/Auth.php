<?php

namespace App;

use Google\Client as GoogleClient;
use Google\Service\Oauth2 as GoogleServiceOauth2;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class Auth
{
    private GoogleClient $client;
    private string $jwtSecret;
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? new Database();
        $this->client = new GoogleClient();
        $this->client->setClientId(getenv('GOOGLE_CLIENT_ID'));
        $this->client->setClientSecret(getenv('GOOGLE_CLIENT_SECRET'));

        $redirectUri = getenv('GOOGLE_REDIRECT_URI');
        if (isset($_SERVER['HTTP_HOST']) && !empty($redirectUri)) {
            $parsedUrl = parse_url($redirectUri);
            if (isset($parsedUrl['scheme']) && isset($parsedUrl['path'])) {
                $redirectUri = $parsedUrl['scheme'] . '://' . $_SERVER['HTTP_HOST'] . $parsedUrl['path'];
            }
        }
        $this->client->setRedirectUri($redirectUri);

        $this->client->addScope("email");
        $this->client->addScope("profile");

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->jwtSecret = getenv('JWT_SECRET') ?: 'default_secret_change_me_in_production';
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
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['user_role'] = $user['role'] ?? 'user';
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

    public function isAdmin(): bool
    {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
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

    public function generateToken(int $userId, int $expiry = 3600): string
    {
        $payload = [
            'iss' => 'agent-control',
            'sub' => $userId,
            'iat' => time(),
            'exp' => time() + $expiry
        ];

        return JWT::encode($payload, $this->jwtSecret, 'HS256');
    }

    public function validateToken(string $token): ?int
    {
        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            return (int)$decoded->sub;
        } catch (Exception $e) {
            error_log("JWT Validation failed: " . $e->getMessage());
            return null;
        }
    }

    public function generateRefreshToken(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + (60 * 60 * 24 * 30)); // 30 days

        $stmt = $this->db->getConnection()->prepare(
            "INSERT INTO user_refresh_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)"
        );
        $stmt->execute([$userId, $hash, $expiresAt]);

        return $token;
    }

    public function validateRefreshToken(string $token): ?int
    {
        $hash = hash('sha256', $token);
        $now = date('Y-m-d H:i:s');
        $stmt = $this->db->getConnection()->prepare(
            "SELECT user_id FROM user_refresh_tokens WHERE token_hash = ? AND expires_at > ?"
        );
        $stmt->execute([$hash, $now]);
        $row = $stmt->fetch();

        return $row ? (int)$row['user_id'] : null;
    }

    public function revokeRefreshToken(string $token): void
    {
        $hash = hash('sha256', $token);
        $stmt = $this->db->getConnection()->prepare(
            "DELETE FROM user_refresh_tokens WHERE token_hash = ?"
        );
        $stmt->execute([$hash]);
    }
}
