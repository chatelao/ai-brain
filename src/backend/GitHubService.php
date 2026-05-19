<?php

namespace App;

use Github\Client;
use Github\AuthMethod;
use Github\ResultPager;
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
    public function getIssue(string $repo, int $issueNumber): array
    {
        $parts = explode('/', $repo);
        if (count($parts) !== 2) {
            throw new Exception("Invalid repository name: $repo");
        }

        [$username, $repository] = $parts;

        return $this->apiCall(
            'GitHub API',
            "GET issue $repo/issues/$issueNumber",
            fn() => $this->client->api('issue')->show($username, $repository, $issueNumber)
        );
    }

    /**
     * @throws Exception
     */
    public function getCheckSuites(string $repo, string $ref): array
    {
        $parts = explode('/', $repo);
        if (count($parts) !== 2) {
            throw new Exception("Invalid repository name: $repo");
        }

        [$username, $repository] = $parts;

        return $this->apiCall(
            'GitHub API',
            "GET check_suites $repo/commits/$ref",
            fn() => $this->client->api('repo')->checkSuites()->all($username, $repository, $ref)
        );
    }

    /**
     * @throws Exception
     */
    public function addLabel(string $repo, int $issueNumber, string $label): void
    {
        $parts = explode('/', $repo);
        if (count($parts) !== 2) {
            throw new Exception("Invalid repository name: $repo");
        }

        [$username, $repository] = $parts;

        $this->apiCall(
            'GitHub API',
            "POST label $repo/issues/$issueNumber",
            fn() => $this->client->api('issue')->labels()->add($username, $repository, $issueNumber, $label)
        );
    }

    /**
     * @throws Exception
     */
    public function mergePullRequest(string $repo, int $prNumber, string $message = '', string $mergeMethod = 'merge'): array
    {
        $parts = explode('/', $repo);
        if (count($parts) !== 2) {
            throw new Exception("Invalid repository name: $repo");
        }

        [$username, $repository] = $parts;

        $pr = $this->getPullRequest($repo, $prNumber);
        $sha = $pr['head']['sha'] ?? null;

        try {
            return $this->apiCall(
                'GitHub API',
                "PUT merge $repo/pull/$prNumber",
                fn() => $this->client->api('pull_request')->merge($username, $repository, $prNumber, $message, $sha, $mergeMethod)
            );
        } catch (Exception $e) {
            // Handle case where PR is already merged (405 or 422 depending on implementation/state)
            if ($e->getCode() === 405 || $e->getCode() === 422 || str_contains($e->getMessage(), 'Validation Failed') || str_contains($e->getMessage(), 'Method Not Allowed')) {
                $currentPr = $this->getPullRequest($repo, $prNumber);
                if ($currentPr['merged'] ?? false) {
                    return $currentPr;
                }
            }
            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    public function closeIssue(string $repo, int $issueNumber, ?string $stateReason = null): array
    {
        $parts = explode('/', $repo);
        if (count($parts) !== 2) {
            throw new Exception("Invalid repository name: $repo");
        }

        [$username, $repository] = $parts;

        $params = ['state' => 'closed'];
        if ($stateReason) {
            $params['state_reason'] = $stateReason;
        }

        try {
            return $this->apiCall(
                'GitHub API',
                "PATCH issue $repo/issues/$issueNumber",
                fn() => $this->client->api('issue')->update($username, $repository, $issueNumber, $params)
            );
        } catch (Exception $e) {
            // Handle case where issue is already closed
            if ($e->getCode() === 422 || str_contains($e->getMessage(), 'Validation Failed')) {
                $issue = $this->getIssue($repo, $issueNumber);
                if (($issue['state'] ?? '') === 'closed') {
                    return $issue;
                }
            }
            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    private function apiCall(string $type, string $target, callable $call, ?array $context = null): mixed
    {
        $start = microtime(true);
        try {
            $result = $call();
            $duration = microtime(true) - $start;
            if ($duration > 1.0) {
                Logger::getInstance()->logPerformance(null, 'GitHub API', $target, $duration, $context, 200);
            }
            return $result;
        } catch (Exception $e) {
            $duration = microtime(true) - $start;
            Logger::getInstance()->logPerformance(
                null,
                'GitHub API',
                $target,
                $duration,
                $context,
                $e->getCode() ?: 500,
                $e->getMessage()
            );
            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    public function getPullRequest(string $repo, int $prNumber): array
    {
        $parts = explode('/', $repo);
        if (count($parts) !== 2) {
            throw new Exception("Invalid repository name: $repo");
        }

        [$username, $repository] = $parts;

        return $this->apiCall(
            'GitHub API',
            "GET pull_request $repo/pull/$prNumber",
            fn() => $this->client->api('pull_request')->show($username, $repository, $prNumber)
        );
    }

    public function extractPrNumber(string $prUrl): ?int
    {
        if (preg_match('/\/pull\/(\d+)/', $prUrl, $matches)) {
            return (int)$matches[1];
        }
        return null;
    }

    /**
     * @throws Exception
     */
    public function listWebhooks(string $repo): array
    {
        $parts = explode('/', $repo);
        if (count($parts) !== 2) {
            throw new Exception("Invalid repository name: $repo");
        }

        [$username, $repository] = $parts;

        return $this->client->api('repo')->hooks()->all($username, $repository);
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

        return $this->apiCall(
            'GitHub API',
            "POST comment $repo/issues/$issueNumber",
            fn() => $this->client->api('issue')->comments()->create($username, $repository, $issueNumber, [
                'body' => $comment
            ])
        );
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

        return $this->apiCall(
            'GitHub API',
            "POST issue $repo",
            fn() => $this->client->api('issue')->create($username, $repository, [
                'title' => $title,
                'body' => $body,
                'labels' => $labels
            ]),
            ['title' => $title]
        );
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

        return $this->apiCall(
            'GitHub API',
            "GET issues $repo?state=$state",
            function () use ($username, $repository, $state) {
                $pager = new ResultPager($this->client);
                return $pager->fetchAll($this->client->api('issue'), 'all', [$username, $repository, [
                    'state' => $state
                ]]);
            }
        );
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
                            $fullPath = ($path !== '.' ? $path . '/' : '') . $file['name'];

                            $nextTask = null;
                            try {
                                $fileContentResponse = $this->client->api('repo')->contents()->show($username, $repository, $fullPath);
                                if (isset($fileContentResponse['content'])) {
                                    $decodedContent = base64_decode($fileContentResponse['content']);
                                    $nextTask = $this->extractNextTask($decodedContent);
                                }
                            } catch (Exception $e) {
                                // Ignore content fetching errors
                            }

                            $roadmaps[] = [
                                'name' => $fullPath,
                                'html_url' => $file['html_url'],
                                'next_task' => $nextTask
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

    /**
     * @throws Exception
     */
    public function createWebhook(string $repo, string $url, string $secret): array
    {
        $parts = explode('/', $repo);
        if (count($parts) !== 2) {
            throw new Exception("Invalid repository name: $repo");
        }

        [$username, $repository] = $parts;

        return $this->client->api('repo')->hooks()->create($username, $repository, [
            'name' => 'web',
            'config' => [
                'url' => $url,
                'content_type' => 'json',
                'secret' => $secret,
                'insecure_ssl' => '0'
            ],
            'events' => ['issues', 'check_suite'],
            'active' => true,
        ]);
    }

    private function extractNextTask(string $content): ?string
    {
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $line = trim($line);
            // Match - [ ], * [ ] or + [ ]
            if (preg_match('/^[-*+]\s*\[\s*\]\s*(.*)$/', $line, $matches)) {
                return trim($matches[1]);
            }
        }
        return null;
    }
}
