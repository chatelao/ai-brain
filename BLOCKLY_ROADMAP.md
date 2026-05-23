# Roadmap: Blockly Automation Engine Implementation

## Progress Overview

| Phase | Description | Status |
|-------|-------------|--------|
| 1 | Database Schema & API | ✅ |
| 2 | UI Integration (Next-Gen) | 🌑 |
| 3 | Custom Block Definitions | 🌑 |
| 4 | JavaScript Execution Sandbox | 🌑 |
| 5 | Scoping & Inheritance | 🌑 |
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
- [ ] Install `blockly` and associated React wrappers in the `web/` project.
- [ ] Implement `BlocklyEditor` component with dual-pane (Blocks/JS) view.
- [ ] Integrate `BlocklyEditor` into User Settings page.
- [ ] Integrate `BlocklyEditor` into Project Settings page.
- [ ] Implement persistence logic (saving to API on change/save).

## Phase 3: Custom Block Definitions
- [ ] Define the `OnEvent` trigger block.
- [ ] Implement Action blocks (Merge, Duplicate, Set Label, Notify).
- [ ] Implement Logic/Predicate blocks (Check Label, Is Task Ready).
- [ ] Configure `javascriptGenerator` to produce clean, proxy-aware JS code.

## Phase 4: JavaScript Execution Sandbox
- [ ] Select and integrate a JS sandbox engine (e.g., PHP `v8js` or Node.js bridge).
- [ ] Implement the `SandboxService` to initialize and run JS logic.
- [ ] Define and inject API proxy functions into the sandbox context.
- [ ] Implement resource limits (timeout, memory) for script execution.

## Phase 5: Scoping & Inheritance
- [ ] Update `WebhookHandler` to fetch and execute Global logic.
- [ ] Update `WebhookHandler` to fetch and execute Local logic.
- [ ] Implement precedence rules (Local overrides Global actions).
- [ ] Add logging for Blockly execution (success, failure, actions taken).

## Phase 6: End-to-End Workflows
- [ ] Test "Auto-Merge on CI Success" workflow.
- [ ] Test "Auto-Repeat on Issue Closed" workflow.
- [ ] Test "Custom Telegram Notification on Agent Error" workflow.
- [ ] Verify that manual JS edits correctly sync back to Blockly (where possible).
