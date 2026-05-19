# Concept: On-Event and On-State Behaviors

This document describes the reactive behaviors of the Agent Control application, detailing how it responds to external events and how it behaves based on its internal state.

### Related Concepts
- **[Notifications System (NOTIF_CONCEPT.md)](NOTIF_CONCEPT.md)**: Describes how state transitions and events trigger user alerts. (Secondary concept).

## 1. Unified Task State Mapping

The application defines a set of unified task states to provide a consistent view of progress across different external tools (GitHub, Jules). These states represent the "Source of Truth" for the task's lifecycle and include a shared set of visual indicators (Colors and Emojis) used across the web dashboard and Telegram.

| Unified State | Substate | Color (Web) | Emoji | Description | Mapping Logic (Source States) |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **`CREATED`** | - | Gray | ⏳ | Task is waiting to be processed by the agent. | `GH.Issue:open` AND `Jules.Session:none` |
| **`PROCESSING`** | **`ANALYZING`** | Blue | 🚧 | The agent is researching or analyzing the task. | `Jules.Session:researching` |
| | **`PLANNING`** | Blue | 🚧 | A plan is being created or is awaiting approval. | `Jules.Session:planning` OR `Jules.Session:awaiting_plan_approval` |
| | **`EXECUTING`** | Yellow | 🚧 | The agent is actively coding the solution. | `Jules.Session:in-progress` OR `Jules.Session:coding` |
| | **`VERIFYING`** | Yellow | 🚧 | The agent is testing the solution locally. | `Jules.Session:testing` |
| | **`IMPLEMENTED`**| Yellow | 🚧 | The agent completed implementation (no PR yet).| `Jules.Session:finished` AND `GH.PR:none` |
| | **`CHECKING`** | Orange | 🔍 | Awaiting PR check results from GitHub. | `Jules.Session:finished` AND `GH.PR:open` AND `GH.PR:checks_running` |
| **`READY`** | - | Green | ✅ | All checks passed, task is ready for merge. | `Jules.Session:finished` AND `GH.PR:open` AND `GH.PR:checks_passed` |
| **`FINISHED`** | - | Purple | ✅ | The task has been successfully completed/merged.| `GH.Issue:closed` |
| **`FAILED`** | **`FAILED_JULES`** | Red | ❌ | An error occurred during agent execution. | `Jules.Session:failed` OR `Jules.Session:error` |
| | **`FAILED_PR`** | Red | ❌ | PR verification (checks) failed on GitHub. | `GH.PR:checks_failed` |

### 1.1 Source State Key
- **`GH.Issue:*`**: State of the GitHub Issue (open/closed).
- **`GH.PR:*`**: State of the associated GitHub Pull Request.
  - `checks_running`: Check suites are currently executing.
  - `checks_passed`: All check suites completed with success.
  - `checks_failed`: One or more check suites failed.
- **`Jules.Session:*`**: Status returned by the Google Jules API for the session.

## 2. State-Event Diagram

The following diagram illustrates the transitions between the unified task states and their relationship with external tool states.

