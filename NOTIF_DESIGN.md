# Design: Notification System

## Overview
The notification system is designed as a modular service within the Agent Control PHP application. It handles the generation, storage, and multi-channel delivery of alerts based on system events.

## Architecture

### Centralized Notification Service
A `App\NotificationService` is the primary entry point for triggering notifications. It handles:
1.  Checking user and project-level notification preferences.
2.  Persisting notifications to the database for the In-App Inbox.
3.  Dispatching delivery to active channels (Browser, Telegram) via specific channel handlers.

### Component Diagram
```
[Event Source] -> [NotificationService]
                      |
                      +--> [Database (In-App Inbox)]
                      |
                      +--> [BrowserChannelHandler] -> [Web Notifications API]
                      |
                      +--> [TelegramChannelHandler] -> [Telegram Bot API]
```

## Notification Triggers & Emojis

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

## Database Schema

### `notifications`
Stores the history of notifications for each user.
- `notification_id` (INT, PK)
- `user_id` (INT, FK)
- `type` (VARCHAR)
- `title` (VARCHAR)
- `message` (TEXT)
- `data` (JSON): Context (e.g., `project_id`, `task_id`, `source_url`).
- `is_read` (BOOLEAN)
- `created_at` (TIMESTAMP)

### `user_notification_settings`
Global toggles for notification channels.
- `user_id` (INT, FK)
- `channel` (VARCHAR): `in_app`, `browser`, `telegram`.
- `is_enabled` (BOOLEAN)

### `user_event_notification_settings` (Patch 015)
Global toggles for event types.
- `user_id` (INT, FK)
- `event_type` (VARCHAR): `github_issue`, `github_pr`, `task_status`, `agent_event`.
- `is_enabled` (BOOLEAN)

### `project_notification_settings`
Granular control per project and event type.
- `project_id` (INT, FK)
- `notification_type` (VARCHAR)
- `is_enabled` (BOOLEAN)

### `task_notification_settings`
Mute specific tasks.
- `task_id` (INT, FK)
- `is_muted` (BOOLEAN)

## Delivery Mechanism

### 1. In-App Inbox
- Notifications are fetched from the `notifications` table.
- A notification bell in `navbar.php` displays the unread count.
- **Deep Linking**: Each notification in the inbox is a clickable link directing the user to the `source_url` provided in the notification data.

### 2. Active Browser Notifications
- Uses the `Web Notifications API`.
- Triggered when the dashboard is open.
- Permission requested on the "Settings" page.

### 3. Telegram
- Uses `user_telegram_accounts` and Telegram Bot API.
- Notifications sent to linked `telegram_chat_id`.
- Handled asynchronously via `fastcgi_finish_request()` to avoid blocking.

## Implementation Details

### Deep Linking
The `NotificationService` populates `source_url` in the `data` payload:
- **GitHub PR events**: URL of the Pull Request.
- **Jules Session events**: URL of the AI agent session.
- **Task/Issue events**: URL of the GitHub Issue.

### Dispatching Logic
```php
class NotificationService {
    public function notify(User $user, string $type, string $title, string $message, array $data = []) {
        // 1. Check user and project-level settings
        // 2. Persist to 'notifications' table
        // 3. For each enabled channel:
        //    - Dispatch to ChannelHandler
    }
}
```

### Frontend Integration
- **Settings Page (`src/frontend/settings.php`)**: Sections for channel toggles, global event type toggles, and browser notification permission.
- **Project Page (`src/frontend/project.php`)**: Tab to manage project-specific overrides.
- **Task List**: Option to "Mute" individual tasks.
