# Concept: Blockly Event Handling

![Blockly Logo](https://raw.githubusercontent.com/google/blockly/master/media/logo_only.png)

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

## 3. Scoping & Inheritance
The system supports two levels of visual coding to balance ease of management with project-specific needs.

### 3.1 Global Logic (Per User)
Global rules are defined at the user account level and apply to **all projects** owned by that user. This is ideal for standardizing behaviors across the entire portfolio (e.g., "Always notify me on Telegram when an agent fails").

### 3.2 Local Logic (Per Project)
Local rules are defined at the project level. They can override or supplement global logic for project-specific requirements (e.g., "Only auto-merge in the 'Legacy' repository if the 'stable' label is present").

### 3.3 Execution Order and Precedence
When an event occurs:
1. **Global Logic** is executed first.
2. **Local Logic** is executed second.
3. If a conflict occurs (e.g., Global says "Don't Merge", Local says "Merge"), the **Local Logic takes precedence**.

## 4. Dual-Language Definition (Blockly & JavaScript)
The system supports bidirectional synchronization between the visual Blockly interface and a text-based JavaScript editor, similar to the [Microsoft MakeCode](https://www.microsoft.com/en-us/makecode) experience.

![Dual-Language Editor](https://www.plantuml.com/plantuml/proxy?cache=no&src=https://raw.githubusercontent.com/chatelao/ai-brain/main/specification/BLOCKLY_DUAL_EDITOR.puml)

### 4.1 Bidirectional Sync
* **Blockly to JS**: As blocks are manipulated, the corresponding JavaScript code is automatically generated.
* **JS to Blockly**: When switching back to the visual editor, the JavaScript code is transpiled back into blocks.
* **Validation**: If manual JavaScript edits cannot be mapped back to blocks, the system preserves the code but may disable the visual editor until it's compatible.

### 4.2 Why JavaScript?
* **Developer Familiarity**: Advanced users can write logic faster using standard JavaScript syntax.
* **Logic Complexity**: While Blockly is great for simple flows, JavaScript excels at complex conditional logic and data manipulation.
* **No Python Support**: To keep the execution environment lightweight and focused, Python support is explicitly excluded.

## 5. Visual Programming with "OnEvent"
The core of the Blockly integration is the `OnEvent` block. Users can drag and drop action blocks into the `OnEvent` handler to define their workflow.

![Visual OnEvent Logic](https://www.plantuml.com/plantuml/proxy?cache=no&src=https://raw.githubusercontent.com/chatelao/ai-brain/main/specification/BLOCKLY_VISUAL.puml)

### Example Logic (JavaScript Equivalent):
```javascript
onEvent("Checks Completed", (event) => {
  if (readLabel("auto-merge") && event.status === "success") {
    merge();
    notify("PR merged automatically");
  }

  if (readLabel("auto-repeat")) {
    duplicate();
  }
});
```
* **Event**: `Checks Completed` (Success)
* **Logic**:
    1. If `Read Label` ("auto-merge") is true:
        - `Merge` Pull Request
        - `Notify` user "PR merged automatically"
    2. If `Read Label` ("auto-repeat") is true:
        - `Duplicate` Issue

## 6. UI Integration
The Blockly editor is integrated into two primary locations in the Next-Gen UI:

* **User Settings / Profile**: For managing **Global Logic**.
* **Project Settings**: For managing **Local Logic**.

Each interface includes:
* **Editor Workspace**: A dual-pane editor (or toggle-able view) allowing users to switch between **Blocks** and **JavaScript**.
* **Toolbox**: Contains event trigger blocks, logic blocks, and specific Agent Control action blocks (available in both modes).
* **Persistence**:
    * Global config is stored in the `users` table (e.g., `blockly_config` column).
    * Local config is stored in the `projects` table (e.g., `blockly_config` column).

## 7. Backend Execution
When an event occurs (e.g., in `WebhookHandler` or `cronjob.php`), the system will:

1. **Fetch Configs**: Retrieve both the user's `global_blockly_config` and the project's `local_blockly_config`.
2. **Initialize Engine**: Initialize a JavaScript execution environment (e.g., using a sandboxed JS engine in PHP or a Node.js-based microservice).
3. Provide the current `Task` and `Event` context to the execution environment.
4. Execute the logic and perform the defined actions via the existing `GitHubService`, `JulesService`, and `NotificationService`.

## 8. Portability and Extensibility
By using Blockly, we ensure that:
* **Non-developers** can create custom workflows without writing code.
* **Complex conditions** (e.g., "Only merge if label X exists AND checks passed AND it's a weekday") can be easily implemented.
* **New events and actions** can be added to the system by simply registering new Blockly block definitions.
