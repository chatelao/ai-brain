# Design: Blockly Automation Engine

## Overview
The Blockly Automation Engine provides a visual and text-based interface for defining event-driven workflows. It bridges the gap between simple triggers and complex custom logic by allowing users to compose "OnEvent" handlers using either a visual block-based editor or a standard JavaScript editor.

## 1. Technical Architecture

### 1.1 Dual-Representation Persistence
To support the "MakeCode" style bidirectional synchronization, the configuration is stored in a dual-format JSON structure:

```json
{
  "xml": "<xml ...>...</xml>",
  "js": "onEvent('PR Merged', (event) => { ... });",
  "version": "1.0"
}
```

- **`xml`**: The primary source for the Blockly workspace state.
- **`js`**: The generated (and potentially edited) code used for actual execution.
- **Storage**: Stored in `users.blockly_config` (Global) and `projects.blockly_config` (Local).

### 1.2 Execution Sandbox
Logic execution occurs in a secure, isolated environment to prevent malicious code from compromising the server.

- **Engine**: A sandboxed JavaScript interpreter (e.g., `v8js` for PHP or an internal Node.js-based execution microservice).
- **Context Injection**: For every event, the sandbox is initialized with:
    - `event`: Metadata about the trigger (type, timestamp, task context).
    - `task`: Current task properties (ID, status, labels, PR details).
    - **API Bridges**: Pre-defined functions (`merge`, `setLabel`, `notify`) that act as proxies to backend services.

## 2. Frontend Integration (Next-Gen UI)

### 2.1 Editor Workspace
The editor is implemented as a React component in `web/src/components/blockly/BlocklyEditor.tsx`.

- **Library**: `blockly` with React wrappers.
- **Toolbox**: A categorized list of blocks (Events, Actions, Logic, Loops, Math).
- **Sync Logic**:
    - `onChange` in Blockly workspace triggers `javascriptGenerator` to update the JS view.
    - Manual JS edits trigger a "transpiler" (e.g., using Blockly's `serialization` or a custom parser) to attempt to update the blocks.

### 2.2 Custom Block Definitions
Custom blocks are defined for the Agent Control ecosystem:

- **Events**: `on_event` (Top-level wrapper).
- **Actions**: `action_merge`, `action_duplicate`, `action_set_label`, `action_notify`.
- **Logic**: `check_label_exists`, `is_task_ready`.

## 3. Backend Action Mapping

When the JS engine calls a proxy function, it maps to the following PHP services:

| JS Proxy | Backend Service | PHP Method |
| :--- | :--- | :--- |
| `merge()` | `App\GitHubService` | `mergePullRequest(taskId)` |
| `duplicate()` | `App\Task` | `duplicate(taskId)` |
| `setLabel(name)` | `App\GitHubService` | `addLabel(taskId, name)` |
| `removeLabel(name)` | `App\GitHubService` | `removeLabel(taskId, name)` |
| `notify(msg)` | `App\NotificationService`| `notify(userId, msg, ...)` |
| `triggerAgent()` | `App\JulesService` | `trigger(taskId)` |

## 4. Execution Flow & Scoping

1. **Event Interception**: `WebhookHandler` or `cronjob.php` detects an event.
2. **Context Resolution**: Identify the relevant `project` and `user`.
3. **Global Execution**:
    - Load `user.blockly_config.js`.
    - Execute in sandbox.
4. **Local Execution**:
    - Load `project.blockly_config.js`.
    - Execute in sandbox.
5. **Action Dispatch**: Collect all actions requested during execution and dispatch them via the respective services.

## 5. Security & Validation

- **Timeout**: Each script execution has a strict timeout (e.g., 500ms) to prevent infinite loops.
- **Memory Limit**: Restricted memory allocation within the sandbox.
- **No Network/FS Access**: The sandbox has no access to `fetch`, `XMLHttpRequest`, or filesystem modules; it can only communicate via the provided API bridges.
