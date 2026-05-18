# Concept: Cron Job System

## Overview
The Cron Job system provides a periodic trigger mechanism to perform background synchronization and maintenance tasks. It ensures that the application's state remains consistent with external services like GitHub and Google Jules, even if real-time webhooks are delayed or missed.

## Business Cases
- **Data Integrity**: Ensures the local database reflects the source of truth (GitHub) through regular synchronization.
- **Automated Monitoring**: Maintains up-to-date agent status without requiring manual user refreshes.
- **System Reliability**: Acts as a "self-healing" mechanism that recovers from missed event-driven updates.

## Use Cases
- **<a name="UC-CJ1"></a>Scheduled Issue Sync (UC-CJ1)**: The system periodically scans all linked GitHub repositories for new issues, updates, or state changes to ensure the local task list is current.
- **<a name="UC-CJ2"></a>Periodic Agent Status Refresh (UC-CJ2)**: For all active tasks, the system polls the Google Jules API to fetch the latest execution status and logs.
- **<a name="UC-CJ3"></a>Multi-User Background Processing (UC-CJ3)**: The cron job iterates through all registered users and their projects, performing maintenance tasks at scale.

## Logic Flow
The cron system implements "Event Source Parity". For detailed information on how periodic polling affects task states and transitions, refer to [STATE_EVENTS_CONCEPT.md](STATE_EVENTS_CONCEPT.md).
