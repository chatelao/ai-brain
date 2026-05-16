# Design: Notification System

## Overview
The notification system is designed as a modular service within the Agent Control PHP application. It handles the generation, storage, and multi-channel delivery of alerts based on system events.

## Architecture

### Centralized Notification Service
A `App\NotificationService` will be the primary entry point for triggering notifications. It will:
1.  Check user and project-level notification preferences.
2.  Persist notifications to the database for the In-App Inbox.
3.  Dispatch delivery to active channels (Browser, Telegram) via specific channel handlers.

### Component Diagram (Conceptual)
```
[Event Source] -> [NotificationService]
                      |
                      +--> [Database (In-App Inbox)]
                      |
                      +--> [BrowserChannelHandler] -> [Web Notifications API]
                      |
                      +--> [TelegramChannelHandler] -> [Telegram Bot API]
```

## Database Schema

### `notifications`
Stores the history of notifications for each user.
- `notification_id` (INT, PK)
- `user_id` (INT, FK)
- `type` (VARCHAR): e.g., `build_failed`, `pr_available`, `session_failed`, `task_completed`.
- `title` (VARCHAR)
- `message` (TEXT)
- `data` (JSON): Additional context (e.g., `project_id`, `task_id`, `url`).
- `is_read` (BOOLEAN): Default `false`.
- `created_at` (TIMESTAMP)

### `user_notification_settings`
Global toggles for notification channels.
- `user_id` (INT, FK)
- `channel` (VARCHAR): `in_app`, `browser`, `telegram`.
- `is_enabled` (BOOLEAN)
- *Primary Key: (user_id, channel)*

### `project_notification_settings`
Granular control per project and event type.
- `project_id` (INT, FK)
- `notification_type` (VARCHAR): `build_failed`, `pr_available`, etc.
- `is_enabled` (BOOLEAN)
- *Primary Key: (project_id, notification_type)*

### `task_notification_settings`
Mute or prioritize specific tasks.
- `task_id` (INT, FK)
- `is_muted` (BOOLEAN)
- *Primary Key: (task_id)*

## Delivery Channels

### 1. In-App Inbox
- Notifications are fetched from the `notifications` table.
- A notification bell in `navbar.php` displays the unread count.
- A dropdown or dedicated page allows users to view and mark notifications as read.

### 2. Active Browser Notifications
- Uses the `Web Notifications API`.
- When the dashboard is open, it either polls or uses a lightweight event mechanism to trigger desktop alerts.
- Permission is requested on the "Account Settings" page.

### 3. Telegram
- Uses the existing `user_telegram_accounts` and Telegram bot configuration.
- Notifications are sent as instant messages to the linked `telegram_chat_id`.
- Handled asynchronously to avoid blocking the main execution flow.

## Implementation Details

### Notification Service Dispatching
```php
class NotificationService {
    public function notify(User $user, string $type, string $title, string $message, array $data = []) {
        // 1. Check if project-level settings allow this type (if project_id is in $data)
        // 2. Persist to 'notifications' table
        // 3. For each enabled channel in 'user_notification_settings':
        //    - Dispatch to ChannelHandler
    }
}
```

### Event Integration
- **GitHub Webhooks**: `WebhookHandler` will trigger `NotificationService::notify` for relevant events (e.g., `check_run.completed` for build failures).
- **Jules API Responses**: Task status changes in `Task::refreshJulesStatus` or `github-callback.php` can trigger notifications.

## Frontend UI

### Settings Page (`src/frontend/settings.php`)
- Added sections for "Notification Preferences".
- Channel toggles (In-App, Browser, Telegram).
- Global event type toggles.
- "Request Browser Notification Permission" button.

### Project Page (`src/frontend/project.php`)
- A "Notifications" tab or modal to manage project-specific overrides.
- Individual tasks in the task list can be "Muted" via a context menu or toggle.
