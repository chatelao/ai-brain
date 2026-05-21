# Concept: Cron Job System

## Overview
The Cron Job system provides a periodic trigger mechanism to perform background synchronization and maintenance tasks. It ensures that the application's state remains consistent with external services like GitHub and Google Jules, even if real-time webhooks are delayed or missed.

## Business Cases
| Case | Description |
| :--- | :--- |
| **Data Integrity** | Ensures the local database reflects the source of truth (GitHub) through regular synchronization. |
| **Automated Monitoring** | Maintains up-to-date agent status without requiring manual user refreshes. |
| **System Reliability** | Acts as a "self-healing" mechanism that recovers from missed event-driven updates. |

## Use Cases
| ID | Use Case | Description |
| :--- | :--- | :--- |
| <a name="UC-CJ1"></a>**UC-CJ1** | **Scheduled Issue Sync** | The system periodically scans all linked GitHub repositories for new issues, updates, or state changes to ensure the local task list is current. |
| <a name="UC-CJ2"></a>**UC-CJ2** | **Periodic Agent Status Refresh** | For all active tasks, the system polls the Google Jules API to fetch the latest execution status and logs. |
| <a name="UC-CJ3"></a>**UC-CJ3** | **Multi-User Background Processing** | The cron job iterates through all registered users and their projects, performing maintenance tasks at scale. |

## Logic Flow
The cron system implements "Event Source Parity", ensuring that system events are triggered consistently whether they arrive via real-time webhooks, periodic polling, or manual refreshes.
