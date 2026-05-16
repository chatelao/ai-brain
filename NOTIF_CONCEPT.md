# Concept: Notifications System

## Overview
The notification system aims to keep users informed about critical events across their projects and tasks. It provides a multi-channel approach to ensure timely updates whether the user is actively using the dashboard or is on the move.

## Business Cases
-   **Reduced Cycle Times**: Real-time alerts for build failures and PR availability minimize "wait time" in the development lifecycle.
-   **Improved Operational Awareness**: Provides stakeholders with immediate visibility into the progress and health of automated AI tasks without manual monitoring.

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

## Use Cases
-   **Jump to Source (Deep Linking)**: A user clicks on a "PR Available" notification and is immediately taken to the corresponding GitHub Pull Request page.
-   **Immediate Build Failure Response**: A developer receives a "Build Failed" notification on Telegram and can quickly initiate a fix, even when away from their primary workstation.
-   **Streamlined PR Review Workflow**: Reviewers are notified as soon as a new PR is ready for feedback, accelerating the path to merging.
-   **Passive Progress Monitoring**: A developer receives a "Task Completed" notification when Jules finishes a long-running task, allowing them to switch contexts at the optimal time.
-   **Noise Management for Experimental Projects**: A user mutes all notifications for a specific experimental repository to maintain focus on stable production repositories.
-   **Unified Task Oversight**: A user scans their In-App Inbox to catch up on all completed and failed tasks across multiple projects in a single view.

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
