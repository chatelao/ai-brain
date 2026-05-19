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
| **Issue labeled (`Jules`)** | Triggers `App\Task::triggerAgent()`. Task moves to `PROCESSING/ANALYZING` (via Jules). |
| **Issue closed** | Updates `github_state` to `closed`. Task moves to `FINISHED`. May trigger "Auto-Repeat". |
| **Issue reopened** | Updates `github_state` to `open`. Task moves back to `CREATED` or active state. |
| **Issue deleted** | Triggers a notification and removes the task from the local database. |
| **Pull Request created** | Links the PR to the task. Task moves to `PROCESSING/CHECKING`. |
| **Pull Request merged** | Triggers "Auto-Repeat" logic if the issue has the `Auto-Repeat` label. |
| **Check Suite completed** | If `success`, task moves to `READY`. If `failure/timeout`, task moves to `FAILED/FAILED_PR`. |

### 3.2 User Interactions (UI)

| Action | Effect |
| :--- | :--- |
| **Manual sync (Refresh Icon)** | Forces a synchronization of GitHub issues and Jules statuses for the project. |
| **Trigger Agent (Button)** | Manually starts a Jules session for a task. |
| **Merge & close (Button)** | Merges the associated PR and closes the GitHub issue via API (moves task to `FINISHED`). |
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
| **Issue opened** | User (GitHub) | No | No |
| **Issue closed** | User (GitHub) / System (Merge) | No | No |
| **Issue reopened** | User (GitHub) | No | No |
| **Issue deleted** | User (GitHub) | No | No |
| **PR created** | System (Jules) | No | No |
| **PR merged** | User (UI) / System (Auto) | No | No |
| **Agent started** | User (UI) / System (Label) | No | No |
| **Agent completed**| System (Jules) | No | No |
| **Check Suite fail**| System (GitHub CI) | **Yes** | **Yes** |
| **Check Suite pass**| System (GitHub CI) | **Yes** | **Yes** |
| **Auto-Repeat** | System | **Yes** | **Yes** |

## 7. Distinguishing Manual vs. Automated Actions

To minimize notification noise and ensure clear accountability, the system distinguishes between actions performed by a human user and those performed automatically by the system or external agents (Jules, GitHub CI).

### 7.1 The `is_system` Flag
All internal notifications carry an `is_system` boolean flag in their metadata. This flag serves as the primary mechanism for determining whether a notification should be broadcast to external channels (Telegram, Browser).

- **`is_system: true`**: The event was triggered by the system, an external webhook (e.g., GitHub Check Suite), or the Jules agent completion. These events are eligible for broadcasting if they require human follow-up.
- **`is_system: false`**: The event was directly initiated by a user via the web interface or by a human interaction on GitHub. These events are recorded in the in-app inbox but are **never broadcast** to external channels, as the user is already aware of their own action.

### 7.2 Detection Mechanisms

#### GitHub Webhooks
The `App\WebhookHandler` identifies user-triggered events by inspecting the `sender.type` field in the GitHub payload.
- If `sender.type === 'User'`, the action is considered manual (`is_system => false`).
- Events like `check_suite` completions or Jules bot comments are always treated as system-driven (`is_system => true`).

#### UI Interactions
Actions performed directly in the web dashboard (e.g., clicking "Run Agent", "Merge & Close", or creating an issue from a template) explicitly set the `is_system` flag to `false` when calling `NotificationService::notify()`.

### 7.3 Broadcasting Rules
The `App\NotificationService` enforces the following logic for external broadcasts:
1. **Manual Actions**: If `is_system` is `false`, the broadcast is suppressed.
2. **System Actions**: If `is_system` is `true`, the system further checks if the event is **actionable**. Only system events that require human follow-up (e.g., a task becoming `READY` for merge, or a session failing with `FAILED_JULES`) are dispatched to Telegram or Browser notifications.

## 8. Task State Machine (XState)

