# Concept: Agent Control PHP Application

## Overview
This application aims to provide a centralized platform for controlling and coordinating Google Jules agents through GitHub issues. It leverages a PHP-based web server, MySQL database, and Google SSO for secure, multi-user management.

## Business Cases
- **Centralized Agent Management**: Simplify the coordination of multiple AI agents across various projects.
- **Workflow Automation**: Automate repetitive tasks by triggering agent actions directly from project management tools like GitHub.
- **Improved Collaboration**: Enable team members to manage and monitor agent activities in a unified interface.

## Use Cases
- **<a name="UC-1"></a>User Authentication (UC-1)**: Secure login using Google SSO to manage access for different users, with support for linking multiple GitHub accounts per user.
- **<a name="UC-2"></a>Project Coordination (UC-2)**: Linking GitHub repositories and issues from any connected GitHub account to specific agent tasks.
- **<a name="UC-3"></a>Agent Triggering (UC-3)**: Automatically or manually initiating Google Jules agents based on GitHub issue activity.
- **<a name="UC-4"></a>Status Monitoring (UC-4)**: Tracking the progress and results of agent-led tasks within the application.

## High-Level Architecture
- **Frontend**: Web interface for users to manage projects and agents.
- **Backend**: PHP application handling logic, API integrations, and user sessions.
- **Database**: MySQL for storing user data, project configurations, and task logs.
- **Integrations**:
    - **Google SSO**: For authentication.
    - **GitHub REST API**: For repository and issue management.
    - **Google Jules API**: For agent control.
    - **Telegram Bot API**: For mobile-based agent control and notifications.

## Mobile Interaction & Notifications (Telegram)
The Telegram integration provides a mobile-first interface for users to monitor and interact with their AI agents. Key features include:
- **Account Linking**: Users securely link their Telegram account by using a unique token generated in the dashboard, which is then sent to the bot via the `/start` command.
- **Real-time Notifications**: Instant alerts for critical events such as task completions, session failures, or new pull requests.
- **Bot Customization**: Support for user-specific Telegram bots, allowing each user to use their own bot token and webhook secret for enhanced privacy and control.

## Sub-Concepts
For detailed information on specific functional areas, refer to the following sub-concept documents:

| Concept | File | Description |
| :--- | :--- | :--- |
| **Automation** | [`AUTOMATION_CONCEPT.md`](AUTOMATION_CONCEPT.md) | Operations for PRs, Jules sessions, and issue lifecycle automation. |
| **Telegram Chat Control** | [`CHAT_CONCEPT.md`](CHAT_CONCEPT.md) | Mobile interaction strategy and interactive bot capabilities. |
| **Logging & Monitoring** | [`CONCEPT_LOGGING.md`](CONCEPT_LOGGING.md) | Strategy for performance tracking and system auditability. |
| **State & Event** | [`CONCEPT_ONEVENT_ONSTATE.md`](CONCEPT_ONEVENT_ONSTATE.md) | Detailed state transitions and reactive system behaviors. |
| **Notification System** | [`NOTIF_CONCEPT.md`](NOTIF_CONCEPT.md) | Multi-channel delivery and user preference management. |
