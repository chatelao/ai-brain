# Audit: Auto-Repeat Failure Analysis (Issue #770)

This document describes the analysis of why Pull Request [chatelao/alpheusafpparser#771](https://github.com/chatelao/alpheusafpparser/pull/771) was not automatically merged and why the automatic duplication of Issue #770 failed, requiring the manual creation of [chatelao/alpheusafpparser#772](https://github.com/chatelao/alpheusafpparser/issues/772).

## 1. Why PR #771 was not automatically merged

According to `AUTOMATION_CONCEPT.md`, the system should provide a "Merge & Close" operation if certain conditions are met. However, analysis of the current codebase reveals:

- **Missing Implementation**: The "Merge & Close" logic is documented as a concept but is **not implemented** in either the frontend (`src/frontend/project.php`, `src/frontend/task.php`) or the backend (`src/backend/WebhookHandler.php`).
- **No API Calls**: There are no calls to the GitHub Merge API (`/repos/{owner}/{repo}/pulls/{pull_number}/merge`) in the `GitHubService.php` or anywhere else in the application.
- **Manual Step Required**: Since the automation is not yet part of the codebase, all PR merges must currently be performed manually via the GitHub UI.

## 2. Why the automatic duplication of Issue #770 failed

Issue #770 was expected to be duplicated upon closing because it carried the "Auto-Repeat" label. The duplication failed due to a **label mismatch** in the `WebhookHandler.php`.

### Root Cause: Case-Sensitive Label Check
In `src/backend/WebhookHandler.php` (lines 89-106), the code handles the duplication logic when an issue is closed:

```php
if ($result && $action === 'closed' && $githubService) {
    $stateReason = $issue['state_reason'] ?? '';
    $labels = $issue['labels'] ?? [];
    $hasAutorepeat = false;
    foreach ($labels as $label) {
        if (($label['name'] ?? '') === 'autorepeat') {
            $hasAutorepeat = true;
            break;
        }
    }

    if ($stateReason === 'completed' && $hasAutorepeat) {
        // ... duplication logic ...
    }
}
```

- **Problem**: The check `($label['name'] ?? '') === 'autorepeat'` is **case-sensitive** and looks for the exact string `"autorepeat"`.
- **Observation**: Issue #770 was labeled with **"Auto-Repeat"**.
- **Result**: The condition `$hasAutorepeat` evaluated to `false`, the duplication logic was skipped, and no new issue was created automatically.

## 3. Analysis of Failure for #446 and #451

Pull Request #446 and #451 also failed to trigger the auto-repeat mechanism, but for different technical reasons.

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

### Inconsistencies in the Codebase
There is a lack of uniformity in how the auto-repeat label is handled across the application:
1. **`src/backend/WebhookHandler.php`**: Uses case-sensitive `autorepeat`.
2. **`src/backend/Task.php` (`hasAutorepeatLabel`)**: Uses case-insensitive check for `autorepeat` or `auto-repeat`.
3. **`src/backend/Task.php` (`getRunningAutorepeatTasks`)**: Uses case-sensitive `autorepeat`.
4. **`src/frontend/index.php`**: The "Running Autorepeat Tasks" section only recognizes the label `autorepeat`.

## Conclusion
The failure of the automatic merge was due to **missing feature implementation**. The failure of the issue duplication was due to a **strict, case-sensitive label check** in the webhook handler that did not match the "Auto-Repeat" label used on GitHub.
