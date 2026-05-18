# Concept: Telegram Chat Control

## Overview
The Telegram Chat Control feature extends the application's mobile capabilities by transforming the Telegram bot from a passive notification channel into an active control interface. The goal is to allow users to manage project activities with minimal typing, using interactive UI elements like inline buttons to trigger predefined actions.

## Business Cases
- **Increased Responsiveness**: Users can react to critical task events (like session failures or ready PRs) immediately from their mobile devices without needing to access the full web dashboard.
- **Operational Efficiency**: Reduces the friction of common task operations (Retrying, Restarting, Merging) by providing one-tap actions.
- **Improved Focus**: Developers can handle routine task lifecycle management during short breaks or while on the move, keeping projects moving without full context switches.

## Use Cases
- **<a name="UC-C1"></a>Remote Task Recovery (UC-C1)**: A user receives a "Jules Session Failed" notification on Telegram. Instead of opening a laptop, they tap a "Retry" or "Restart" button directly in the chat to recover the task.
- **<a name="UC-C2"></a>One-Tap PR Merging (UC-C2)**: When a task is completed and all CI checks pass, the user is notified on Telegram and can merge the PR and close the issue with a single tap on a "Merge & Close" button.
- **<a name="UC-C3"></a>Quick Task Acknowledgment (UC-C3)**: Users can acknowledge new tasks or state changes, clearing them from their active mental queue or providing feedback to the system that the event has been seen.

## High-Level Architecture
- **Telegram Bot API (Callback Queries)**: Utilizes Telegram's inline keyboards and callback queries to receive user interactions.
- **Telegram Webhook Handler**: A dedicated endpoint in the PHP backend that processes incoming `callback_query` updates from Telegram.
- **Operation Engine**: Reuses the logic defined in `AUTOMATION_CONCEPT.md` to perform actions via GitHub and Jules APIs.
- **Security & Authorization**: Every interaction is validated against the user's linked Telegram `chat_id` and the project permissions stored in the database.

## Interaction Design
The system prioritizes "selection over writing". Whenever an actionable event occurs, the notification message sent to Telegram will include an inline keyboard with relevant options.

### Activity Diagram
![Telegram Chat Control Activity](https://www.plantuml.com/plantuml/proxy?cache=no&src=https://raw.githubusercontent.com/chatelao/ai-brain/main/CHAT_ACTIVITY.puml)

## Technical Requirements
1. **Dynamic Button Generation**: The `NotificationService` must be able to attach action metadata to notifications, which the `TelegramChannelHandler` translates into inline buttons.
2. **State Management**: The application must securely map callback data (e.g., `retry_task_123`) to specific actions and entities.
3. **Feedback Loop**: After an action is triggered via Telegram, the bot should provide immediate feedback (e.g., an alert or a message update) confirming the action has started or succeeded.
