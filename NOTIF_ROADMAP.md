# Roadmap: Notification System Implementation

## Phase 1: Core Infrastructure
- [x] Create database migration for `notifications` table.
- [x] Create database migration for `user_notification_settings` table.
- [x] Create database migration for `project_notification_settings` table.
- [x] Create database migration for `task_notification_settings` table.
- [x] Implement `App\NotificationService` core logic (persistence and dispatching).
- [x] Implement unit tests for `NotificationService`.

## Phase 2: In-App Inbox & Deep Linking
- [ ] Implement In-App Inbox UI (notification bell in `navbar.php`).
- [ ] Add unread count indicator to the notification bell.
- [ ] Create a dropdown/modal to view recent notifications.
- [ ] Implement "Mark as Read" functionality.
- [ ] Implement Deep Linking (using `source_url` from notification data).
- [ ] Implement a dedicated "All Notifications" page.

## Phase 3: Telegram Integration
- [ ] Implement `TelegramChannelHandler`.
- [ ] Integrate with existing `user_telegram_accounts` and Telegram bot configuration.
- [ ] Ensure asynchronous delivery to avoid blocking.
- [ ] Test Telegram notification delivery.

## Phase 4: Browser Notifications
- [ ] Implement `BrowserChannelHandler` using Web Notifications API.
- [ ] Add "Request Browser Notification Permission" logic in settings.
- [ ] Implement polling or lightweight event mechanism for real-time alerts.
- [ ] Test browser notification delivery across different browsers.

## Phase 5: Settings & Customization
- [ ] Update `src/frontend/settings.php` with global notification preferences.
- [ ] Implement channel toggles (In-App, Browser, Telegram) in settings.
- [ ] Implement global event type toggles in settings.
- [ ] Add notification settings to the project page (`src/frontend/project.php`).
- [ ] Implement per-task "Mute" functionality.

## Phase 6: Event Integration
- [ ] Integrate `NotificationService` with GitHub webhooks (e.g., `check_run.completed` for build failures).
- [ ] Trigger notifications on PR creation/updates.
- [ ] Trigger notifications on Jules session failures or completions.
- [ ] Trigger notifications on task status changes.
- [ ] Final end-to-end testing of all notification flows.