This section describes the task lifecycle using the [XState](https://xstate.js.org/) v5 specification and Mermaid diagrams, derived from the unified state mapping.

### 8.1 Visual Representation (Mermaid)

The diagram visually groups the core **Jules Processing** activities to distinguish them from GitHub-managed states like `CHECKING`.

```mermaid
stateDiagram
    [*] --> CREATED

    CREATED --> PROCESSING : JULES_STARTED

    state PROCESSING {
        state "Jules Processing" as JULES_ACTIVITY {
            state ANALYZING
            state PLANNING
            state EXECUTING
            state VERIFYING
            state IMPLEMENTED

            [*] --> ANALYZING
            ANALYZING --> PLANNING : RESEARCH_COMPLETE
            PLANNING --> EXECUTING : PLAN_APPROVED
            EXECUTING --> VERIFYING : CODE_COMPLETE
            VERIFYING --> IMPLEMENTED : TESTS_PASSED
        }
        state CHECKING

        JULES_ACTIVITY --> CHECKING : PR_CREATED
    }

    CHECKING --> READY : CHECKS_PASSED
    PROCESSING --> FAILED_JULES : JULES_ERROR
    CHECKING --> FAILED_PR : CHECKS_FAILED

    READY --> FINISHED : ISSUE_CLOSED
    READY --> FAILED_PR : NEW_COMMIT_FAILED

    state FAILED {
        state FAILED_JULES
        state FAILED_PR
    }

    FAILED --> CREATED : RESTART
    FAILED_JULES --> PROCESSING : RETRY

    FINISHED --> [*]
```

### 8.2 XState Machine Definition (v5)

```javascript
import { createMachine } from 'xstate';

/**
 * Task State Machine
 * Represents the unified lifecycle of a Jules Task.
 */
export const taskMachine = createMachine({
  id: 'task',
  initial: 'CREATED',
  states: {
    CREATED: {
      on: {
        JULES_STARTED: 'PROCESSING.ANALYZING',
        JULES_ERROR: 'FAILED.FAILED_JULES',
        ISSUE_CLOSED: 'FINISHED'
      }
    },
    PROCESSING: {
      initial: 'ANALYZING',
      states: {
        // Core Jules Processing Substates
        ANALYZING: {
          on: { RESEARCH_COMPLETE: 'PLANNING' }
        },
        PLANNING: {
          on: { PLAN_APPROVED: 'EXECUTING' }
        },
        EXECUTING: {
          on: { CODE_COMPLETE: 'VERIFYING' }
        },
        VERIFYING: {
          on: { TESTS_PASSED: 'IMPLEMENTED' }
        },
        IMPLEMENTED: {
          on: { PR_CREATED: 'CHECKING' }
        },
        // GitHub PR Check Substate
        CHECKING: {
          on: {
            CHECKS_PASSED: { target: '#task.READY' },
            CHECKS_FAILED: { target: '#task.FAILED.FAILED_PR' }
          }
        }
      },
      on: {
        JULES_ERROR: 'FAILED.FAILED_JULES',
        ISSUE_CLOSED: 'FINISHED'
      }
    },
    READY: {
      on: {
        ISSUE_CLOSED: 'FINISHED',
        NEW_COMMIT_FAILED: 'FAILED.FAILED_PR'
      }
    },
    FAILED: {
      initial: 'FAILED_JULES',
      states: {
        FAILED_JULES: {
          on: {
            RETRY: { target: '#task.PROCESSING.ANALYZING' }
          }
        },
        FAILED_PR: {}
      },
      on: {
        RESTART: { target: 'CREATED' },
        ISSUE_CLOSED: 'FINISHED'
      }
    },
    FINISHED: {
      type: 'final'
    }
  }
});
```

### 8.3 Mapping to Implementation

The machine states map to the following constants in `App\Task`:

- `CREATED`: `STATUS_CREATED`
- `PROCESSING.ANALYZING`: `STATUS_ANALYZING`
- `PROCESSING.PLANNING`: `STATUS_PLANNING`
- `PROCESSING.EXECUTING`: `STATUS_EXECUTING`
- `PROCESSING.VERIFYING`: `STATUS_VERIFYING`
- `PROCESSING.IMPLEMENTED`: `STATUS_IMPLEMENTED`
- `PROCESSING.CHECKING`: `STATUS_CHECKING`
- `READY`: `STATUS_READY`
- `FINISHED`: `STATUS_FINISHED`
- `FAILED.FAILED_JULES`: `STATUS_FAILED_JULES`
- `FAILED.FAILED_PR`: `STATUS_FAILED_PR`
