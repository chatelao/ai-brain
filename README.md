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
