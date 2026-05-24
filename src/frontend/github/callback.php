<?php

require_once __DIR__ . '/../../../vendor/autoload.php';
use App\Auth;
use App\GitHubAuth;
use App\Database;
use App\User;
use App\RateLimiter;
use App\Project;
use App\Task;
use App\GitHubService;

$db = new Database();
$rateLimiter = new RateLimiter($db);
$ip = $rateLimiter->getIpAddress();

if (!$rateLimiter->check("github_callback_$ip", 20, 60)) {
    http_response_code(429);
    exit('Too many requests. Please try again later.');
}

$auth = new Auth();
$userModel = new User($db);
$githubAuth = new GitHubAuth();

if (isset($_GET['code']) && isset($_GET['state'])) {
    try {
        $githubData = $githubAuth->authenticate($_GET['code'], $_GET['state']);

        if ($auth->isLoggedIn()) {
            // Existing behavior: linking a GitHub account to the current user
            $userId = $auth->getUserId();
            $userModel->addGitHubAccount($userId, $githubData['access_token'], $githubData['github_username']);

            // Sync tasks for all user projects matching this GitHub account
            $projectModel = new Project($db);
            $taskModel = new Task($db);
            $projects = $projectModel->findByUserId($userId);

            foreach ($projects as $project) {
                if ($project['github_username'] === $githubData['github_username']) {
                    try {
                        $githubService = new GitHubService(null, $githubData['access_token']);
                        $taskModel->syncIssues($userId, $project['project_id'], $project['github_repo'], $githubService);
                    } catch (Exception $e) {
                        // Ignore sync errors for individual projects
                    }
                }
            }

            header('Location: ../settings.php?github=success');
            exit;
        } else {
            // New behavior: GitHub Login
            $user = $userModel->createOrUpdate([
                'github_id' => $githubData['github_id'],
                'name' => $githubData['name'],
                'email' => $githubData['email'],
                'avatar' => $githubData['avatar']
            ]);

            if ($user) {
                // Also update/add the GitHub account for token persistence
                $userModel->addGitHubAccount($user['user_id'], $githubData['access_token'], $githubData['github_username']);

                $auth->login($user);
                $redirectUrl = file_exists(__DIR__ . '/../web/index.html') ? '/web/' : '/index.php';
                header('Location: ' . $redirectUrl);
                exit;
            } else {
                throw new Exception("Failed to create or update user from GitHub data.");
            }
        }
    } catch (Exception $e) {
        if ($auth->isLoggedIn()) {
            header('Location: ../settings.php?github=error&message=' . urlencode($e->getMessage()));
        } else {
            echo "Error: " . htmlspecialchars($e->getMessage());
        }
        exit;
    }
} else {
    header('Location: ../index.php');
    exit;
}
