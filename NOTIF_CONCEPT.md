# Concept: Notifications System

### Related Concepts
- **[On-Event and On-State Behaviors (STATE_EVENTS_CONCEPT.md)](STATE_EVENTS_CONCEPT.md)**: The primary concept defining the states and events that trigger notifications. This document must be adjusted if `STATE_EVENTS_CONCEPT.md` is updated.

## Overview
The notification system aims to keep users informed about critical events across their projects and tasks. It provides a multi-channel approach to ensure timely updates whether the user is actively using the dashboard or is on the move.

## Business Cases
| Case | Description |
| :--- | :--- |
| **Reduced Cycle Times** | Real-time alerts for build failures and PR availability minimize "wait time" in the development lifecycle. |
| **Improved Operational Awareness** | Provides stakeholders with immediate visibility into the progress and health of automated AI tasks without manual monitoring. |

## Notification Channels
1.  **In-App Inbox**: A centralized list of notifications within the web application dashboard.
2.  **Active Browser Notifications**: Real-time push notifications or browser-level alerts when the dashboard is open.
3.  **Telegram**: Instant messages delivered via the linked Telegram bot for mobile and desktop connectivity.

## Key Notification Events & Logic
The system triggers notifications based on the following logic:

### Event Types
-   **Build Failed**: Triggered when a GitHub Check Suite or Check Run fails.
-   **PR Available**: Triggered when a new Pull Request is discovered or created.
-   **Session Failed**: Triggered when a Jules session enters a failed or error state.
-   **Task Completed**: Triggered when a task reaches the `completed` or `implemented` status.
-   **Issue State Change**: Triggered when a GitHub issue is opened, closed, reopened, or deleted.

### Triggering Rules
Notifications are generated consistently across multiple event sources (Event Source Parity):
-   **Real-time Webhooks**: Immediate triggers from incoming GitHub or CI/CD webhooks.
-   **Backend Polling (`cronjob.php`)**: Periodic synchronization that detects changes missed by webhooks.
-   **Manual Refreshes (`project.php`)**: User-initiated synchronizations that refresh project and task states.

### Broadcasting Logic
To minimize notification noise, the system distinguishes between **In-App Inbox** notifications and **External Broadcasts** (Telegram/Browser).
- Only **System-triggered** events are eligible for broadcasting.
- Human-initiated actions do not trigger broadcasts to avoid redundant alerts for the active user.
- Users can granularly enable or disable notifications for specific task states via **Status Notification Preferences**.

## Use Cases
| ID | Use Case | Description |
| :--- | :--- | :--- |
| <a name="UC-N1"></a>**UC-N1** | **Jump to Source (Deep Linking)** | A user clicks on a "PR Available" notification and is immediately taken to the corresponding GitHub Pull Request page. |
| <a name="UC-N2"></a>**UC-N2** | **Immediate Build Failure Response** | A developer receives a "Build Failed" notification on Telegram and can quickly initiate a fix, even when away from their primary workstation. |
| <a name="UC-N3"></a>**UC-N3** | **Streamlined PR Review Workflow** | Reviewers are notified as soon as a new PR is ready for feedback, accelerating the path to merging. |
| <a name="UC-N4"></a>**UC-N4** | **Passive Progress Monitoring** | A developer receives a "Task Completed" notification when Jules finishes a long-running task, allowing them to switch contexts at the optimal time. |
| <a name="UC-N5"></a>**UC-N5** | **Noise Management for Experimental Projects** | A user mutes all notifications for a specific experimental repository to maintain focus on stable production repositories. |
| <a name="UC-N6"></a>**UC-N6** | **Unified Task Oversight** | A user scans their In-App Inbox to catch up on all completed and failed tasks across multiple projects in a single view. |

## Configuration & Customization
To avoid notification fatigue, users can customize their preferences at multiple levels:

### User-Level Settings
- Global toggles for active channels (In-App, Browser, Telegram).
- Global toggles for notification types (e.g., `github_issue`, `github_pr`, `task_status`, `agent_event`).

### Project-Level Settings
- Enable or disable specific notification types for an entire repository.
- Broadcast filters for internal task statuses.

### Task-Level Settings
- Mute notifications for specific tasks/issues.
