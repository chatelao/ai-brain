# Design: Cron Job System

## Overview
The Cron Job system is implemented as a set of background services triggered by an external scheduler. It handles the iteration and synchronization logic required to maintain system state parity.

## High-Level Architecture
- **Trigger**: An external scheduler makes an HTTP GET request to the cron entry point.
- **Entry Point**: `src/frontend/cronjob.php` serves as the execution gateway.
- **Service Execution**: Orchestrates calls to `App\Task::syncIssues` and `App\Task::refreshJulesStatus`. During `refreshJulesStatus`, the system also performs PR discovery and check suite polling. Detailed state transition logic is documented in [STATE_EVENTS_CONCEPT.md](STATE_EVENTS_CONCEPT.md).

## Logic Flow
1. **Authentication**: Validates the request against a pre-shared secret (`CRON_SECRET`).
2. **User Discovery**: Fetches all users from the database.
3. **Project Iteration**: For each user, identifies all linked projects.
4. **Task Processing**: Iterates through active projects to perform:
   - **Issue Synchronization**: Updates local issues from GitHub.
   - **Agent Status Refresh**: Polls Jules API for session updates.
   - **PR & Check Suite Sync**: Discovers associated PRs and polls for CI/CD results to update task states.

## Security Considerations
To prevent unauthorized triggering of resource-intensive synchronization tasks:
- **Secret Key Validation**: The `CRON_SECRET` environment variable must be configured.
- **Request Authorization**: The entry point requires a matching `key` parameter in the URL (e.g., `cronjob.php?key=YOUR_SECRET`).
- **Access Control**: If the secret is missing or incorrect, the system returns a `403 Forbidden` response.
