# Task Management

Agent Control transforms GitHub issues into actionable tasks. This guide explains how to interact with these tasks and trigger AI agents.

## Syncing Issues

By default, the application uses webhooks to receive updates from GitHub. However, you can manually trigger a synchronization to ensure everything is up to date.

1. Open the **Project Details** page.
2. Click the **"Sync Issues"** button.
3. The application will fetch the latest issues from GitHub and update the local task list.

## Running the AI Agent

You can trigger a Google Jules agent to analyze any open task.

1. In the **Project Details** page, find the task in the list.
2. Click the **"Run Agent"** button.
3. The agent will:
    - Update the task status to `in_progress`.
    - Post a "started" comment on the GitHub issue.
    - Analyze the issue content.
    - Post its findings as a comment on GitHub.
    - Update the task status to `analyzed`.

## Understanding Task Statuses

Tasks can have various statuses represented by colors and icons:

| Icon | Status | Description |
| :--- | :--- | :--- |
| ⏳ | `pending` | Initial state after sync, waiting for action. |
| 🚧 | `in_progress` | Agent is currently processing the task. |
| ✅ | `completed` | Task successfully processed or issue closed on GitHub. |
| ❌ | `failed_jules` | The AI agent encountered an error during processing. |
| ❌ | `failed_pr` | GitHub Pull Request checks failed for this task. |

## Detailed Task View

Click on a task title or issue number to view the **Task Details** page. This page provides:
- A Markdown rendering of the GitHub issue body.
- Direct links to the GitHub issue.
- **Agent Logs**: The full history of agent communication and status changes.
- **PR Status**: Information about any linked Pull Request.

---
Next: [Issue Templates](Issue-Templates.md)
