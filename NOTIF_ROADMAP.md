# Roadmap: Notification System Implementation

## Phase 1: Core Infrastructure
- [x] Create database migration for `notifications` table.
- [x] Create database migration for `user_notification_settings` table.
- [x] Create database migration for `project_notification_settings` table.
- [x] Create database migration for `task_notification_settings` table.
- [x] Implement `App\NotificationService` core logic (persistence and dispatching).
- [x] Implement unit tests for `NotificationService`.

## Phase 2: In-App Inbox & Deep Linking
- [x] Implement In-App Inbox UI (notification bell in `navbar-icons.php`).
- [x] Add unread count indicator to the notification bell.
- [x] Create a dropdown/modal to view recent notifications.
- [x] Implement "Mark as Read" functionality via AJAX.
- [x] Implement Deep Linking (using `source_url` from notification data).
- [x] Implement a dedicated "All Notifications" page (`src/frontend/notifications.php`).

## Phase 3: Telegram Integration
- [x] Implement `TelegramChannelHandler`.
- [x] Integrate with existing `user_telegram_accounts` and Telegram bot configuration.
- [ ] Ensure asynchronous delivery (e.g., using `fastcgi_finish_request()` or a queue).
- [x] Test Telegram notification delivery.

## Phase 4: Browser Notifications
- [ ] Implement `BrowserChannelHandler` using Web Notifications API.
- [ ] Add "Request Browser Notification Permission" logic in settings.
- [ ] Implement polling or lightweight event mechanism for real-time alerts.
- [ ] Test browser notification delivery across different browsers.

## Phase 5: Settings & Customization
- [x] Update `src/frontend/settings.php` with global notification preferences.
- [ ] Implement channel toggles (In-App, Browser, Telegram) in settings.
    - [x] In-App Inbox
    - [x] Telegram
    - [ ] Browser
- [ ] Implement global event type toggles in settings.
- [ ] Add notification settings to the project page (`src/frontend/project.php`).
- [ ] Implement per-task "Mute" functionality.

## Phase 6: Event Integration
- [x] Integrate `NotificationService` with GitHub webhooks (e.g., issue opened/closed/reopened).
- [ ] Trigger notifications on PR creation/updates.
- [x] Trigger notifications on Jules session failures or completions.
- [x] Trigger notifications on task status changes.
- [ ] Final end-to-end testing of all notification flows.
