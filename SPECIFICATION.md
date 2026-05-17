# Specification: Agent Control Application

This document defines the functional features of the Agent Control application, organized by UX element, and maps them to the design and conceptual foundations.

## 1. Web Frontend (Pages)

### 1.1 Dashboard (`index.php`)
The primary entry point for authenticated users to oversee their projects.
- **Project Cards**: Visual summary of each linked repository.
    - *Coverage*: [DESIGN.md#data-model](DESIGN.md#data-model) (Projects)
    - *Use Case*: [CONCEPT.md#UC-4](CONCEPT.md#UC-4) (Status Monitoring)
- **Status Squares**: Color-coded grid representing the status of the most recent tasks/issues.
    - *Coverage*: [DESIGN.md#data-model](DESIGN.md#data-model) (Tasks)
- **Quick Project Creation**: Form to link new GitHub repositories using authenticated accounts.
    - *Coverage*: [DESIGN.md#api-integration-strategy](DESIGN.md#api-integration-strategy) (GitHub)
    - *Use Case*: [CONCEPT.md#UC-2](CONCEPT.md#UC-2) (Project Coordination)
- **Autorepeat Task Monitoring**: Dedicated section for tasks that are currently running in an automated loop.
- **Navigation**: Quick links to Settings, Templates, and Logout.

### 1.2 Project Details (`project.php`)
In-depth management of a single repository and its associated agent tasks.
- **Task Synchronization**: Manually sync issues from GitHub to the local database.
    - *Coverage*: [DESIGN.md#api-integration-strategy](DESIGN.md#api-integration-strategy) (GitHub)
- **Manual Agent Trigger**: Button to explicitly start a Jules session for a specific issue.
    - *Coverage*: [DESIGN.md#api-integration-strategy](DESIGN.md#api-integration-strategy) (Google Jules)
    - *Use Case*: [CONCEPT.md#UC-3](CONCEPT.md#UC-3) (Agent Triggering)
- **Rerun Task**: Ability to duplicate an issue on GitHub and immediately trigger the agent again.
- **Issue Templates UI**: Quick-select templates to create standardized GitHub issues directly from the dashboard.
    - *Coverage*: [DESIGN.md#data-model](DESIGN.md#data-model) (Issue Templates)
- **Roadmap Visualization**: Listing of roadmap files (e.g., `ROADMAP.md`) found in the repository.
- **Project Settings Link**: Shortcut to project-specific configuration.

### 1.3 Task Details (`task.php`)
Granular view of a specific agent intervention.
- **Markdown Rendering**: Secure display of the original GitHub issue body and Jules analysis.
- **Status Overview Sidebar**: Unified view of GitHub Issue state, Jules Session status, and Pull Request status.
    - *Use Case*: [CONCEPT.md#UC-4](CONCEPT.md#UC-4) (Status Monitoring)
- **Pull Request Integration**: Deep links to associated PRs with status indicators (Open, Merged, Failed).
- **Jules Message History**: Display of the most recent comments posted by the agent on the GitHub issue.
- **Execution Logs**: Real-time (or near real-time) log stream from the agent's processing session.
    - *Coverage*: [DESIGN.md#data-model](DESIGN.md#data-model) (Logs)

### 1.4 Issue Templates (`templates.php`)
Management of reusable issue structures.
- **CRUD Operations**: Create, Read, Update, and Delete templates with placeholder support (e.g., `%1`, `%2`).
- **Placeholder Aliasing**: Define user-friendly names for template parameters.
- **SQL Export**: Ability to export templates for backup or portability.

### 1.5 User Settings (`settings.php`)
Global configuration for the user account.
- **GitHub Account Management**: Link/unlink multiple GitHub OAuth accounts.
    - *Coverage*: [DESIGN.md#api-integration-strategy](DESIGN.md#api-integration-strategy) (Google SSO / GitHub)
    - *Use Case*: [CONCEPT.md#UC-1](CONCEPT.md#UC-1) (User Authentication)
- **Telegram Bot Configuration**: Input for custom Bot Token and Webhook Secret.
    - *Coverage*: [DESIGN.md#telegram-integration-details](DESIGN.md#telegram-integration-details)
- **API Keys**: Manage Google Jules API credentials.
- **Notification Toggles**: Channel-level (In-App, Telegram) and event-level toggles.
    - *Coverage*: [NOTIF_DESIGN.md#user_notification_settings](NOTIF_DESIGN.md#user_notification_settings)

## 2. Notification System

### 2.1 In-App Inbox (`navbar-icons.php`)
- **Notification Bell**: Reactive icon with unread count indicator.
    - *Coverage*: [NOTIF_DESIGN.md#1-in-app-inbox](NOTIF_DESIGN.md#1-in-app-inbox)
- **Dropdown List**: Preview of recent notifications with "Mark as Read" functionality.
- **Deep Linking**: Clicking a notification navigates directly to the relevant Task or GitHub PR.
    - *Coverage*: [NOTIF_DESIGN.md#deep-linking](NOTIF_DESIGN.md#deep-linking)
    - *Use Case*: [NOTIF_CONCEPT.md#UC-N1](NOTIF_CONCEPT.md#UC-N1) (Jump to Source)
- **All Notifications Page**: Dedicated list (`notifications.php`) for auditing history.
    - *Use Case*: [NOTIF_CONCEPT.md#UC-N6](NOTIF_CONCEPT.md#UC-N6) (Unified Task Oversight)

### 2.2 Telegram Alerts
- **Real-time Event Delivery**: Push notifications to the linked Telegram chat for build failures, PRs, and task completions.
    - *Coverage*: [NOTIF_DESIGN.md#3-telegram](NOTIF_DESIGN.md#3-telegram)
    - *Use Case*: [NOTIF_CONCEPT.md#UC-N2](NOTIF_CONCEPT.md#UC-N2) (Immediate Build Failure Response)

## 3. Telegram Bot Interface

### 3.1 Bot Interaction (`telegram-webhook.php`)
- **Account Linking**: Secure association of Telegram `chat_id` using a unique `/start <token>` command.
    - *Coverage*: [DESIGN.md#secure-linking-flow](DESIGN.md#secure-linking-flow)
- **Asynchronous Processing**: Background handling of updates to ensure high responsiveness.
    - *Coverage*: [DESIGN.md#telegram-integration-details](DESIGN.md#telegram-integration-details)
- **Webhook Logging**: Audit trail of incoming Telegram requests for debugging in settings.
    - *Coverage*: [HOWTO_DEBUG_TELEGRAM_BOT.md](HOWTO_DEBUG_TELEGRAM_BOT.md)
