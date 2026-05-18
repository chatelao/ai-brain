# Design: Agent Control PHP Application

## Architecture
The application follows a modular architecture, separating concerns between the presentation layer, business logic, and data access.

### Component Diagram
The following diagram illustrates the high-level components and their interactions:

```plantuml
@startuml
!theme plain
skinparam componentStyle uml2

package "Frontend (Tailwind CSS, Alpine.js)" {
    [UI Pages] as UI
    [AJAX Endpoints] as AJAX
}

package "Backend (PHP 8.3)" {
    package "API Handlers" {
        [WebhookHandler] as WH
        [TelegramWebhookHandler] as TWH
    }

    package "Services" {
        [GitHubService] as GHS
        [JulesService] as JS
        [TelegramService] as TS
        [NotificationService] as NS
        [MigrationService] as MS
    }

    package "Models & Data Access" {
        [User] as UserM
        [Project] as ProjectM
        [Task] as TaskM
        [Database] as DB
        [Auth] as AuthM
    }

    package "Logging & Monitoring" {
        [Logger] as Log
        [WebhookLogger] as WHLog
    }
}

database "MySQL Database" as MySQL

cloud "External Systems" {
    [GitHub API/Webhooks] as GitHub
    [Google Jules API] as Jules
    [Telegram API] as Telegram
    [Google SSO] as SSO
}

' Interactions
UI --> AJAX : fetch/post
AJAX --> AuthM : authenticate
AJAX --> GHS : repo/issue data
AJAX --> JS : agent status
AJAX --> NS : notifications
AJAX --> UserM : profile/settings

WH --> GHS : process events
WH --> TaskM : update tasks
WH --> NS : trigger notifications

TWH --> TS : response
TWH --> UserM : link accounts

NS --> TS : external delivery
NS --> [BrowserChannelHandler] : in-app delivery

GHS --> GitHub : REST API
JS --> Jules : REST API
TS --> Telegram : REST API
AuthM --> SSO : OAuth 2.0

UserM --> DB
ProjectM --> DB
TaskM --> DB
DB --> MySQL
Log --> MySQL
WHLog --> MySQL

@enduml
```

## Tech Stack
- **Development & Production**:
    - **Language**: PHP 8.3+
    - **Server**: PHP-compatible web server (e.g., Apache, Nginx)
    - **Database**: MySQL
    - **Frontend**: Tailwind CSS (Responsive UI), Alpine.js (Interactivity)
    - **Authentication**: Google SSO via `google/apiclient`
    - **API Integration**:
        - **GitHub**: `knplabs/github-api`
        - **Google Jules**: `guzzlehttp/guzzle` (REST API client)
        - **Telegram**: `guzzlehttp/guzzle` (for webhook responses and API calls)
- **Testing**:
    - **CI/CD**: GitHub Actions
    - **Tools**: PHPUnit for unit testing, Mocking libraries for API responses

## Data Model
- **Users**: Stores user profiles, Google SSO identifiers, and associated GitHub account tokens.
- **Projects**: Links users (via their connected GitHub accounts) to GitHub repositories.
- **Agents**: Configurable agent definitions and their capabilities.
- **Tasks**: Logs of agent activities, linked to GitHub issues and project progress.

## API Integration Strategy
- **GitHub**: Use webhooks to listen for issue events and the REST API to fetch details and post updates.
- **Google Jules**: Utilize secure API calls to trigger and manage agent sessions.
- **Google SSO**: Implement OAuth 2.0 flow for secure user authentication.
- **Telegram**: Implement webhook handler with secret token validation and asynchronous processing using `fastcgi_finish_request()`.

### Telegram Integration Details
- **TelegramService**: A wrapper for the Telegram Bot API using Guzzle. It handles outgoing messages, supporting HTML parse mode and custom bot tokens.
- **TelegramWebhookHandler**: Processes incoming updates from Telegram. It validates the webhook secret and handles commands like `/start` for account linking.
- **Secure Linking Flow**:
  1. User clicks "Link Telegram" in the dashboard.
  2. A random `telegram_link_token` is generated and stored in the `users` table.
  3. User is directed to the Telegram bot with the token as a parameter (e.g., `/start <token>`).
  4. The bot receives the token, matches it to the user, and stores the `telegram_chat_id` in the `user_telegram_accounts` table.
  5. The `telegram_link_token` is cleared upon successful linking.

## Security
- Secure storage of API tokens using environment variables.
- Input validation and sanitization for all user-provided data.
- Session management for authenticated users.

## Sub-Designs
Detailed architectural and technical designs for specific features can be found in the following documents:
- [**Telegram Chat Control Design**](CHAT_DESIGN.md): Callback handling, inline keyboards, and mobile interaction logic.
- [**Notification System Design**](NOTIF_DESIGN.md): Service architecture, delivery channel implementations, and database schema.
