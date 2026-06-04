# Roadmap: Telegram Chat Control Implementation

## Progress Overview

| Phase | Description | Status |
|-------|-------------|--------|
| 1 | Callback Infrastructure | ✅ |
| 2 | Action-Enabled Notifications | ✅ |
| 3 | [UC-C1] Remote Task Recovery | ✅ |
| 4 | [UC-C2] One-Tap PR Merging | ✅ |
| 5 | UX & Feedback Loop | ✅ |
| 6 | [UC-C3] Quick Task Acknowledgment | ✅ |
| 7 | [UC-C4] Chat Cleanup | ✅ |
| 8 | Advanced Interactions | ✅ |
| 9 | Interactive Task Management | ✅ |
| 10 | Enhanced Navigation & Contextual Actions | ✅ |
| 11 | Notification Settings Management | ✅ |
| 12 | Pagination & Search | ✅ |
| 13 | Enhanced Management & Logging | ✅ |

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
- [x] Define standardized callback data format (e.g., `retry:{taskId}`, `restart:{taskId}`, `merge:{taskId}`).
- [x] Test button rendering in Telegram for different notification types.

## Phase 3: [UC-C1] Remote Task Recovery
- [x] Implement `retry` action handler in `TelegramWebhookHandler`.
- [x] Integrate with `GitHubService::postComment()` to trigger agent retries via "retry" signal.
- [x] Implement `restart` action handler for fresh Jules sessions via label toggling.
- [x] Verify task recovery flow from a "Failed" notification.

## Phase 4: [UC-C2] One-Tap PR Merging
- [x] Implement `merge` action handler in `TelegramWebhookHandler`.
- [x] Integrate with `GitHubService::mergePullRequest()`.
- [x] Ensure associated issues are closed after successful merge.
- [x] Verify PR merging flow from a "CI Success" notification.

## Phase 5: UX & Feedback Loop
- [x] Implement `editMessageText` in `TelegramService` to update message state after action.
- [x] Show "Loading..." or success/failure toasts using `answerCallbackQuery`.
- [x] Update original notification text to reflect new status (e.g., "✅ PR merged...").
- [x] Final end-to-end testing of the interactive chat experience.

## Phase 6: [UC-C3] Quick Task Acknowledgment
- [x] Update `WebhookHandler` to include the `acknowledge` action for new 'jules' issues.
- [x] Implement `acknowledge` action handler in `TelegramWebhookHandler`.
- [x] Define what "Acknowledgment" does in the system (e.g., clear from pending queue).
- [x] Verify acknowledgment flow from a "New Issue" notification.

## Phase 7: [UC-C4] Chat Cleanup
- [x] Implement `deleteMessage` in `App\TelegramService`.
- [x] Capture Telegram `message_id` and `chat_id` in `TelegramChannelHandler` and store in notification metadata.
- [x] Implement `delete` method in `NotificationChannelInterface` and its handlers.
- [x] Update `NotificationService` to support automatic and manual message cleanup.
- [x] Implement `/cleanup` command in `TelegramWebhookHandler`.
- [x] Verify cleanup flow by marking a notification as read and using manual command.

## Phase 8: Advanced Interactions
- [x] Implement `/status` command to pull current task summary.
- [x] Implement `/tasks` command to list active tasks.
- [x] Implement `/help` command to list available commands.

## Phase 9: Interactive Task Management
- [x] Implement `/projects` command to list active projects.
- [x] Enhance `/tasks` list with "Actions" buttons for each task.
- [x] Implement `view_task:{taskId}` callback to show task details and available actions.
- [x] Update `/help` command with new options.

## Phase 10: Enhanced Navigation & Contextual Actions
- [x] Enhance `/projects` with inline buttons for task filtering.
- [x] Support project-based filtering in `/tasks`.
- [x] Implement `refresh_task:{taskId}` action to force a status sync.

## Phase 11: Notification Settings Management
- [x] Implement `/settings` command to view current channel preferences.
- [x] Add inline buttons to toggle notification channels (Telegram, In-App, Browser).
- [x] Update `handleCallback` to process toggle actions and refresh settings view.

## Phase 12: Pagination & Search
- [x] Implement pagination for `/tasks` list (Next/Prev buttons).
- [x] Implement `/search` command for finding tasks.

## Phase 13: Enhanced Management & Logging
- [x] Implement `view_logs:{taskId}` action to see recent task logs in chat.
- [x] Implement status-based filtering directly from `/status` summary.
- [x] Support shortcut command `/task <number>` to quickly view task details.