![Task State-Event Diagram](https://www.plantuml.com/plantuml/proxy?cache=no&src=https://raw.githubusercontent.com/chatelao/ai-brain/main/specification/TASK_STATE_EVENTS.puml)

## 3. On-Event Behaviors

These behaviors are triggered by specific events originating from GitHub (webhooks), user interactions, or periodic polling.

### 3.1 GitHub Webhooks

| Event | Action |
| :--- | :--- |
| **Issue Labeled (`Jules`)** | Triggers `App\Task::triggerAgent()`. Task moves to `PROCESSING/ANALYZING` (via Jules). |
| **Issue Closed** | Updates `github_state` to `closed`. Task moves to `FINISHED`. May trigger "Auto-Repeat". |
| **Issue Reopened** | Updates `github_state` to `open`. Task moves back to `CREATED` or active state. |
| **Issue Deleted** | Triggers a notification and removes the task from the local database. |
| **Pull Request Created** | Links the PR to the task. Task moves to `PROCESSING/CHECKING`. |
| **Pull Request Merged** | Triggers "Auto-Repeat" logic if the issue has the `Auto-Repeat` label. |
| **Check Suite Completed** | If `success`, task moves to `READY`. If `failure/timeout`, task moves to `FAILED/FAILED_PR`. |

### 3.2 User Interactions (UI)

| Action | Effect |
| :--- | :--- |
| **Manual Sync (Refresh Icon)** | Forces a synchronization of GitHub issues and Jules statuses for the project. |
| **Trigger Agent (Button)** | Manually starts a Jules session for a task. |
| **Merge & Close (Button)** | Merges the associated PR and closes the GitHub issue via API (moves task to `FINISHED`). |
| **Retry (Button)** | Sends a "retry" command to a failed Jules session. |
| **Restart (Button)** | Aborts the current Jules session and restarts it by toggling the `Jules` label. |

### 3.3 Jules Status Polling

Periodic calls to `App\Task::refreshJulesStatus` update the unified task state based on the remote Jules session state:

- `STATE_RESEARCHING` -> `PROCESSING/ANALYZING`
- `STATE_PLANNING` -> `PROCESSING/PLANNING`
- `STATE_IN_PROGRESS` -> `PROCESSING/EXECUTING`
- `STATE_CODING` -> `PROCESSING/EXECUTING`
- `STATE_TESTING` -> `PROCESSING/VERIFYING`
- `STATE_FINISHED` / `STATE_COMPLETED` -> `PROCESSING/CHECKING` (if PR exists) or `FINISHED` (if issue closed)
- `STATE_FAILED` / `STATE_ERROR` -> `FAILED/FAILED_JULES`

## 4. Automation & Operations

This section details the logic for automated and manual operations within the application.

### 4.1 Pull Request Operations

#### Merge & Close
When a task has an associated Pull Request (PR), it may be eligible for a "Merge & Close" operation directly from the project issue list.

**Conditions for presenting the "Merge & Close" option:**
- Task is in `READY` state.
- The Pull Request is reported as **mergeable** by GitHub.
- All status checks (more than 1) are in a **passed** or **skipped** state.

**Action:**
- Merges the Pull Request via the GitHub API.
- Closes the associated GitHub issue (moves Task to `FINISHED`).

### 4.2 Failed Jules Session Operations

When a task is in the `FAILED` state, the following options are presented:

#### Retry
**Action:**
- Sends a command to the existing Jules Session: "retry to finish the task".
- Aims to move the Task back to an active state.

#### Restart
**Action:**
- Aborts or deletes the current Jules session.
- Removes and re-adds the "Jules" label to the GitHub issue to trigger a fresh agent session.
- Task moves back to `CREATED`.

### 4.3 Issue Lifecycle Automation

#### Auto-Repeat Duplication
To support recurring tasks, the system implements an "Auto-Repeat" mechanism.

**Trigger:**
- A task reaches `FINISHED` state AND carried the **"Auto-Repeat"** label.

**Action:**
- The system automatically duplicates the issue.
- The new issue **includes** the "Jules" label to trigger the agent.
- The new issue **excludes** the "Auto-Repeat" label.

## 5. Feature Availability

The system exhibits different behaviors and interactive options depending on the unified state of a task.

- **Merge & Close**: Only available if state is `READY`, PR is mergeable and checks passed.
- **Retry / Restart**: Only available when state is `FAILED`.

## 6. Notifications & Broadcasts

The application triggers notifications for state transitions and external events. A "Broadcast" is a notification sent to external channels (Telegram, Browser), while all notifications are persisted in the In-App Inbox.

### 6.1 State Transition Notifications

| Unified State | Notification | Broadcast (Default) | Human Follow-up Required |
| :--- | :---: | :---: | :--- |
| `CREATED` | Yes | No | No |
| `ANALYZING` | Yes | No | No |
| `PLANNING` | Yes | No | No |
| `EXECUTING` | Yes | No | No |
| `VERIFYING` | Yes | No | No |
| `IMPLEMENTED` | Yes | No | No |
| `CHECKING` | Yes | No | No |
| `READY` | Yes | **Yes** | **Yes** (Merge needed) |
| `FINISHED` | Yes | No | No (FYI) |
| `FAILED_JULES` | Yes | **Yes** | **Yes** (Retry/Restart needed) |
| `FAILED_PR` | Yes | **Yes** | **Yes** (Fix needed) |

### 6.2 Event Trigger Sources

Only system-triggered events with a need for human follow-up are typically broadcast to external channels to minimize noise.

| Event | Trigger Source | Follow-up Needed | Broadcast |
| :--- | :--- | :---: | :---: |
| **Issue Opened** | User (GitHub) | No | No |
| **Issue Closed** | User (GitHub) / System (Merge) | No | No |
| **Issue Reopened** | User (GitHub) | No | No |
| **Issue Deleted** | User (GitHub) | No | No |
| **PR Created** | System (Jules) | No | No |
| **PR Merged** | User (UI) / System (Auto) | No | No |
| **Agent Started** | User (UI) / System (Label) | No | No |
| **Agent Completed**| System (Jules) | No | No |
| **Check Suite Fail**| System (GitHub CI) | **Yes** | **Yes** |
| **Check Suite Pass**| System (GitHub CI) | **Yes** | **Yes** |
| **Auto-Repeat** | System | **Yes** | **Yes** |
