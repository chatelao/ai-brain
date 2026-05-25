# Roadmap: Blockly Automation Engine Implementation

## Progress Overview

| Phase | Description | Status |
|-------|-------------|--------|
| 1 | Database Schema & API | ✅ |
| 2 | UI Integration (Next-Gen) | ✅ |
| 3 | Custom Block Definitions | ✅ |
| 4 | JavaScript Execution Sandbox | ✅ |
| 5 | Scoping & Inheritance | 🏗️ |
| 6 | End-to-End Workflows | 🌑 |

## Goals

- Enable non-developers to create complex custom workflows via Blockly.
- Provide advanced users with a powerful JavaScript editor for automation.
- Support "MakeCode" style bidirectional sync between Blocks and JS.
- Ensure secure, sandboxed execution of user-defined logic.
- Implement hierarchical logic (Global vs. Project-specific).

## Phase 1: Database Schema & API
- [x] Add `blockly_config` (JSON) column to `users` table.
- [x] Add `blockly_config` (JSON) column to `projects` table.
- [x] Update `/api/user.php` to support getting/setting global Blockly config.
- [x] Update `/api/project.php` to support getting/setting local Blockly config.
- [x] Document new API fields in `api/openapi.yaml`.

## Phase 2: UI Integration (Next-Gen)
- [x] Install `blockly` and associated React wrappers in the `web/` project.
- [x] Implement `BlocklyComponent` (base wrapper for Blockly injection).
- [x] Implement `BlocklyEditor` component with dual-pane (Blocks/JS) view.
- [x] Integrate `BlocklyEditor` into User Settings page.
- [x] Integrate `BlocklyEditor` into Project Settings page.
- [x] Implement persistence logic (saving to API on change/save).

## Phase 3: Custom Block Definitions
- [x] Define the `OnEvent` trigger block.
- [x] Implement basic Action blocks (`Merge`, `Duplicate`, `Notify`).
- [x] Implement advanced Action blocks (`Set Label`, `Remove Label`, `Post Comment`, `Trigger Agent`).
- [x] Implement Logic/Predicate blocks (`Read Label`, `Is Task Ready`).
- [x] Configure `javascriptGenerator` to produce clean, proxy-aware JS code.

## Phase 4: JavaScript Execution Sandbox
- [x] Create `SandboxService.php` to interface with the JS runner.
- [x] Implement `scripts/blockly-runner.js` skeleton using Node.js `vm` module.
- [x] Implement proxy handlers for actions (`notify`, `merge`, `setLabel`, etc.) in the runner.
- [x] Implement proxy handlers for predicates (`readLabel`, `isTaskReady`) in the runner.
- [x] Define the JSON interface for `event` and `task` data context.
- [x] Implement resource limits (timeout) and basic error handling in the runner.
- [x] Add unit tests for `blockly-runner.js` in the `test/` directory.
- [x] Implement action dispatching logic in `SandboxService.php`.
- [x] Add integration tests for `SandboxService` in `test/Integration/SandboxServiceTest.php`.

## Phase 5: Scoping & Inheritance
- [x] Fetch Global Blockly config from `users` table in `WebhookHandler`.
- [x] Fetch Local Blockly config from `projects` table in `WebhookHandler`.
- [x] Implement sequential execution of Global then Local logic in `WebhookHandler`.
- [ ] Implement precedence rules (Local overrides Global actions) during execution.
- [ ] Add detailed execution logging to `task_logs` for auditability and debugging.
- [ ] Implement a "Dry Run" mode for testing Blockly logic without performing actions.

## Phase 6: End-to-End Workflows
- [ ] Test "Auto-Merge on CI Success" workflow.
- [ ] Test "Auto-Repeat on Issue Closed" workflow.
- [ ] Test "Custom Telegram Notification on Agent Error" workflow.
- [ ] Verify that manual JS edits correctly sync back to Blockly (where possible).
