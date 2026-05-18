# Debugging Notifications

This document explains how notifications are triggered, how to debug their configuration, and how to verify they are working correctly.

## 1. Trigger Events

The system sends notifications for the following events:

### GitHub Webhooks (`src/backend/WebhookHandler.php`)
*   **Issues**:
    *   `opened`: 🆕 Issue Opened
    *   `closed`: 🔒 Issue Closed
    *   `reopened`: 🔓 Issue Reopened
    *   `deleted`: 🗑️ Issue Deleted
*   **Pull Requests**:
    *   `opened`: 🆕 PR Opened
    *   `closed`: 💜 PR Merged or ❌ PR Closed
    *   `reopened`: 🔓 PR Reopened
    *   `synchronize`: 🔄 PR Pushed
*   **Check Suites**:
    *   `completed`: Triggered when PR checks finish.
    *   Failing checks -> ❌ PR Failed
    *   Passing checks (if previously failed) -> ✅ PR Fixed

### Jules Status Sync (`src/backend/Task.php`)
*   **Status Changes**: Triggered when a Jules session status changes.
    *   States: `researching`, `planning`, `coding`, `testing`, `in_progress`.
    *   `implemented`: Jules finished but PR not yet detected.
    *   `completed`: ✅ Task Completed (PR detected).
    *   `failed_jules`: ❌ Jules Failed.

---

## 2. Configuration Hierarchy

If a notification is not sent, check the following hierarchy in the database or UI:

1.  **User Settings** (`user_notification_settings` table):
    *   Is the channel enabled? (`in_app`, `browser`, `telegram`).
    *   For Telegram, ensure `telegram_bot_token` is set in the `users` table and a record exists in `user_telegram_accounts` for the `telegram_chat_id`.
2.  **Project Settings** (`project_notification_settings` table):
    *   Is the notification type enabled for this project? (`github_issue`, `github_pr`, `task_status`, `agent_event`).
3.  **Status Settings** (`project_status_notification_settings` table):
    *   If it's a `task_status` notification, is this specific status enabled? (e.g., `coding`, `failed_jules`).
4.  **Task Settings** (`task_notification_settings` table):
    *   Is this specific task muted? (`is_muted = 1`).

---

## 3. Debugging Steps

### Test Broadcast
Use the **Test Broadcast** button in the "Notifications" tab of the **Settings** page.
*   This triggers `ajax-notifications.php?action=test_broadcast`.
*   It bypasses project/task filters and tests the connection to enabled channels.
*   Success is reported per channel in the response.

### Check Logs
*   **Webhook Logs**: Go to `/logs.php` (Admin only) or check the `webhook_logs` table to see if GitHub sent the event.
*   **Performance Logs**: Check the `performance_logs` table for API errors. Look for `target = 'sendMessage'` or `target = 'GitHub API'` with `status_code >= 400`.
*   **System Error Log**: Check the PHP error log for "Telegram Send Error" messages.

### Database Inspection
```sql
-- Check if notification was persisted
SELECT * FROM notifications ORDER BY created_at DESC LIMIT 5;

-- Check enabled channels for a user
SELECT * FROM user_notification_settings WHERE user_id = 1;

-- Check project notification types
SELECT * FROM project_notification_settings WHERE project_id = 1;
```

---

## 4. Running Tests

### Unit Tests
Verify the `NotificationService` logic:
```bash
vendor/bin/phpunit test/Unit/Services/NotificationServiceTest.php
```

### Integration Tests
Verify that webhooks and background sync correctly trigger notifications:
```bash
vendor/bin/phpunit test/Integration/NotificationTriggerTest.php
```

### Mocking for Manual Testing
To mock a Telegram message without a real bot, you can register a mock channel in your bootstrap or test setup:
```php
$mockChannel = $this->createMock(NotificationChannelInterface::class);
$notificationService->registerChannel('telegram', $mockChannel);
```
