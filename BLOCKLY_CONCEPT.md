# Concept: Blockly Event Handling

## Overview
This document describes the integration of [Blockly](https://developers.google.com/blockly) for customizable event-driven automation within Agent Control. It allows users to define complex logic for handling system and external events through a visual "OnEvent" interface.

## 1. Event Triggers
The Blockly engine can be triggered by a wide range of events across the system:

| Source | Event | Description |
| :--- | :--- | :--- |
| **GitHub** | `Issue Labeled` | Triggered when a specific label is added to an issue. |
| | `Issue Closed` | Triggered when an issue is closed (manually or via PR). |
| | `PR Created` | Triggered when a new Pull Request is opened. |
| | `PR Merged` | Triggered when a Pull Request is successfully merged. |
| | `Checks Completed` | Triggered when GitHub Check Suites finish (Success/Failure). |
| **Jules** | `Status Changed` | Triggered when Jules moves between states (e.g., Planning to Executing). |
| | `Agent Error` | Triggered when the agent encounters a failure. |
| **Telegram** | `Command Received` | Triggered by custom bot commands or inline button clicks. |
| **System** | `Cron Sync` | Triggered during the periodic background synchronization. |

## 2. Available Actions
Blockly scripts can trigger one or more actions in response to events:

| Action | Description |
| :--- | :--- |
| **`Merge`** | Merges the Pull Request associated with the current task. |
| **`Duplicate`** | Creates a copy of the current issue (useful for Auto-Repeat). |
| **`Read Label`** | Checks if a specific label exists on the GitHub issue. |
| **`Set Label`** | Adds a specific label to the GitHub issue. |
| **`Remove Label`** | Removes a specific label from the GitHub issue. |
| **`Rename Label`** | Renames an existing label on the GitHub issue. |
| **`Post Comment`** | Adds a comment to the GitHub issue or Pull Request. |
| **`Trigger Agent`** | Manually starts or retries a Jules session. |
| **`Notify`** | Sends a notification via Telegram or the In-App Inbox. |

## 3. Visual Programming with "OnEvent"
The core of the Blockly integration is the `OnEvent` block. Users can drag and drop action blocks into the `OnEvent` handler to define their workflow.

### Example Logic:
* **Event**: `Checks Completed` (Success)
* **Logic**:
    1. If `Read Label` ("auto-merge") is true:
        - `Merge` Pull Request
        - `Notify` user "PR merged automatically"
    2. If `Read Label` ("auto-repeat") is true:
        - `Duplicate` Issue

## 4. UI Integration
The Blockly editor will be integrated into the **Project Settings** page of the Next-Gen UI.

* **Editor Workspace**: A dedicated tab or section in settings.
* **Toolbox**: Contains event trigger blocks, logic blocks, and the specific Agent Control action blocks.
* **Persistence**: The generated XML or JSON representation of the blocks is stored in the `projects` table (e.g., in a `blockly_config` column).

## 5. Backend Execution
When an event occurs (e.g., in `WebhookHandler` or `cronjob.php`), the system will:

1. Fetch the `blockly_config` for the relevant project.
2. If config exists, initialize a lightweight Blockly execution engine (or transpiler to PHP/JavaScript).
3. Provide the current `Task` and `Event` context to the execution environment.
4. Execute the logic and perform the defined actions via the existing `GitHubService`, `JulesService`, and `NotificationService`.

## 6. Portability and Extensibility
By using Blockly, we ensure that:
* **Non-developers** can create custom workflows without writing code.
* **Complex conditions** (e.g., "Only merge if label X exists AND checks passed AND it's a weekday") can be easily implemented.
* **New events and actions** can be added to the system by simply registering new Blockly block definitions.
