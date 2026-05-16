# Concept: Notifications System

## Overview
The notification system aims to keep users informed about critical events across their projects and tasks. It provides a multi-channel approach to ensure timely updates whether the user is actively using the dashboard or is on the move.

## Notification Channels
1.  **In-App Inbox**: A centralized list of notifications within the web application dashboard.
2.  **Active Browser Notifications**: Real-time push notifications or browser-level alerts when the dashboard is open.
3.  **Telegram**: Instant messages delivered via the linked Telegram bot for mobile and desktop connectivity.

## Key Notification Events
Users can toggle notifications for the following event types:
-   **Build Failed**: Alert when a CI/CD build or deployment fails.
-   **PR Available**: Notification when a new Pull Request is created or requires attention.
-   **Session Failed**: Error alerts when an AI agent (Jules) session fails or encounters an error.
-   **Task Completed**: Success notification when a task or issue is resolved.

## Configuration & Customization
To avoid notification fatigue, users can customize their preferences at multiple levels:

### Per-Project Settings
Enable or disable specific notification types for an entire repository.
-   Example: Only receive "Build Failed" alerts for `production-repo`, but all alerts for `dev-repo`.

### Per-Task/Issue Settings
Mute or prioritize notifications for specific tasks.
-   Example: Following a critical hotfix task with all notification channels active.

### Channel Toggles
Globally or per project, users can choose which channels are active (e.g., Browser only, Telegram only, or both).

## Technical Architecture (Proposed)

### 1. Database Schema
-   `notifications`: Stores the actual notification messages, status (read/unread), and recipient user.
-   `user_notification_settings`: Stores user-level channel preferences and global toggles.
-   `project_notification_settings`: Stores overrides for specific projects.

### 2. Delivery Mechanism
-   **Webhooks**: GitHub and CI/CD webhooks trigger the notification logic.
-   **Polling/SSE/WebSockets**: For real-time in-app updates and active browser notifications.
-   **Telegram Bot API**: Asynchronous delivery of messages to configured `telegram_chat_id`.

### 3. Frontend Integration
-   **Inbox UI**: A notification bell icon with a dropdown/slide-over showing recent events.
-   **Settings Page**: A dedicated section in "Account Settings" to manage all notification preferences.
