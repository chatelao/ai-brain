# Agent Control PHP Application
[![Documentation Status](https://readthedocs.org/projects/ai-brain/badge/?version=latest)](https://ai-brain.readthedocs.io/en/latest/?badge=latest)

## Overview
A PHP application to control agents from Google Jules, coordinated by GitHub repositories and issues. It provides a centralized platform for managing AI agent workflows through a unified web interface.

## Architecture
![Architecture](https://www.plantuml.com/plantuml/proxy?cache=no&src=https://raw.githubusercontent.com/chatelao/ai-brain/main/specification/architecture.puml)

### Database Schema
![Database Schema](https://www.plantuml.com/plantuml/proxy?cache=no&src=https://raw.githubusercontent.com/chatelao/ai-brain/main/schema.puml)

### Task Lifecycle
![Task States](https://www.plantuml.com/plantuml/proxy?cache=no&src=https://raw.githubusercontent.com/chatelao/ai-brain/main/TASK_STATE_EVENTS.puml)

### Used APIs and Interfaces

#### Production
| API / Interface | Description |
| :--- | :--- |
| [**Google SSO**](https://developers.google.com/identity/protocols/oauth2) | Secure user authentication and session management. |
| [**GitHub REST API**](https://docs.github.com/en/rest) | Management of repositories, issues, and webhooks. |
| [**Google Jules API**](https://ai.google.dev/) | Orchestration and control of AI agents. |
| [**PHP**](https://www.php.net/docs.php) | Server-side scripting language for backend logic. |
| [**MySQL**](https://dev.mysql.com/doc/) | Relational database for storing user and project data. |
| [**Tailwind CSS**](https://tailwindcss.com/docs) | Utility-first CSS framework for responsive UI design. |
| [**Alpine.js**](https://alpinejs.dev/docs) | Minimal framework for composing JavaScript behavior. |
| [**Guzzle**](https://docs.guzzlephp.org/) | PHP HTTP client for sending API requests. |
| [**GitHub PHP API**](https://github.com/KnpLabs/php-github-api) | Object-oriented wrapper for the GitHub API. |
| [**Telegram Bot API**](https://core.telegram.org/bots/api) | Mobile-based agent control and notifications. |

#### Test/Documentation
| API / Interface | Description |
| :--- | :--- |
| [**PlantUML**](https://plantuml.com/) | Architecture diagram generation from text. |

## Features

| Category | Feature | Description |
| :--- | :--- | :--- |
| **Authentication & Security** | Google SSO | Secure user authentication and session management. |
| **Authentication & Security** | Role-Based Access Control (RBAC) | Fine-grained permissions for administrators and regular users. |
| **Authentication & Security** | Security Hardening | Integrated CSRF protection and IP-based rate limiting for sensitive endpoints. |
| **GitHub Integration** | Multi-Account Support | Link and manage multiple GitHub accounts per application user. |
| **GitHub Integration** | Repository Synchronization | Seamlessly link repositories and synchronize issues as actionable tasks. |
| **GitHub Integration** | Automated Webhooks | Real-time updates for issue events (opened, edited, reopened) via secure webhooks. |
| **GitHub Integration** | Customizable Templates | Create and manage reusable GitHub issue templates with dynamic parameter support. |
| **Agent Orchestration** | Google Jules (Gemini) Integration | Power agent workflows using advanced AI models. |
| **Agent Orchestration** | Task Management | Track agent activity, execution logs, and status updates directly in the dashboard. |
| **Agent Orchestration** | Automated Feedback | Agents can post responses and status updates back to GitHub issues as comments. |
| **Telegram Connectivity** | Mobile Control | Interact with agents and receive status notifications via a dedicated Telegram Bot. |
| **Telegram Connectivity** | Secure Webhooks | Robust webhook handling with secret token validation. |
| **Telegram Connectivity** | Asynchronous Processing | Optimized response times using fast-cgi finish request for immediate acknowledgement. |
| **Developer Experience** | CI/CD Pipeline | Automated testing (PHPUnit, Playwright) and documentation builds via GitHub Actions. |
| **Developer Experience** | Comprehensive Documentation | Integrated Sphinx documentation (Read the Docs) and OpenAPI 3.0 specification. |

## Getting Started

### Requirements
- PHP 8.3+
- MySQL
- Composer

### Installation
See the [Installation Guide](INSTALL.md) for detailed instructions on how to install AI Brain on a web server using SSH.

For quick local installation:
1. Clone the repository.
2. Install dependencies:
 
   ```bash
   ./scripts/install.sh
   ```

4. Set up your environment variables (DB_HOST, DB_NAME, DB_USER, DB_PASS, GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REDIRECT_URI).
5. Initialize the database using `src/sql/schema.sql`.

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
- `CONCEPT.md`, `DESIGN.md`, `GEMINI.md`, `ROADMAP.md`, `NOTIF_ROADMAP.md`, `CHAT_ROADMAP.md`: Project documentation.

## Documentation
- [**User Wiki**](wiki/Home.md) - Comprehensive guide for users.
- [Concept](CONCEPT.md)
- [Design](DESIGN.md)
- [Gemini Project Goal](GEMINI.md)
- [Roadmap](ROADMAP.md)
- [Notification Roadmap](NOTIF_ROADMAP.md)
- [Telegram Chat Roadmap](CHAT_ROADMAP.md)
