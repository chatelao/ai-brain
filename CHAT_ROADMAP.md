# Roadmap: Telegram Chat Control Implementation

## Progress Overview

| Phase | Description | Status |
|-------|-------------|--------|
| 1 | Callback Infrastructure | ✅ |
| 2 | Action-Enabled Notifications | ✅ |
| 3 | [UC-C1] Remote Task Recovery | ✅ |
| 4 | [UC-C2] One-Tap PR Merging | ✅ |
| 5 | UX & Feedback Loop | 🚧 |

## Goals

- Transform Telegram bot into an interactive management interface. ✅
- Support common task operations (Retry, Restart, Merge) directly from chat. ✅
- Provide immediate feedback for remote actions. ✅
- Maintain secure, authorized access to project resources. ✅

## Phase 1: Callback Infrastructure
- [x] Extend `App\TelegramWebhookHandler` to process `callback_query` updates.
- [x] Implement secure user verification for callback queries (via `chat_id`).
- [x] Implement resource authorization check (verify user permissions for `taskId`).
- [x] Add `answerCallbackQuery` support to `App\TelegramService`.
- [x] Implement basic routing for callback data patterns (e.g., `action:id`).

## Phase 2: Action-Enabled Notifications
- [x] Update `NotificationService::notify` to accept an optional `actions` array.
- [x] Enhance `TelegramChannelHandler` to translate actions into `InlineKeyboardMarkup`.
- [x] Define standardized callback data format (e.g., `retry:{taskId}`, `merge:{taskId}`).
- [ ] Test button rendering in Telegram for different notification types.

## Phase 3: [UC-C1] Remote Task Recovery
- [x] Implement `retry` action handler in `TelegramWebhookHandler`.
- [x] Integrate with `GitHubService::postComment()` to trigger agent retries via "retry" signal.
- [x] Implement `restart` action handler for fresh Jules sessions via label toggling.
- [ ] Verify task recovery flow from a "Failed" notification.

## Phase 4: [UC-C2] One-Tap PR Merging
- [x] Implement `merge` action handler in `TelegramWebhookHandler`.
- [x] Integrate with `GitHubService::mergePullRequest()`.
- [x] Ensure associated issues are closed after successful merge.
- [ ] Verify PR merging flow from a "CI Success" notification.

## Phase 5: UX & Feedback Loop
- [x] Implement `editMessageText` in `TelegramService` to update message state after action.
- [x] Show "Loading..." or success/failure toasts using `answerCallbackQuery`.
- [x] Update original notification text to reflect new status (e.g., "✅ PR merged...").
- [ ] Final end-to-end testing of the interactive chat experience.
