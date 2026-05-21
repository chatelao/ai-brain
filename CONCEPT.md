# Concept: Agent Control PHP Application

## Overview
This application aims to provide a centralized platform for controlling and coordinating Google Jules agents through GitHub issues. It leverages a PHP-based web server, MySQL database, and Google SSO for secure, multi-user management.

## Business Cases
| Case | Description |
| :--- | :--- |
| **Centralized Agent Management** | Simplify the coordination of multiple AI agents across various projects. |
| **Workflow Automation** | Automate repetitive tasks by triggering agent actions directly from project management tools like GitHub. |
| **Improved Collaboration** | Enable team members to manage and monitor agent activities in a unified interface. |

## Use Cases
![Use Cases](https://www.plantuml.com/plantuml/proxy?cache=no&src=https://raw.githubusercontent.com/chatelao/ai-brain/main/specification/use_cases.puml)

| ID | Use Case | Description |
| :--- | :--- | :--- |
| <a name="UC-1"></a>**UC-1** | **User Authentication** | Secure login using Google SSO to manage access for different users, with support for linking multiple GitHub accounts per user. |
| <a name="UC-2"></a>**UC-2** | **Project Coordination** | Linking GitHub repositories and issues from any connected GitHub account to specific agent tasks. |
| <a name="UC-3"></a>**UC-3** | **Agent Triggering** | Automatically or manually initiating Google Jules agents based on GitHub issue activity. |
| <a name="UC-4"></a>**UC-4** | **Status Monitoring** | Tracking the progress and results of agent-led tasks within the application. |

## Core Entities & Mapping
The following table illustrates the core entities of the system and their representations across integrated services.

| Entity | Application | Google OAuth | GitHub | Jules | Telegram | Relationship |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **USER** | `users` table | `google_id` | `user_github_accounts` | `jules_api_key` | `user_telegram_accounts` | 1 User : N GitHub, 1 User : 1 Telegram |
| **PROJECT** | `projects` table | - | Repository (`github_repo`) | - | - | 1 User : N Projects |
| **TASK** | `tasks` table | - | Issue (`issue_number`) | Session Status | - | 1 Project : N Tasks |

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

## Logging & Performance Monitoring
To ensure reliability and maintainability, this application implements a robust logging strategy. This includes traditional event logging and automated performance monitoring for database and external API interactions.

### Logging Levels
The application uses the following standard logging levels:
- **DEBUG**: Detailed information, typically of interest only when diagnosing problems.
- **INFO**: Confirmation that things are working as expected.
- **WARNING**: An indication that something unexpected happened, or indicative of some problem in the near future.
- **ERROR**: Due to a more serious problem, the software has not been able to perform some function.
- **CRITICAL**: A serious error, indicating that the program itself may be unable to continue running.

### Performance Monitoring Strategy
The application automatically monitors the execution duration of critical components.
- **Database Performance**: All database queries executed through the `Database` class are timed. Any query taking longer than 1.0 second is automatically logged.
- **External API Performance**: All external API requests (e.g., GitHub REST API, Google Jules API) are timed. Any request with a duration exceeding 1.0 second is automatically logged.

## Sub-Concepts
For detailed information on specific functional areas, refer to the following sub-concept documents:

| Concept | File | Description |
| :--- | :--- | :--- |
| **Cron Job System** | [`CRONJOB_CONCEPT.md`](CRONJOB_CONCEPT.md) | Logic for triggering periodic synchronization of status changes. |
| **Telegram Chat Control** | [`CHAT_CONCEPT.md`](CHAT_CONCEPT.md) | Mobile interaction strategy and interactive bot capabilities. |
| **State & Event** | [`STATE_EVENTS_CONCEPT.md`](STATE_EVENTS_CONCEPT.md) | Detailed state transitions and reactive system behaviors. |
| **Notification System** | [`NOTIF_CONCEPT.md`](NOTIF_CONCEPT.md) | Multi-channel delivery and user preference management. |
