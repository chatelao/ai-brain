# Design: Telegram Chat Control

## Overview
Telegram Chat Control transforms the Telegram bot from a notification-only channel into an interactive management interface. It leverages Telegram's Inline Keyboards and Callback Queries to allow users to perform common task operations (Retry, Restart, Merge) directly from the chat.

## Architecture

### Component Diagram (Differential)
The following diagram highlights the changes required for Telegram Chat Control.
- **Orange**: Existing components requiring significant logic updates.
- **Green**: New components, interactions, or removed flows.

![Telegram Chat Control Component Diagram](https://www.plantuml.com/plantuml/proxy?cache=no&src=https://raw.githubusercontent.com/chatelao/ai-brain/main/specification/CHAT_COMPONENT.puml)

## Technical Details

### 1. Callback Query Handling
The `TelegramWebhookHandler` processes `callback_query` updates.
- **Authentication**: Every callback query must come from a `chat_id` linked to a valid `user_id`.
- **Authorization**: The system verifies that the user has appropriate permissions for the project associated with the `taskId`.

### 2. Inline Keyboards & Button Data
The `NotificationService` attaches action metadata to notifications, which `TelegramChannelHandler` translates into `InlineKeyboardMarkup`.

**Standardized Button Data Format:**
- `retry:{taskId}`
- `restart:{taskId}`
- `merge:{taskId}`
- `acknowledge:{taskId}`

### 3. Message Deletion & Cleanup
- The system tracks the Telegram `message_id` for each notification sent.
- Implements logic to call the `deleteMessage` API method when a notification is marked as read in the application or via a cleanup command.

### 4. Technical Requirements
- **Dynamic Button Generation**: Ability to attach action metadata to notification payloads.
- **State Mapping**: Securely map callback data to specific backend actions.
- **Feedback Loop**: Provide immediate feedback via `answerCallbackQuery` and update the original message using `editMessageText`.

## Use Case Realization

### [UC-C1] Remote Task Recovery
1.  Failure detected.
2.  Notification triggered with `retry` and `restart` actions.
3.  User taps "Retry".
4.  `TelegramWebhookHandler` receives `retry:{id}`, calls `JulesService::retry()`, and confirms success.

### [UC-C2] One-Tap PR Merging
1.  CI success reported.
2.  Notification triggered with `merge` action.
3.  User taps "Merge & Close".
4.  `TelegramWebhookHandler` receives `merge:{id}`, calls `GitHubService::mergePullRequest()`, then closes the issue.

### [UC-C3] Quick Task Acknowledgment
1.  New task or state change detected.
2.  Notification triggered with `acknowledge` action.
3.  User taps "Acknowledge".
4.  `TelegramWebhookHandler` receives `acknowledge:{id}`, updates internal state, and confirms.
