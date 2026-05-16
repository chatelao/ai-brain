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
    public function getIssueComments(string $repo, int $issueNumber): array
    {
        $parts = explode('/', $repo);
        if (count($parts) !== 2) {
            throw new Exception("Invalid repository name: $repo");
        }

        [$username, $repository] = $parts;

        return $this->client->api('issue')->comments()->all($username, $repository, $issueNumber);
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

    /**
     * @throws Exception
     */
    public function createIssue(string $repo, string $title, ?string $body, array $labels): array
    {
        $parts = explode('/', $repo);
        if (count($parts) !== 2) {
            throw new Exception("Invalid repository name: $repo");
        }

        [$username, $repository] = $parts;

        return $this->client->api('issue')->create($username, $repository, [
            'title' => $title,
            'body' => $body,
            'labels' => $labels
        ]);
    }

    /**
     * @throws Exception
     */
    public function removeLabel(string $repo, int $issueNumber, string $label): void
    {
        $parts = explode('/', $repo);
        if (count($parts) !== 2) {
            throw new Exception("Invalid repository name: $repo");
        }

        [$username, $repository] = $parts;

        try {
            $this->client->api('issue')->labels()->remove($username, $repository, $issueNumber, $label);
        } catch (Exception $e) {
            // Ignore if label not found
            if ($e->getCode() !== 404) {
                throw $e;
            }
        }
    }

    /**
     * @throws Exception
     */
    public function listIssues(string $repo, string $state = 'open'): array
    {
        $parts = explode('/', $repo);
        if (count($parts) !== 2) {
            throw new Exception("Invalid repository name: $repo");
        }

        [$username, $repository] = $parts;

        return $this->client->api('issue')->all($username, $repository, [
            'state' => $state
        ]);
    }

    /**
     * @throws Exception
     */
    public function getRoadmapFiles(string $repo): array
    {
        $parts = explode('/', $repo);
        if (count($parts) !== 2) {
            throw new Exception("Invalid repository name: $repo");
        }

        [$username, $repository] = $parts;
        $roadmaps = [];

        $pathsToSearch = ['.', 'docs'];

        foreach ($pathsToSearch as $path) {
            try {
                $contents = $this->client->api('repo')->contents()->show($username, $repository, $path === '.' ? '' : $path);
                if (is_array($contents)) {
                    foreach ($contents as $file) {
                        if ($file['type'] === 'file' && stripos($file['name'], 'ROADMAP') !== false) {
                            $roadmaps[] = [
                                'name' => ($path !== '.' ? $path . '/' : '') . $file['name'],
                                'html_url' => $file['html_url']
                            ];
                        }
                    }
                }
            } catch (Exception $e) {
                // Ignore if path not found or other errors for specific paths
                if ($e->getCode() !== 404) {
                    // Log or handle other errors if necessary, but don't fail the whole request
                }
            }
        }

        return $roadmaps;
    }
}
