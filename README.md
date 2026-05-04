# Agent Control PHP Application
[![Documentation Status](https://readthedocs.org/projects/ai-brain/badge/?version=latest)](https://ai-brain.readthedocs.io/en/latest/?badge=latest)

## Overview
A PHP application to control agents from Google Jules, coordinated by GitHub repositories and issues. It provides a centralized platform for managing AI agent workflows through a unified web interface.

## Architecture
![Architecture](https://www.plantuml.com/plantuml/proxy?cache=no&src=https://raw.githubusercontent.com/chatelao/ai-brain/main/specification/architecture.puml)

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
| **Developer Experience** | Vagrant Environment | Pre-configured virtualized development environment for consistent setups. |
| **Developer Experience** | CI/CD Pipeline | Automated testing (PHPUnit, Playwright) and documentation builds via GitHub Actions. |
| **Developer Experience** | Comprehensive Documentation | Integrated Sphinx documentation (Read the Docs) and OpenAPI 3.0 specification. |

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

4. Set up your environment variables (DB_HOST, DB_NAME, DB_USER, DB_PASS, GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REDIRECT_URI).
5. Initialize the database using `src/sql/schema.sql`.

### Local Development
Start the local development server:
```bash
php -S localhost:8080 -t src/frontend
```

### Local Development with Vagrant
If you prefer to use a virtualized environment, you can use Vagrant:
1. Ensure you have Vagrant and VirtualBox installed.
2. Run `vagrant up` to start the VM and provision it.
3. The application will be accessible at `http://localhost:8080`.
4. You may need to update the environment variables in `/etc/nginx/sites-available/aibrain` inside the VM if you want to use GitHub or Google SSO.

### Troubleshooting Vagrant

#### Error: E_ACCESSDENIED (0x80070005)
If you encounter `VBoxManage.exe: error: Details: code E_ACCESSDENIED (0x80070005)` when running `vagrant up`:
- **Dropbox/OneDrive:** This error often occurs if the project is located in a folder synced by Dropbox or OneDrive. These services lock files that VirtualBox needs to access.
  - **Solution:** Move the project to a non-synced folder (e.g., `C:\ws\ai-brain`) or temporarily pause syncing while using Vagrant.
- **Stale VM Locks:** Sometimes VirtualBox background processes hang.
  - **Solution:** Kill all `VBoxSVC.exe` and `VBoxManage.exe` processes in Task Manager, delete the `.vagrant` folder in your project, and try again.

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
