# Concept: On-Event and On-State Behaviors

This document describes the reactive behaviors of the Agent Control application, detailing how it responds to external events and how it behaves based on its internal state.

## 1. Unified Task State Mapping

To provide a tool-independent view of task progress, the following unified states and substatuses are defined. These are the canonical states for any task within the application.

### 1.1 Task States and External Peers

Every Task can have 1:N **"External Peers"**, which represent the task's representation in external systems. A peer consists of three elements:
- **Source**: The origin system (e.g., `GH.Issue`, `GH.PullRequest`, `Jules.Session`, `Telegram.Message`).
- **State**: The system-specific state of that peer.
- **ID**: The unique identifier of the peer in that external system.

The application uses the collection of a task's external peers to derive its unified state.

### 1.2 State Mapping Hierarchy

| Unified State | Substatus | Description | Mapping Logic (Peer States) |
| :--- | :--- | :--- | :--- |
| **`CREATED`** | N/A | Task created in the system, no active processing yet. | `GH.Issue:open` AND no active `Jules.Session`. |
| **`PROCESSING`** | `QUEUED` | Agent trigger requested, awaiting session start. | Triggered, but `Jules.Session` state is pending/none. |
| | `ANALYZING` | The agent is researching or analyzing the task. | `Jules.Session:researching` |
| | `PLANNING` | A plan is being created or awaiting approval. | `Jules.Session:planning` |
| | `EXECUTING` | The agent is implementing the solution. | `Jules.Session:coding` OR `Jules.Session:in-progress` |
| | `VERIFYING` | Jules finished, PR awaiting check results or manual review. | `Jules.Session:testing` OR (`Jules.Session:finished` AND `GH.PR:open` AND checks not yet passed) |
| **`READY`** | N/A | PR has passed all checks and is ready for merge. | `GH.PR:open` AND `GH.PR.Checks:success` |
| **`FINISHED`** | N/A | Work successfully completed and/or merged. | `GH.Issue:closed` OR (`Jules.Session:finished` AND `GH.PR:merged`) |
| **`FAILED`** | `JULES_FAILED` | Error occurred during Jules agent execution. | `Jules.Session:failed` OR `Jules.Session:error` |
| | `PR_FAILED` | PR verification checks failed. | `GH.PR:open` AND `GH.PR.Checks:failure` |

### 1.3 System-Specific State Matrix

The following table details how external system states map to the unified task state hierarchy.

| External System | External State | Unified State | Substatus |
| :--- | :--- | :--- | :--- |
| **GitHub Issue** | `open` | `CREATED` | N/A |
| **GitHub Issue** | `closed` | `FINISHED` | N/A |
| **GitHub PR** | `open` | `PROCESSING` | `VERIFYING` |
| **GitHub PR** | `merged` | `FINISHED` | N/A |
| **GitHub Checks** | `failure` | `FAILED` | `PR_FAILED` |
| **GitHub Checks** | `success` | `READY` | N/A |
| **Jules Session** | `researching` | `PROCESSING` | `ANALYZING` |
| **Jules Session** | `planning` | `PROCESSING` | `PLANNING` |
| **Jules Session** | `coding` | `PROCESSING` | `EXECUTING` |
| **Jules Session** | `in-progress` | `PROCESSING` | `EXECUTING` |
| **Jules Session** | `testing` | `PROCESSING` | `VERIFYING` |
| **Jules Session** | `completed` | `PROCESSING` / `FINISHED` | `VERIFYING` / N/A |
| **Jules Session** | `failed` | `FAILED` | `JULES_FAILED` |
| **Jules Session** | `error` | `FAILED` | `JULES_FAILED` |

## 2. State-Event Diagram

The following diagram illustrates how the Unified Task State is derived from external peer updates.

![Task State-Event Diagram](https://www.plantuml.com/plantuml/proxy?cache=no&src=https://raw.githubusercontent.com/chatelao/ai-brain/main/specification/TASK_STATE_EVENTS.puml)

## 3. On-Event Behaviors

Incoming events from External Peers trigger state re-derivation and potential outgoing actions.

### 3.1 GitHub Webhooks

- **Issue Labeled (`Jules`)**: Upserts `GH.Issue` peer state, triggers agent, upserts `Jules.Session` peer. State moves to `PROCESSING (QUEUED)`.
- **Issue Closed**: Updates `GH.Issue` peer state to `closed`. State moves to `FINISHED`.
- **PR Created**: Adds `GH.PullRequest` peer. State moves to `PROCESSING (VERIFYING)`.
- **Check Suite Completed**: Updates `GH.PR.Checks` state. Triggers state refresh. If `success`, moves to `READY`.

### 3.2 User Interactions (UI)

- **Retry/Restart**: Resets/updates `Jules.Session` peer state, triggering agent retry.

## 4. Automation & Operations

The application uses automation rules to respond to state changes:
- **Auto-Repeat**: When `GH.Issue` moves to `closed` with `state_reason: completed`, create a new task.
- **Merge & Close**: When in `READY` (or `PROCESSING (VERIFYING)` if checks passed), trigger merge API.

## 5. On-State Behaviors

### 5.1 Task Status Visuals

| Unified State | UI Indicator | Description |
| :--- | :--- | :--- |
| **`CREATED`** | Grey | Idle task. |
| **`PROCESSING`** | Blue/Yellow/Orange | Active lifecycle. |
| **`READY`** | Green (Light) | PR checks passed, ready for merge. |
| **`FINISHED`** | Green / Purple | Completed successfully. |
| **`FAILED`** | Red | Needs human intervention. |
