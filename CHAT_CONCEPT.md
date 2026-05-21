# Concept: Telegram Chat Control

## Overview
The Telegram Chat Control feature extends the application's mobile capabilities by transforming the Telegram bot from a passive notification channel into an active control interface. The goal is to allow users to manage project activities with minimal typing, using interactive UI elements like inline buttons to trigger predefined actions.

## Business Cases
| Case | Description |
| :--- | :--- |
| **Increased Responsiveness** | Users can react to critical task events immediately from their mobile devices. |
| **Operational Efficiency** | Reduces the friction of common task operations (Retrying, Restarting, Merging) by providing one-tap actions. |
| **Improved Focus** | Developers can handle routine task lifecycle management during short breaks, keeping projects moving. |
| **Noise Reduction** | Keeps the Telegram chat environment clean by removing stale or addressed notifications. |

## Interaction Principles
The system prioritizes "selection over writing". Interactions occur primarily:
1. **Reactive (Post-Notification)**: Every actionable event notification sent to Telegram includes an inline keyboard with relevant options.
2. **Asynchronous (Pull)**: Users can interact with the bot to pull current status information or trigger cleanup operations independently of received notifications.

## Use Cases
| ID | Use Case | Description |
| :--- | :--- | :--- |
| <a name="UC-C1"></a>**UC-C1** | **Remote Task Recovery** | A user receives a "Jules Session Failed" notification on Telegram. They tap a "Retry" or "Restart" button directly in the chat to recover the task. |
| <a name="UC-C2"></a>**UC-C2** | **One-Tap PR Merging** | When a task is completed and all CI checks pass, the user is notified on Telegram and can merge the PR and close the issue with a single tap. |
| <a name="UC-C3"></a>**UC-C3** | **Quick Task Acknowledgment** | Users can acknowledge new tasks or state changes, clearing them from their active mental queue. |
| <a name="UC-C4"></a>**UC-C4** | **Chat Cleanup** | To maintain a clear workspace, users can trigger a "Cleanup" command or the system can automatically delete messages from Telegram once the corresponding notification is marked as read in the application. |

## Interaction Design
Whenever an actionable event occurs, the notification message sent to Telegram will include an inline keyboard with relevant options.

### Activity Diagram
![Telegram Chat Control Activity](https://www.plantuml.com/plantuml/proxy?cache=no&src=https://raw.githubusercontent.com/chatelao/ai-brain/main/specification/CHAT_ACTIVITY.puml)
