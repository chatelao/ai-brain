<?php

namespace App;

use PDO;

class Task
{
    public const STATUS_CREATED = 'created';
    public const STATUS_ANALYZING = 'analyzing';
    public const STATUS_PLANNING = 'planning';
    public const STATUS_EXECUTING = 'executing';
    public const STATUS_VERIFYING = 'verifying';
    public const STATUS_CHECKING = 'checking';
    public const STATUS_READY = 'ready';
    public const STATUS_FINISHED = 'finished';
    public const STATUS_IMPLEMENTED = 'implemented';
    public const STATUS_FAILED_JULES = 'failed_jules';
    public const STATUS_FAILED_PR = 'failed_pr';

    public const UNIFIED_CREATED = 'CREATED';
    public const UNIFIED_PROCESSING = 'PROCESSING';
    public const UNIFIED_READY = 'READY';
    public const UNIFIED_FINISHED = 'FINISHED';
    public const UNIFIED_FAILED = 'FAILED';

    public function __construct(private Database $db)
    {
    }

    public function resolveStatus(array $task, ?array $prData = null, ?array $checkSuitesData = null): string
    {
        $githubState = $task['github_state'] ?? 'open';
        if ($githubState === 'closed') {
            return self::STATUS_FINISHED;
        }

        if ($prData && ($prData['state'] ?? 'open') === 'closed') {
            return self::STATUS_FINISHED;
        }

        $julesStatus = $task['jules_status'] ?? '';
        if ($julesStatus === 'failed' || $julesStatus === 'error') {
            return self::STATUS_FAILED_JULES;
        }

        $prUrl = $task['pr_url'] ?? null;
        if ($prUrl) {
            // Check results from check suites
            // Handle both webhook (singular 'check_suite') and API (array 'check_suites')
            $suites = [];
            $suitesProvided = ($checkSuitesData !== null);

            if ($checkSuitesData) {
                if (isset($checkSuitesData['check_suites'])) {
                    $suites = $checkSuitesData['check_suites'];
                } elseif (isset($checkSuitesData['check_suite'])) {
                    $suites = [$checkSuitesData['check_suite']];
                } elseif (isset($checkSuitesData['conclusion']) || isset($checkSuitesData['status'])) {
                    $suites = [$checkSuitesData];
                }
            }

            if (!empty($suites)) {
                $failed = false;
                $running = false;
                $success = true;

                foreach ($suites as $suite) {
                    $status = $suite['status'] ?? '';
                    $conclusion = $suite['conclusion'] ?? '';

                    if ($status !== 'completed' && $status !== 'skipped') {
                        $running = true;
                        $success = false;
                    } elseif (in_array($conclusion, ['failure', 'timed_out', 'cancelled', 'action_required', 'startup_failure'])) {
                        $failed = true;
                        $success = false;
                    } elseif ($conclusion === 'success' || $conclusion === 'neutral' || $conclusion === 'skipped' || $conclusion === 'stale') {
                        // Keep success = true (or whatever it is)
                    } elseif (empty($conclusion)) {
                        // Completed but no conclusion yet? Treat as still running/checking to avoid false failure
                        $running = true;
                        $success = false;
                    } else {
                        // Any other completed conclusion (including unknown ones) is considered a failure for safety
                        $failed = true;
                        $success = false;
                    }
                }

                if ($failed) {
                    return self::STATUS_FAILED_PR;
                }
                if ($success) {
                    return self::STATUS_READY;
                }
                if ($running || $julesStatus === 'finished' || $julesStatus === 'completed') {
                    return self::STATUS_CHECKING;
                }
            } elseif ($julesStatus === 'finished' || $julesStatus === 'completed') {
                return $suitesProvided ? self::STATUS_READY : self::STATUS_CHECKING;
            }
        }

        // Jules Processing substates
        switch ($julesStatus) {
            case 'researching':
                return self::STATUS_ANALYZING;
            case 'planning':
            case 'awaiting_plan_approval':
                return self::STATUS_PLANNING;
            case 'in-progress':
            case 'coding':
                return self::STATUS_EXECUTING;
            case 'testing':
                return self::STATUS_VERIFYING;
            case 'finished':
            case 'completed':
                return self::STATUS_IMPLEMENTED;
        }

        return self::STATUS_CREATED;
    }

