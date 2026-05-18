# Concept: Notifications System

## Overview
The notification system aims to keep users informed about critical state transitions across their projects and tasks. By focusing on the Unified Task State model, it provides a consistent alerting experience regardless of whether the change was triggered by GitHub or the Jules agent.

## Business Cases
-   **Reduced Cycle Times**: Real-time alerts for transitions to `FAILED` or `READY` states minimize "wait time" in the development lifecycle.
-   **Improved Operational Awareness**: Provides stakeholders with immediate visibility into the progress and health of automated AI tasks without manual monitoring of external logs.

## Notification Channels
1.  **In-App Inbox**: A centralized list of notifications within the web application dashboard.
2.  **Active Browser Notifications**: Real-time push notifications or browser-level alerts when the dashboard is open.
3.  **Telegram**: Instant messages delivered via the linked Telegram bot for mobile and desktop connectivity.

## Key Notification Events & Logic
The system triggers notifications based on transitions within the Unified Task State model.

### Event Types
-   **Transition to FAILED**: Triggered when a task enters the `FAILED` state, either due to an agent error (`FAILED_JULES`) or PR check failures (`FAILED_PR`).
-   **Transition to READY**: Triggered when a task enters the `READY` state, indicating that all automated checks have passed and the Pull Request is ready for merge.
-   **Transition to FINISHED**: Triggered when a task reaches the `FINISHED` status (e.g., associated GitHub issue is closed or PR is merged).
-   **External Issue Events**: Triggered by external lifecycle events for GitHub issues, such as being `opened`, `reopened`, or `deleted`.
-   **Agent Progress Updates**: Occasional notifications for significant progress within the `PROCESSING` group, such as when a task enters `PLANNING` or `VERIFYING`.

### Triggering Rules
Notifications are generated consistently across multiple event sources (Event Source Parity):
-   **Real-time Webhooks**: Immediate triggers from incoming GitHub or CI/CD webhooks.
-   **Backend Polling (`cronjob.php`)**: Periodic synchronization that detects state changes missed by webhooks.
-   **Manual Refreshes (`project.php`)**: User-initiated synchronizations that refresh project and task states.

## Use Cases
-   **<a name="UC-N1"></a>Jump to Source (Deep Linking) (UC-N1)**: A user clicks on a "Task Ready" notification and is immediately taken to the corresponding GitHub Pull Request or Jules session page.
-   **<a name="UC-N2"></a>Immediate Failure Response (UC-N2)**: A developer receives a "Task Failed" notification (state `FAILED_PR`) on Telegram and can quickly initiate a fix, even when away from their primary workstation.
-   **<a name="UC-N3"></a>Streamlined Review Workflow (UC-N3)**: Reviewers are notified as soon as a task enters the **`READY`** state, accelerating the path to merging.
-   **<a name="UC-N4"></a>Passive Progress Monitoring (UC-N4)**: A developer receives a "Task Finished" notification when a task reaches the **`FINISHED`** state, allowing them to switch contexts at the optimal time.
-   **<a name="UC-N5"></a>Noise Management for Experimental Projects (UC-N5)**: A user mutes all notifications for a specific experimental repository to maintain focus on stable production repositories.
-   **<a name="UC-N6"></a>Unified Task Oversight (UC-N6)**: A user scans their In-App Inbox to catch up on all state transitions across multiple projects in a single view.

## Configuration & Customization
To avoid notification fatigue, users can customize their preferences at multiple levels:

### User-Level Settings
- Global toggles for active channels (In-App, Browser, Telegram).
- Global toggles for notification types (e.g., `github_issue`, `github_pr`, `task_status`, `agent_event`).

### Project-Level Settings
- Enable or disable specific notification types for an entire repository.
- Broadcast filters for internal task statuses (Unified States).

### Task-Level Settings
- Mute notifications for specific tasks/issues.
