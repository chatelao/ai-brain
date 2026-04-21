<?php

namespace App;

use Github\Client;
use Github\AuthMethod;
use Exception;

class GitHubService
{
    private Client $client;

    public function __construct(?Client $client = null, ?string $token = null)
    {
        $this->client = $client ?? new Client();
        if ($token) {
            $this->client->authenticate($token, null, AuthMethod::ACCESS_TOKEN);
        }
    }

    /**
     * @throws Exception
     */
    public function postComment(string $repo, int $issueNumber, string $comment): array
    {
        $parts = explode('/', $repo);
        if (count($parts) !== 2) {
            throw new Exception("Invalid repository name: $repo");
        }

        [$username, $repository] = $parts;

        return $this->client->api('issue')->comments()->create($username, $repository, $issueNumber, [
            'body' => $comment
        ]);
    }
}