    public function findByProjectId(int $projectId, bool $showAll = true): array
    {
        $sql = "SELECT t1.* FROM tasks t1 WHERE t1.project_id = ?
                AND t1.issue_number > 0 AND t1.title != ''";
        $params = [$projectId];

        if (!$showAll) {
            $sql .= " AND (t1.github_state = 'open' OR (
                t1.github_state = 'closed' AND t1.status IN ('" . self::STATUS_FINISHED . "', 'completed')
                AND (
                    SELECT COUNT(*) FROM tasks t2
                    WHERE t2.project_id = t1.project_id
                    AND t2.github_state = 'closed'
                    AND t2.status IN ('" . self::STATUS_FINISHED . "', 'completed')
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
                t.github_state = 'closed' AND t.status IN ('" . self::STATUS_FINISHED . "', 'completed')
                AND (
                    SELECT COUNT(*) FROM tasks t3
                    WHERE t3.user_id = ?
                    AND t3.github_state = 'closed'
                    AND t3.status IN ('" . self::STATUS_FINISHED . "', 'completed')
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
                    SUM(CASE WHEN status = '" . self::STATUS_FINISHED . "' THEN 1 ELSE 0 END) as completed_tasks,
                    SUM(CASE WHEN github_state = 'open' AND status IN ('" . self::STATUS_ANALYZING . "', '" . self::STATUS_PLANNING . "') THEN 1 ELSE 0 END) as jules_analyzing,
                    SUM(CASE WHEN github_state = 'open' AND status IN ('" . self::STATUS_EXECUTING . "', '" . self::STATUS_VERIFYING . "', '" . self::STATUS_IMPLEMENTED . "') THEN 1 ELSE 0 END) as jules_executing,
                    SUM(CASE WHEN github_state = 'open' AND status = '" . self::STATUS_FAILED_JULES . "' THEN 1 ELSE 0 END) as jules_failed,
                    SUM(CASE WHEN github_state = 'open' AND status = '" . self::STATUS_CHECKING . "' THEN 1 ELSE 0 END) as github_running,
                    SUM(CASE WHEN github_state = 'open' AND status = '" . self::STATUS_READY . "' THEN 1 ELSE 0 END) as github_passed,
                    SUM(CASE WHEN github_state = 'open' AND status = '" . self::STATUS_FAILED_PR . "' THEN 1 ELSE 0 END) as github_failed
                FROM tasks
                WHERE user_id = ?";

        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute([$userId]);
        $counts = $stmt->fetch();

        return [
            'total' => (int)($counts['total'] ?? 0),
            'open_issues' => (int)($counts['open_issues'] ?? 0),
            'completed_tasks' => (int)($counts['completed_tasks'] ?? 0),
            'jules_analyzing' => (int)($counts['jules_analyzing'] ?? 0),
            'jules_executing' => (int)($counts['jules_executing'] ?? 0),
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
                $sql .= " AND t.github_state = 'open' AND t.status = '" . self::STATUS_CHECKING . "'";
                break;
            case 'github_passed':
                $sql .= " AND t.github_state = 'open' AND t.status = '" . self::STATUS_READY . "'";
                break;
            case 'github_failed':
                $sql .= " AND t.github_state = 'open' AND t.status = '" . self::STATUS_FAILED_PR . "'";
                break;
            case 'jules_analyzing':
                $sql .= " AND t.github_state = 'open' AND t.status IN ('" . self::STATUS_ANALYZING . "', '" . self::STATUS_PLANNING . "')";
                break;
            case 'jules_executing':
                $sql .= " AND t.github_state = 'open' AND t.status IN ('" . self::STATUS_EXECUTING . "', '" . self::STATUS_VERIFYING . "', '" . self::STATUS_IMPLEMENTED . "')";
                break;
            case 'jules_failed':
                $sql .= " AND t.github_state = 'open' AND t.status = '" . self::STATUS_FAILED_JULES . "'";
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
            return $isOpen && $this->hasAutorepeatLabel($task);
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

    public function markAsMerged(int $id): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "UPDATE tasks SET status = ?, github_state = ? WHERE task_id = ?"
        );
        return $stmt->execute([self::STATUS_FINISHED, 'closed', $id]);
    }

    public function updateAgentResponse(int $id, string $response, string $status = self::STATUS_FINISHED): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "UPDATE tasks SET agent_response = ?, status = ? WHERE task_id = ?"
        );
        return $stmt->execute([$response, $status, $id]);
    }

    public function updateGitHubCache(int $taskId, ?array $prData = null, ?array $commentsData = null): bool
    {
        $updates = [];
        $params = [];

        if ($prData !== null) {
            $updates[] = "github_pr_data = ?";
            $params[] = json_encode($prData);
        }

        if ($commentsData !== null) {
            $updates[] = "github_comments_data = ?";
            $params[] = json_encode($commentsData);
        }

        if (empty($updates)) {
            return true;
        }

        $updates[] = "github_data_updated_at = ?";
        $params[] = date('Y-m-d H:i:s');

        $params[] = $taskId;

        $sql = "UPDATE tasks SET " . implode(', ', $updates) . " WHERE task_id = ?";
        $stmt = $this->db->getConnection()->prepare($sql);
        return $stmt->execute($params);
    }

    public function create(array $data): bool
    {
        $status = $data['status'] ?? self::STATUS_CREATED;
        if (($data['github_state'] ?? '') === 'closed') {
            $status = self::STATUS_FINISHED;
        }

        $stmt = $this->db->getConnection()->prepare(
            "INSERT INTO tasks (user_id, project_id, issue_number, title, body, github_data, status, github_state, autorepeat_remaining) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        return $stmt->execute([
            $data['user_id'],
            $data['project_id'],
            $data['issue_number'],
            $data['title'],
            $data['body'] ?? '',
            $data['github_data'] ?? null,
            $status,
            $data['github_state'] ?? 'open',
            $data['autorepeat_remaining'] ?? 0
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

    public function upsert(int $userId, int $projectId, array $issue, int $autorepeatRemaining = 0): bool
    {
        $connection = $this->db->getConnection();
        $driver = $connection->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $sql = "INSERT INTO tasks (user_id, project_id, issue_number, title, body, github_data, status, github_state, autorepeat_remaining)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON CONFLICT(project_id, issue_number) DO UPDATE SET
                        title = excluded.title,
                        body = excluded.body,
                        github_data = excluded.github_data,
                        github_state = excluded.github_state,
                        autorepeat_remaining = excluded.autorepeat_remaining,
                        status = CASE
                            WHEN excluded.github_state = 'closed' THEN 'finished'
                            WHEN status = 'pending' THEN 'created'
                            ELSE status
                        END";
        } else {
            $sql = "INSERT INTO tasks (user_id, project_id, issue_number, title, body, github_data, status, github_state, autorepeat_remaining)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        title = VALUES(title),
                        body = VALUES(body),
                        github_data = VALUES(github_data),
                        github_state = VALUES(github_state),
                        autorepeat_remaining = VALUES(autorepeat_remaining),
                        status = CASE
                            WHEN VALUES(github_state) = 'closed' THEN 'finished'
                            WHEN status = 'pending' THEN 'created'
                            ELSE status
                        END";
        }

        $stmt = $connection->prepare($sql);

        $status = ($issue['state'] ?? 'open') === 'closed' ? self::STATUS_FINISHED : self::STATUS_CREATED;

        return $stmt->execute([
            $userId,
            $projectId,
            $issue['number'],
            $issue['title'],
            $issue['body'],
            json_encode($issue),
            $status,
            $issue['state'] ?? 'open',
            $autorepeatRemaining
        ]);
    }

    public function getLogs(int $taskId): array
    {
        $logger = new Logger($this->db);
        return $logger->getLogsByTaskId($taskId);
    }

    public function getStatusColor(array $task): string
    {
        $status = $task['status'] ?? self::STATUS_CREATED;

        if ($status === self::STATUS_FINISHED || $status === 'completed') {
            $githubState = $task['github_state'] ?? 'closed';
            return $githubState === 'closed' ? 'purple' : 'green';
        }

        if (str_starts_with($status, 'failed_') || $status === 'failed') {
            return 'red';
        }

        if (in_array($status, [self::STATUS_EXECUTING, self::STATUS_VERIFYING, self::STATUS_IMPLEMENTED])) {
            return 'yellow';
        }

        if (in_array($status, [self::STATUS_ANALYZING, self::STATUS_PLANNING])) {
            return 'blue';
        }

        if ($status === self::STATUS_CHECKING) {
            return 'orange';
        }

        if ($status === self::STATUS_READY) {
            return 'green';
        }

        if ($status === self::STATUS_CREATED) {
            return 'gray';
        }

        return 'gray';
    }

    public function getStatusEmoji(string $status): string
    {
        if ($status === self::STATUS_CREATED) {
            return '⏳';
        }

        if ($status === self::STATUS_FINISHED || $status === self::STATUS_READY || $status === 'completed') {
            return '✅';
        }

        if ($status === self::STATUS_CHECKING) {
            return '🔍';
        }

        if ($status === self::STATUS_FAILED_JULES || $status === self::STATUS_FAILED_PR || $status === 'failed') {
            return '❌';
        }

        if (in_array($status, [self::STATUS_ANALYZING, self::STATUS_PLANNING, self::STATUS_EXECUTING, self::STATUS_VERIFYING, self::STATUS_IMPLEMENTED])) {
            return '🚧';
        }

        return '⏳';
    }

    public function getStatusLabel(string $status): string
    {
        if ($status === self::STATUS_CREATED) {
            return 'Waiting for Agent';
        }

        return ucwords(str_replace('_', ' ', $status));
    }

    public static function getUnifiedState(string $status): string
    {
        switch ($status) {
            case self::STATUS_CREATED:
                return self::UNIFIED_CREATED;
            case self::STATUS_ANALYZING:
            case self::STATUS_PLANNING:
            case self::STATUS_EXECUTING:
            case self::STATUS_VERIFYING:
            case self::STATUS_IMPLEMENTED:
            case self::STATUS_CHECKING:
            case 'in_progress': // Legacy
                return self::UNIFIED_PROCESSING;
            case self::STATUS_READY:
                return self::UNIFIED_READY;
            case self::STATUS_FINISHED:
            case 'completed': // Legacy
                return self::UNIFIED_FINISHED;
            case self::STATUS_FAILED_JULES:
            case self::STATUS_FAILED_PR:
            case 'failed': // Legacy
                return self::UNIFIED_FAILED;
            default:
                return self::UNIFIED_CREATED;
        }
    }

    public static function getStatusGrouping(): array
    {
        return [
            self::UNIFIED_CREATED => [
                self::STATUS_CREATED => 'Waiting for Agent'
            ],
            self::UNIFIED_PROCESSING => [
                self::STATUS_ANALYZING => 'Analyzing',
                self::STATUS_PLANNING => 'Planning',
                self::STATUS_EXECUTING => 'Executing',
                self::STATUS_VERIFYING => 'Verifying',
                self::STATUS_IMPLEMENTED => 'Implemented',
                self::STATUS_CHECKING => 'Checking'
            ],
            self::UNIFIED_READY => [
                self::STATUS_READY => 'Ready'
            ],
            self::UNIFIED_FINISHED => [
                self::STATUS_FINISHED => 'Finished'
            ],
            self::UNIFIED_FAILED => [
                self::STATUS_FAILED_JULES => 'Jules Failed',
                self::STATUS_FAILED_PR => 'PR Failed'
            ]
        ];
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
        if (preg_match('/jules\.(?:google|googleapis)\.com\/(?:v1alpha\/)?(?:sessions|task)\/([a-zA-Z0-9_-]+)/i', $text, $matches)) {
            return $matches[1];
        }

        // 2. Explicit task_id or session_id labels
        if (preg_match('/(?:task_id|session_id|sessionId|taskId)\s*[:=]\s*([a-zA-Z0-9_-]+)/i', $text, $matches)) {
            return $matches[1];
        }

        // 3. Look for a long numeric ID that looks like a session ID
        if (preg_match('/\b(\d{15,30})\b/', $text, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function extractPrUrl(string $text, ?string $repo = null): ?string
    {
        // Full URL
        if (preg_match('/https?:\/\/github\.com\/([^\/]+\/[^\/]+)\/pull\/(\d+)/i', $text, $matches)) {
            return $matches[0];
        }

        // Relative reference like #123 or pull/#123
        if ($repo && preg_match('/(?:pull\/|#)(\d+)/i', $text, $matches)) {
            return "https://github.com/$repo/pull/{$matches[1]}";
        }

        return null;
    }

    public function getTargetUrl(array $task, ?string $repo = null): string
    {
        $issueUrl = "https://github.com/" . ($repo ?? $task['github_repo']) . "/issues/" . $task['issue_number'];
        $status = $task['status'] ?? self::STATUS_CREATED;

        if ($status === self::STATUS_FINISHED || $status === 'completed' || $status === 'failed_pr') {
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

    public function updateAutorepeatRemaining(int $id, int $count): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "UPDATE tasks SET autorepeat_remaining = ? WHERE task_id = ?"
        );
        return $stmt->execute([$count, $id]);
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
            $sql .= " AND (t.last_synced_at IS NULL OR t.last_synced_at < ? OR t.status = 'pending')
                      AND (t.status NOT IN ('" . self::STATUS_FINISHED . "', 'completed', 'failed', 'failed_jules', 'failed_pr') OR t.jules_status NOT IN ('completed', 'failed'))";
            $params[] = $fiveMinutesAgo;
        } else {
            $fiveMinutesAgo = date('Y-m-d H:i:s', strtotime('-5 minutes'));
            $sql .= " AND (t.last_synced_at IS NULL OR t.last_synced_at < ? OR t.status = 'pending')
                      AND (t.status NOT IN ('" . self::STATUS_FINISHED . "', 'completed', 'failed', 'failed_jules', 'failed_pr') OR t.jules_status NOT IN ('completed', 'failed'))";
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
                    // Fetch fresh issue to get latest body and possibly PR info
                    $issue = $githubService->getIssue($task['github_repo'], $task['issue_number']);

                    if (!$sessionId) {
                        $sessionId = $this->extractSessionId($issue['body'] ?? '');
                    }

                    if (!$prUrl) {
                        $prUrl = $this->extractPrUrl($issue['body'] ?? '', $task['github_repo']);
                    }

                    $comments = $githubService->getIssueComments($task['github_repo'], $task['issue_number']);

                    // Search for sessionId in comments if not found in body
                    if (!$sessionId) {
                        // Reverse to find the latest comment from Jules first
                        $julesComments = array_reverse(array_filter($comments, function ($c) {
                            $login = strtolower($c['user']['login'] ?? '');
                            return ($login === 'google-labs-jules[bot]' || $login === 'jules');
                        }));

                        foreach ($julesComments as $comment) {
                            $sessionId = $this->extractSessionId($comment['body'] ?? '');
                            if ($sessionId) break;
                        }
                    }

                    // Search for prUrl in comments if not found in body
                    if (!$prUrl) {
                        // Check if issue object already has a PR link (GitHub does this sometimes)
                        if (isset($issue['pull_request']['html_url'])) {
                            $prUrl = $issue['pull_request']['html_url'];
                        } else {
                            foreach (array_reverse($comments) as $comment) {
                                $prUrl = $this->extractPrUrl($comment['body'] ?? '', $task['github_repo']);
                                if ($prUrl) break;
                            }
                        }
                    }

                    // Update database if found
                    if ($sessionId !== $task['jules_session_id'] || $prUrl !== $task['pr_url']) {
                        $updateStmt = $this->db->getConnection()->prepare(
                            "UPDATE tasks SET jules_session_id = ?, pr_url = ? WHERE task_id = ?"
                        );
                        $updateStmt->execute([
                            $sessionId ?: $task['jules_session_id'],
                            $prUrl ?: $task['pr_url'],
                            $task['task_id']
                        ]);
                        $task['jules_session_id'] = $sessionId ?: $task['jules_session_id'];
                        $task['pr_url'] = $prUrl ?: $task['pr_url'];
                    }
                } catch (\Exception $e) {
                    // Log error if needed
                }
            }

            $julesUrl = $task['jules_url'];
            $julesStatus = $task['jules_status'];

            if ($sessionId && $apiKey) {
                $julesData = $julesService->fetchSessionStatus($sessionId, $apiKey);
                if ($julesData) {
                    $julesStatus = $julesData['status'];
                    $julesUrl = $julesData['url'] ?? $julesUrl;
                }
            }

            // Also check PR checks if we have a PR
            $checkSuites = null;
            if ($prUrl) {
                try {
                    $prNumber = $githubService->extractPrNumber($prUrl);
                    if ($prNumber) {
                        $pr = $githubService->getPullRequest($task['github_repo'], $prNumber);
                        $sha = $pr['head']['sha'] ?? null;
                        if ($sha) {
                            $checkSuites = $githubService->getCheckSuites($task['github_repo'], $sha);
                        } else {
                            Logger::getInstance($this->db)->log($userId, $task['task_id'], "Could not find head SHA for PR $prUrl", 'warning');
                        }
                    }
                } catch (\Exception $e) {
                    Logger::getInstance($this->db)->log($userId, $task['task_id'], "Error fetching PR checks: " . $e->getMessage(), 'error');
                }
            }

            $tempTask = $task;
            $tempTask['jules_status'] = $julesStatus;
            $mappedStatus = $this->resolveStatus($tempTask, $pr ?? null, $checkSuites);

            if ($mappedStatus !== $task['status'] || $julesStatus !== $task['jules_status']) {
                $updateStmt = $this->db->getConnection()->prepare(
                    "UPDATE tasks SET jules_status = ?, status = ?, jules_url = ?, last_synced_at = ? WHERE task_id = ?"
                );
                $updateStmt->execute([$julesStatus, $mappedStatus, $julesUrl, date('Y-m-d H:i:s'), $task['task_id']]);

                if ($notificationService && $mappedStatus !== $task['status']) {
                    $title = "Task Update: #" . $task['issue_number'];
                    $message = "Task \"" . $task['title'] . "\" status: " . $this->getStatusEmoji($task['status']) . " ➡️ " . $this->getStatusEmoji($mappedStatus) . " " . $this->getStatusLabel($mappedStatus);
                    if ($mappedStatus === self::STATUS_FINISHED) {
                        $title = "✅ Task Completed: #" . $task['issue_number'];
                    } elseif ($mappedStatus === self::STATUS_READY) {
                        $title = "🚀 Task Ready: #" . $task['issue_number'];
                        $message = "Task \"" . $task['title'] . "\" is ready to merge.";

                        // Automatic merge if autorepeat is active
                        if (($task['autorepeat_remaining'] ?? 0) > 0) {
                            $webhookHandler = new WebhookHandler($this->db);
                            $projectModel = new Project($this->db);
                            $project = $projectModel->findById($task['project_id']);
                            if ($project) {
                                $webhookHandler->autoMergeAndDuplicate($project, array_merge($task, ['status' => $mappedStatus]), $githubService, $notificationService);
                                $message .= " (Auto-merging...)";
                            }
                        }
                    } elseif ($mappedStatus === self::STATUS_FAILED_JULES) {
                        $title = "❌ Jules Failed: #" . $task['issue_number'];
                        $message = "Jules session for \"" . $task['title'] . "\" failed.";
                    } elseif ($mappedStatus === self::STATUS_FAILED_PR) {
                        $title = "❌ PR Failed: #" . $task['issue_number'];
                        $message = "PR checks for \"" . $task['title'] . "\" failed.";
                    }

                    $actions = [];
                    if ($mappedStatus === self::STATUS_READY) {
                        $actions = ['merge'];
                    } elseif ($mappedStatus === self::STATUS_FAILED_JULES || $mappedStatus === self::STATUS_FAILED_PR) {
                        $actions = ['retry', 'restart'];
                    } else {
                        $actions = ['acknowledge'];
                    }

                    $notificationService->notify($userId, 'task_status', $title, $message, [
                        'task_id' => $task['task_id'],
                        'project_id' => $task['project_id'],
                        'status' => $mappedStatus,
                        'source_url' => $this->getTargetUrl(array_merge($task, ['status' => $mappedStatus])),
                        'is_system' => true // Polling/Sync is system-driven
                    ], $actions);
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
