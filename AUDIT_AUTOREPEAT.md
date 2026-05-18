# Audit: Auto-Repeat Failure Analysis

This document describes the analysis of historical and recent failures in the "Auto-Repeat" automation mechanism.

## 1. Why PR #771 was not automatically merged (Historical)

*Note: This section is historical. The "Merge & Close" feature has since been implemented.*

Previously, the "Merge & Close" operation was documented only as a concept. It has now been implemented across:
- **Backend**: `App\GitHubService::mergePullRequest` and `App\GitHubService::closeIssue`.
- **Frontend**: `src/frontend/task.php` now includes a "Merge & Close" button that appears when PR conditions are met.

## 2. Why the automatic duplication of Issue #770 failed (Historical)

Issue #770 carried the "Auto-Repeat" label, but failed to duplicate upon closure.

### Root Cause: Case-Sensitive Label Check (Fixed)
The `WebhookHandler.php` previously used a case-sensitive check: `($label['name'] ?? '') === 'autorepeat'`.
The issue was labeled "Auto-Repeat", which failed the check.

**Resolution**: The code now uses `strtolower($label['name'])` and recognizes both `autorepeat` and `auto-repeat`.

## 3. Analysis of Issue #441 Autorepeat Failure

Issue #441 carried the "Auto-Repeat" label but failed to duplicate when closed via the "Merge & Close" button.

### Root Cause 1: Missing `state_reason` in API Call
The autorepeat logic in `App\WebhookHandler::handle` requires the issue to be closed with a `state_reason` of `completed`:

```php
if ($stateReason === 'completed' && $hasAutorepeat) { ... }
```

However, the "Merge & Close" implementation in `src/frontend/task.php` calls `App\GitHubService::closeIssue`, which performs a PATCH request to the GitHub API:

```php
public function closeIssue(string $repo, int $issueNumber): array
{
    // ...
    return $this->apiCall(
        'GitHub API',
        "PATCH issue $repo/issues/$issueNumber",
        fn() => $this->client->api('issue')->update($username, $repository, $issueNumber, ['state' => 'closed'])
    );
}
```

This call **omits** the `state_reason` parameter. When an issue is closed via the API without this parameter, GitHub does not necessarily mark it as "completed" in the webhook payload, causing the autorepeat condition to fail.

### Root Cause 2: Early Return for Pull Request Events
In `src/backend/WebhookHandler.php`, the `handle` method returns early if a `pull_request` event is detected:

```php
if ($pullRequest) {
    return $this->handlePullRequest($project, $event, $notificationService);
}
```

While GitHub typically sends a separate `issues` event when a PR "closes" an issue, any logic that depends on the PR merge itself (as specified in `CONCEPT_ONEVENT_ONSTATE.md`) is bypassed because the autorepeat logic is located further down in the `handle` method, after this early return.

## Recommendations
1. Update `App\GitHubService::closeIssue` to allow passing a `state_reason`, and ensure "Merge & Close" sets it to `completed`.
2. Consolidate the autorepeat logic into a dedicated method that can be called from both `issue` and `pull_request` event handlers.

## 4. Analysis of Failure for #446 and #451

Pull Request #446 and #451 also failed to trigger the auto-repeat mechanism.

### Failure 1: PR Events Bypass Autorepeat Logic
In `src/backend/WebhookHandler.php`, the auto-repeat check is only implemented within the handling of `issues` events.
- When a Pull Request is merged, GitHub sends a `pull_request` event with `action: closed` and `merged: true`.
- The current code routes this to `handlePullRequest`, which only sends a notification and returns `true`.
- It completely bypasses the label-checking and issue-duplication logic located further down in the main `handle` method.

### Failure 2: Missing `state_reason` in App-Initiated Closure
The system requires `state_reason === 'completed'` to trigger duplication.
- However, `App\GitHubService::closeIssue` (called by the "Merge & Close" UI button) only sends `['state' => 'closed']`.
- GitHub defaults the reason to `not_planned` or leaves it empty when closed via this API call without an explicit reason.
- This prevents auto-repeat for any task completed using the application's internal "Merge & Close" feature.

### Failure 3: Issue Deletion Exception
Issue #441 (the parent of PR #446) was deleted on GitHub.
- The `WebhookHandler` treats `action: deleted` as a cleanup task, removing the task from the local database and returning immediately.
- This is by design, but it means deleted issues can never trigger an auto-repeat duplication.
