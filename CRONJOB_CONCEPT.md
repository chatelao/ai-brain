# Concept: Cron Job System

## Overview
The Cron Job system provides a periodic trigger mechanism to perform background synchronization and maintenance tasks. It ensures that the application's state remains consistent with external services like GitHub and Google Jules, even if real-time webhooks are delayed or missed.

## Business Cases
- **Data Integrity**: Ensures the local database eventually reflects the source of truth (GitHub) through regular synchronization.
- **Automated Monitoring**: Maintains up-to-date agent status in the dashboard without requiring manual user refreshes.
- **System Reliability**: Acts as a "self-healing" mechanism that recovers from missed event-driven updates (e.g., failed webhooks).

## Use Cases
- **<a name="UC-CJ1"></a>Scheduled Issue Sync (UC-CJ1)**: The system periodically scans all linked GitHub repositories for new issues, updates to existing issues, or state changes (closed/reopened) to ensure the local task list is current.
- **<a name="UC-CJ2"></a>Periodic Agent Status Refresh (UC-CJ2)**: For all active tasks, the system polls the Google Jules API to fetch the latest execution status and logs, updating the internal state accordingly.
- **<a name="UC-CJ3"></a>Multi-User Background Processing (UC-CJ3)**: The cron job iterates through all registered users and their respective projects, performing maintenance tasks at scale without manual intervention.

## High-Level Architecture
- **Trigger**: An external scheduler (e.g., system `crontab` or a cloud-based cron service) makes an HTTP GET request to the cron entry point.
- **Entry Point**: `src/frontend/cronjob.php` serves as the execution gateway.
- **Logic Flow**:
    1. **Authentication**: Validates the request against a pre-shared secret.
    2. **User Discovery**: Fetches all users from the database.
    3. **Project Iteration**: For each user, identifies all linked projects.
    4. **Service Execution**:
        - `GitHubService`: Syncs repository issues.
        - `JulesService`: Refreshes agent session statuses.

## Security Considerations
To prevent unauthorized triggering of resource-intensive synchronization tasks, the cron system implements:
- **Secret Key Validation**: The `CRON_SECRET` environment variable must be configured on the server.
- **Request Authorization**: The entry point requires a matching `key` parameter in the URL (e.g., `cronjob.php?key=YOUR_SECRET`).
- **Access Control**: If the secret is configured but missing or incorrect in the request, the system returns a `403 Forbidden` response.
