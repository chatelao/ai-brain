# Agent Control PHP Application

## Overview
A PHP application to control agents from Google Jules, coordinated by GitHub repositories and issues. It provides a centralized platform for managing AI agent workflows through a unified web interface.

## Architecture
![Architecture](https://www.plantuml.com/plantuml/proxy?cache=no&src=https://raw.githubusercontent.com/chatelao/ai-brain/main/specification/architecture.puml)

### Used APIs and Interfaces

#### Production
| API / Interface | Description | Source Page |
| :--- | :--- | :--- |
| **Google SSO** | Secure user authentication and session management. | [Google Identity Platform](https://developers.google.com/identity/protocols/oauth2) |
| **GitHub REST API** | Management of repositories, issues, and webhooks. | [GitHub REST API Documentation](https://docs.github.com/en/rest) |
| **Google Jules API** | Orchestration and control of AI agents. | [Google AI / Gemini](https://ai.google.dev/) |
| **PHP** | Server-side scripting language for backend logic. | [PHP Official Documentation](https://www.php.net/docs.php) |
| **MySQL** | Relational database for storing user and project data. | [MySQL Documentation](https://dev.mysql.com/doc/) |
| **Tailwind CSS** | Utility-first CSS framework for responsive UI design. | [Tailwind CSS Documentation](https://tailwindcss.com/docs) |
| **Alpine.js** | Minimal framework for composing JavaScript behavior. | [Alpine.js Documentation](https://alpinejs.dev/docs) |
| **Guzzle** | PHP HTTP client for sending API requests. | [Guzzle Documentation](https://docs.guzzlephp.org/) |
| **GitHub PHP API** | Object-oriented wrapper for the GitHub API. | [KnpLabs GitHub API](https://github.com/KnpLabs/php-github-api) |
| **Telegram Bot API** | Mobile-based agent control and notifications. | [Telegram Bot API](https://core.telegram.org/bots/api) |

#### Test/Documentation
| API / Interface | Description | Source Page |
| :--- | :--- | :--- |
| **PlantUML** | Architecture diagram generation from text. | [PlantUML Documentation](https://plantuml.com/) |

## Features
- **Centralized Agent Management**: Coordinate multiple AI agents across projects.
- **GitHub Integration**: Link repositories and issues to specific agent tasks.
- **Secure Authentication**: Google SSO for multi-user management.
- **Workflow Automation**: Trigger agent actions directly from project management tools.

## Getting Started

### Requirements
- PHP 8.3+
- MySQL
- Composer

### Installation
1. Clone the repository.
2. Install dependencies:
   ```bash
   ./scripts/install.sh
   ```
3. Set up your environment variables (DB_HOST, DB_NAME, DB_USER, DB_PASS, GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REDIRECT_URI).
4. Initialize the database using `src/sql/schema.sql`.

### Local Development
Start the local development server:
```bash
php -S localhost:8080 -t src/frontend
```

## Project Structure
- `src/frontend/`: Web entry points (index.php, login.php, etc.) and frontend assets.
- `src/backend/`: Core PHP logic and classes.
- `src/sql/`: Database schema and migrations.
- `test/`: PHPUnit tests and testing tools.
- `specification/`: Additional documentation, mockups, and external know-how.
- `CONCEPT.md`, `DESIGN.md`, `GEMINI.md`, `ROADMAP.md`: Project documentation.

## Documentation
- [Concept](CONCEPT.md)
- [Design](DESIGN.md)
- [Gemini Project Goal](GEMINI.md)
- [Roadmap](ROADMAP.md)
