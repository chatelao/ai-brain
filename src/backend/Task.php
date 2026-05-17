<?php

namespace App;

use PDO;

class Task
{
    public function __construct(private Database $db)
    {
    }

    public function findByProjectId(int $projectId, bool $showAll = true): array
    {
        $sql = "SELECT t1.* FROM tasks t1 WHERE t1.project_id = ?
                AND t1.issue_number > 0 AND t1.title != ''";
        $params = [$projectId];

        if (!$showAll) {
            $sql .= " AND (t1.github_state = 'open' OR (
                t1.github_state = 'closed' AND t1.status = 'completed'
                AND (
                    SELECT COUNT(*) FROM tasks t2
                    WHERE t2.project_id = t1.project_id
                    AND t2.github_state = 'closed'
                    AND t2.status = 'completed'
                    AND (t2.created_at > t1.created_at OR (t2.created_at = t1.created_at AND t2.task_id > t1.task_id))
                ) < 3
            ))";
        }

        $sql .= " ORDER BY t1.created_at DESC";

        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findActiveByProjectId(int $projectId, bool $all = false): array
    {
        return $this->findByProjectId($projectId, $all);
    }

    public function findByUserProjects(int $userId, bool $showAll = true): array
    {
        $sql = "SELECT t.*, p.github_repo
             FROM tasks t
             JOIN projects p ON t.project_id = p.project_id
             WHERE p.user_id = ?
             AND t.issue_number > 0 AND t.title != ''";

        $params = [$userId];

        if (!$showAll) {
            $sql .= " AND (t.github_state = 'open' OR (
                t.github_state = 'closed' AND t.status = 'completed'
                AND (
                    SELECT COUNT(*) FROM tasks t3
                    WHERE t3.user_id = ?
                    AND t3.github_state = 'closed'
                    AND t3.status = 'completed'
                    AND (t3.created_at > t.created_at OR (t3.created_at = t.created_at AND t3.task_id > t.task_id))
                ) < 3
            ))";
            $params[] = $userId;
        }

        $sql .= " ORDER BY t.created_at DESC";

        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findActiveByUserProjects(int $userId): array
    {
        return $this->findByUserProjects($userId, false);
    }

    public function getTaskCounts(int $userId): array
    {
        $sql = "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN github_state = 'open' THEN 1 ELSE 0 END) as open_issues,
                    SUM(CASE WHEN github_state = 'closed' OR status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
                    SUM(CASE WHEN github_state = 'open' AND status IN ('researching', 'planning', 'in_progress', 'coding', 'testing') THEN 1 ELSE 0 END) as jules_running,
                    SUM(CASE WHEN github_state = 'open' AND status IN ('failed', 'failed_jules') THEN 1 ELSE 0 END) as jules_failed,
                    SUM(CASE WHEN github_state = 'open' AND status = 'implemented' THEN 1 ELSE 0 END) as github_running,
                    SUM(CASE WHEN github_state = 'open' AND status = 'completed' THEN 1 ELSE 0 END) as github_passed,
                    SUM(CASE WHEN github_state = 'open' AND status = 'failed_pr' THEN 1 ELSE 0 END) as github_failed
                FROM tasks
                WHERE user_id = ?";

        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute([$userId]);
        $counts = $stmt->fetch();

        return [
            'total' => (int)($counts['total'] ?? 0),
            'open_issues' => (int)($counts['open_issues'] ?? 0),
            'completed_tasks' => (int)($counts['completed_tasks'] ?? 0),
            'jules_running' => (int)($counts['jules_running'] ?? 0),
            'jules_failed' => (int)($counts['jules_failed'] ?? 0),
            'github_running' => (int)($counts['github_running'] ?? 0),
            'github_passed' => (int)($counts['github_passed'] ?? 0),
            'github_failed' => (int)($counts['github_failed'] ?? 0)
        ];
    }

    public function findByFilter(int $userId, string $filter): array
    {
        $sql = "SELECT t.*, p.github_repo
                FROM tasks t
                JOIN projects p ON t.project_id = p.project_id
                WHERE t.user_id = ?";
        $params = [$userId];

        switch ($filter) {
            case 'github_running':
                $sql .= " AND t.github_state = 'open' AND t.status = 'implemented'";
                break;
            case 'github_passed':
                $sql .= " AND t.github_state = 'open' AND t.status = 'completed'";
                break;
            case 'github_failed':
                $sql .= " AND t.github_state = 'open' AND t.status = 'failed_pr'";
                break;
            case 'jules_running':
                $sql .= " AND t.github_state = 'open' AND t.status IN ('researching', 'planning', 'in_progress', 'coding', 'testing')";
                break;
            case 'jules_failed':
                $sql .= " AND t.github_state = 'open' AND t.status IN ('failed', 'failed_jules')";
                break;
            case 'open_issues':
                $sql .= " AND t.github_state = 'open'";
                break;
        }

        $sql .= " ORDER BY t.created_at DESC";

        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getRunningAutorepeatTasks(int $userId): array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT t.*, p.github_repo
             FROM tasks t
             JOIN projects p ON t.project_id = p.project_id
             WHERE p.user_id = ?
             ORDER BY t.created_at DESC"
        );
        $stmt->execute([$userId]);
        $tasks = $stmt->fetchAll();

        return array_filter($tasks, function ($task) {
            $githubData = json_decode($task['github_data'], true);
            if (!$githubData) {
                return false;
            }

            $isOpen = ($githubData['state'] ?? '') === 'open';
            $hasAutorepeat = false;
            $labels = $githubData['labels'] ?? [];
            foreach ($labels as $label) {
                if (($label['name'] ?? '') === 'autorepeat') {
                    $hasAutorepeat = true;
                    break;
                }
            }

            return $isOpen && $hasAutorepeat;
        });
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT * FROM tasks WHERE task_id = ?"
        );
        $stmt->execute([$id]);
        $task = $stmt->fetch();
        return $task ?: null;
    }

    public function findByPrUrl(string $prUrl): ?array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT * FROM tasks WHERE pr_url = ?"
        );
        $stmt->execute([$prUrl]);
        $task = $stmt->fetch();
        return $task ?: null;
    }

    public function findByIssueNumber(int $projectId, int $issueNumber): ?array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT * FROM tasks WHERE project_id = ? AND issue_number = ?"
        );
        $stmt->execute([$projectId, $issueNumber]);
        $task = $stmt->fetch();
        return $task ?: null;
    }

    public function deleteByIssueNumber(int $projectId, int $issueNumber): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "DELETE FROM tasks WHERE project_id = ? AND issue_number = ?"
        );
        return $stmt->execute([$projectId, $issueNumber]);
    }

    public function deleteByIssueNumbersNotIn(int $projectId, array $issueNumbers): bool
    {
        if (empty($issueNumbers)) {
            $stmt = $this->db->getConnection()->prepare(
                "DELETE FROM tasks WHERE project_id = ?"
            );
            return $stmt->execute([$projectId]);
        }

        $placeholders = implode(',', array_fill(0, count($issueNumbers), '?'));
        $stmt = $this->db->getConnection()->prepare(
            "DELETE FROM tasks WHERE project_id = ? AND issue_number NOT IN ($placeholders)"
        );
        return $stmt->execute(array_merge([$projectId], $issueNumbers));
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "UPDATE tasks SET status = ? WHERE task_id = ?"
        );
        return $stmt->execute([$status, $id]);
    }

    public function updateAgentResponse(int $id, string $response, string $status = 'completed'): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "UPDATE tasks SET agent_response = ?, status = ? WHERE task_id = ?"
        );
        return $stmt->execute([$response, $status, $id]);
    }

    public function create(array $data): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "INSERT INTO tasks (user_id, project_id, issue_number, title, body, github_data, status, github_state) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        return $stmt->execute([
            $data['user_id'],
            $data['project_id'],
            $data['issue_number'],
            $data['title'],
            $data['body'] ?? '',
            $data['github_data'] ?? null,
            $data['status'] ?? 'pending',
            $data['github_state'] ?? 'open'
        ]);
    }

    public function syncIssues(int $userId, int $projectId, string $repo, GitHubService $githubService): void
    {
        $issues = $githubService->listIssues($repo, 'all');
        $issueNumbers = [];

        foreach ($issues as $issue) {
            // Check if it's really an issue (not a PR)
            if (isset($issue['pull_request'])) {
                continue;
            }
            $this->upsert($userId, $projectId, $issue);
            $issueNumbers[] = $issue['number'];
        }

        $this->deleteByIssueNumbersNotIn($projectId, $issueNumbers);
    }

    public function upsert(int $userId, int $projectId, array $issue): bool
    {
        $connection = $this->db->getConnection();
        $driver = $connection->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $sql = "INSERT INTO tasks (user_id, project_id, issue_number, title, body, github_data, status, github_state)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ON CONFLICT(project_id, issue_number) DO UPDATE SET
                        title = excluded.title,
                        body = excluded.body,
                        github_data = excluded.github_data,
                        github_state = excluded.github_state";
        } else {
            $sql = "INSERT INTO tasks (user_id, project_id, issue_number, title, body, github_data, status, github_state)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        title = VALUES(title),
                        body = VALUES(body),
                        github_data = VALUES(github_data),
                        github_state = VALUES(github_state)";
        }

        $stmt = $connection->prepare($sql);

        return $stmt->execute([
            $userId,
            $projectId,
            $issue['number'],
            $issue['title'],
            $issue['body'],
            json_encode($issue),
            'pending',
            $issue['state'] ?? 'open'
        ]);
    }

    public function getLogs(int $taskId): array
    {
        $logger = new Logger($this->db);
        return $logger->getLogsByTaskId($taskId);
    }

    public function getStatusColor(array $task): string
    {
        $state = $task['github_state'] ?? 'open';

        if ($state === 'closed') {
            return 'purple';
        }

        $status = $task['status'] ?? 'pending';

        if ($status === 'failed' || str_starts_with($status, 'failed_')) {
            return 'red';
        }

        if (in_array($status, ['in_progress', 'implemented', 'coding', 'testing'])) {
            return 'yellow';
        }

        if (in_array($status, ['analyzed', 'researching', 'planning', 'awaiting-plan-approval', 'awaiting-user-feedback'])) {
            return 'blue';
        }

        if ($status === 'completed') {
            return 'green';
        }

        return 'grey';
    }

    public function hasAutorepeatLabel(array $task): bool
    {
        $githubData = json_decode($task['github_data'] ?? '{}', true);
        $labels = $githubData['labels'] ?? [];
        foreach ($labels as $label) {
            $name = strtolower($label['name'] ?? '');
            if ($name === 'autorepeat' || $name === 'auto-repeat') {
                return true;
            }
        }
        return false;
    }

    /**
     * Pre-processes Markdown/HTML text to trust and render GitHub image links.
     * This converts <img> tags from trusted GitHub domains into Markdown image syntax
     * so they can be rendered by Parsedown even when safe mode is enabled.
     */
    public function processGitHubImages(string $text): string
    {
        $trustedDomains = [
            'https://github.com/user-attachments/assets/',
            'https://raw.githubusercontent.com/',
            'https://user-images.githubusercontent.com/',
            'https://github-production-user-asset-6210df.s3.amazonaws.com/'
        ];

        return preg_replace_callback('/<img\s+[^>]*src="([^"]+)"[^>]*>/i', function ($matches) use ($trustedDomains) {
            $src = $matches[1];
            $isTrusted = false;
            foreach ($trustedDomains as $domain) {
                if (strpos($src, $domain) === 0) {
                    $isTrusted = true;
                    break;
                }
            }

            if ($isTrusted) {
                // Extract alt text if present
                $alt = 'image';
                if (preg_match('/alt="([^"]+)"/i', $matches[0], $altMatches)) {
                    $alt = $altMatches[1];
                }
                return "![$alt]($src)";
            }

            return $matches[0];
        }, $text);
    }

    /**
     * Converts <img> tags from trusted GitHub domains into HTML links
     * suitable for Telegram notifications.
     * This method handles HTML escaping for Telegram.
     */
    public function convertImagesToLinks(string $text): string
    {
        $trustedDomains = [
            'https://github.com/user-attachments/assets/',
            'https://raw.githubusercontent.com/',
            'https://user-images.githubusercontent.com/',
            'https://github-production-user-asset-6210df.s3.amazonaws.com/'
        ];

        // First, find all matches to preserve them
        $placeholders = [];
        $text = preg_replace_callback('/<img\s+[^>]*src="([^"]+)"[^>]*>/i', function ($matches) use ($trustedDomains, &$placeholders) {
            $src = $matches[1];
            $isTrusted = false;
            foreach ($trustedDomains as $domain) {
                if (strpos($src, $domain) === 0) {
                    $isTrusted = true;
                    break;
                }
            }

            if ($isTrusted) {
                $alt = 'Image';
                if (preg_match('/alt="([^"]+)"/i', $matches[0], $altMatches)) {
                    $alt = $altMatches[1];
                }
                $placeholder = "____IMG_PLACEHOLDER_" . count($placeholders) . "____";
                $placeholders[$placeholder] = "<a href=\"" . htmlspecialchars($src) . "\">[" . htmlspecialchars($alt) . "]</a>";
                return $placeholder;
            }

            return $matches[0];
        }, $text);

        // Now escape the rest of the text for Telegram HTML
        $text = htmlspecialchars($text);

        // Put back the links (which are already safely prepared)
        foreach ($placeholders as $placeholder => $link) {
            $text = str_replace(htmlspecialchars($placeholder), $link, $text);
        }

        return $text;
    }

    public function extractSessionId(string $text): ?string
    {
        // 1. Markdown links like [Jules Task](.../sessions/ID) or .../task/ID
        if (preg_match('/jules\.google\.com\/(?:sessions|task)\/([a-zA-Z0-9_-]+)/', $text, $matches)) {
            return $matches[1];
        }

        // 2. Explicit task_id or session_id labels
        if (preg_match('/(?:task_id|session_id|sessionId|taskId)[:=]\s*([a-zA-Z0-9_-]+)/i', $text, $matches)) {
            return $matches[1];
        }

        // 3. Look for a long numeric ID that looks like a session ID
        if (preg_match('/\b(\d{15,25})\b/', $text, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function extractPrUrl(string $text): ?string
    {
        if (preg_match('/https:\/\/github\.com\/[^\/]+\/[^\/]+\/pull\/\d+/', $text, $matches)) {
            return $matches[0];
        }
        return null;
    }

    public function getTargetUrl(array $task, ?string $repo = null): string
    {
        $issueUrl = "https://github.com/" . ($repo ?? $task['github_repo']) . "/issues/" . $task['issue_number'];
        $status = $task['status'] ?? 'pending';

        if ($status === 'completed' || $status === 'failed_pr') {
            return $task['pr_url'] ?: $issueUrl;
        }

        if ($status === 'in_progress' || $status === 'failed_jules') {
            return $task['jules_url'] ?: $issueUrl;
        }

        if ($status === 'failed') {
            if (!empty($task['pr_url'])) {
                return $task['pr_url'];
            }
            return $task['jules_url'] ?: $issueUrl;
        }

        return $issueUrl;
    }

    public function refreshJulesStatus(int $userId, GitHubService $githubService, JulesService $julesService, ?NotificationService $notificationService = null, ?int $taskId = null, ?int $projectId = null): void
    {
        $sql = "SELECT t.*, p.github_repo
             FROM tasks t
             JOIN projects p ON t.project_id = p.project_id
             WHERE t.user_id = ?";

        $params = [$userId];

        if ($taskId) {
            $sql .= " AND t.task_id = ?";
            $params[] = $taskId;
        } elseif ($projectId) {
            $sql .= " AND t.project_id = ?";
            $params[] = $projectId;
            $fiveMinutesAgo = date('Y-m-d H:i:s', strtotime('-5 minutes'));
            $sql .= " AND (t.last_synced_at IS NULL OR t.last_synced_at < ?)
                      AND (t.status NOT IN ('completed', 'failed', 'failed_jules', 'failed_pr') OR t.jules_status NOT IN ('completed', 'failed'))";
            $params[] = $fiveMinutesAgo;
        } else {
            $fiveMinutesAgo = date('Y-m-d H:i:s', strtotime('-5 minutes'));
            $sql .= " AND (t.last_synced_at IS NULL OR t.last_synced_at < ?)
                      AND (t.status NOT IN ('completed', 'failed', 'failed_jules', 'failed_pr') OR t.jules_status NOT IN ('completed', 'failed'))";
            $params[] = $fiveMinutesAgo;
        }

        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute($params);
        $tasks = $stmt->fetchAll();

        $userStmt = $this->db->getConnection()->prepare("SELECT jules_api_key, jules_quota_updated_at FROM users WHERE user_id = ?");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();
        $apiKey = $user['jules_api_key'] ?? null;
        $quotaUpdatedAt = $user['jules_quota_updated_at'] ?? null;

        // Fetch Jules quota if more than 60 minutes have passed since last update
        $oneHourAgo = date('Y-m-d H:i:s', strtotime('-60 minutes'));
        if ($apiKey && (!$quotaUpdatedAt || $quotaUpdatedAt < $oneHourAgo)) {
            $quota = $julesService->fetchQuota($apiKey);
            if ($quota) {
                $userModel = new User($this->db);
                $userModel->updateJulesQuota($userId, $quota['usage'], $quota['limit']);
            }
        }

        foreach ($tasks as $task) {
            $githubData = json_decode($task['github_data'] ?? '{}', true);
            $assignee = $githubData['assignee']['login'] ?? '';
            $labels = $githubData['labels'] ?? [];
            $hasJulesLabel = false;
            foreach ($labels as $label) {
                if (strtolower($label['name'] ?? '') === 'jules') {
                    $hasJulesLabel = true;
                    break;
                }
            }

            $isJulesRelated = (
                strtolower($assignee) === 'jules' ||
                strtolower($assignee) === 'google-labs-jules[bot]' ||
                $hasJulesLabel
            );

            if (!$isJulesRelated) {
                continue;
            }

            $sessionId = $task['jules_session_id'];
            $prUrl = $task['pr_url'];

            if (!$sessionId || !$prUrl) {
                try {
                    $comments = $githubService->getIssueComments($task['github_repo'], $task['issue_number']);

                    if (!$sessionId) {
                        // Reverse to find the latest "on it" comment
                        $julesComments = array_reverse(array_filter($comments, function ($c) {
                            $login = strtolower($c['user']['login'] ?? '');
                            return ($login === 'google-labs-jules[bot]' || $login === 'jules') &&
                                stripos($c['body'] ?? '', 'on it') !== false;
                        }));

                        foreach ($julesComments as $comment) {
                            $sessionId = $this->extractSessionId($comment['body'] ?? '');
                            if ($sessionId) {
                                $updateStmt = $this->db->getConnection()->prepare(
                                    "UPDATE tasks SET jules_session_id = ? WHERE task_id = ?"
                                );
                                $updateStmt->execute([$sessionId, $task['task_id']]);
                                break;
                            }
                        }
                    }

                    if (!$prUrl) {
                        foreach ($comments as $comment) {
                            $prUrl = $this->extractPrUrl($comment['body'] ?? '');
                            if ($prUrl) {
                                $updateStmt = $this->db->getConnection()->prepare(
                                    "UPDATE tasks SET pr_url = ? WHERE task_id = ?"
                                );
                                $updateStmt->execute([$prUrl, $task['task_id']]);
                                break;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Log error if needed
                }
            }

            if ($sessionId && $apiKey) {
                $julesData = $julesService->fetchSessionStatus($sessionId, $apiKey);
                if ($julesData) {
                    $newStatus = $julesData['status'];
                    $mappedStatus = $task['status'];
                    $julesUrl = $julesData['url'] ?? null;

                    if (in_array($newStatus, ['coding', 'testing', 'researching', 'planning'])) {
                        $mappedStatus = str_replace('-', '_', $newStatus);
                    } elseif ($newStatus === 'in-progress') {
                        $mappedStatus = 'in_progress';
                    } elseif ($newStatus === 'completed' || $newStatus === 'finished') {
                        $mappedStatus = !empty($prUrl) ? 'completed' : 'implemented';
                    } elseif ($newStatus === 'failed' || $newStatus === 'error') {
                        $mappedStatus = 'failed_jules';
                    }

                    if ($mappedStatus !== $task['status'] || $newStatus !== $task['jules_status']) {
                        $updateStmt = $this->db->getConnection()->prepare(
                            "UPDATE tasks SET jules_status = ?, status = ?, jules_url = ?, last_synced_at = ? WHERE task_id = ?"
                        );
                        $updateStmt->execute([$newStatus, $mappedStatus, $julesUrl, date('Y-m-d H:i:s'), $task['task_id']]);

                        if ($notificationService && $mappedStatus !== $task['status']) {
                            $title = "Task Update: #" . $task['issue_number'];
                            $message = "Task \"" . $task['title'] . "\" status changed to " . $mappedStatus . ".";
                            if ($mappedStatus === 'completed' || $mappedStatus === 'implemented') {
                                $title = "✅ Task Completed: #" . $task['issue_number'];
                            } elseif ($mappedStatus === 'failed_jules') {
                                $title = "❌ Jules Failed: #" . $task['issue_number'];
                                $message = "Jules session for \"" . $task['title'] . "\" failed.";
                            } elseif ($mappedStatus === 'failed_pr') {
                                $title = "❌ PR Failed: #" . $task['issue_number'];
                                $message = "PR checks for \"" . $task['title'] . "\" failed.";
                            }

                            $notificationService->notify($userId, 'task_status', $title, $message, [
                                'task_id' => $task['task_id'],
                                'project_id' => $task['project_id'],
                                'status' => $mappedStatus,
                                'source_url' => $this->getTargetUrl($task)
                            ]);
                        }
                    }
                }
            } else {
                // Still update last_synced_at even if no sessionId or apiKey to avoid constant retries
                $updateStmt = $this->db->getConnection()->prepare(
                    "UPDATE tasks SET last_synced_at = ? WHERE task_id = ?"
                );
                $updateStmt->execute([date('Y-m-d H:i:s'), $task['task_id']]);
            }
        }
    }
}
