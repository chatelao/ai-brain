# Concept: State-of-the-Art Logging & Performance Monitoring

## Overview
To ensure reliability and maintainability, this application implements a robust logging strategy. This includes traditional event logging and automated performance monitoring for database and external API interactions.

## Logging Levels
The application uses the following standard logging levels:
- **DEBUG**: Detailed information, typically of interest only when diagnosing problems.
- **INFO**: Confirmation that things are working as expected.
- **WARNING**: An indication that something unexpected happened, or indicative of some problem in the near future (e.g., 'disk space low'). The software is still working as expected.
- **ERROR**: Due to a more serious problem, the software has not been able to perform some function.
- **CRITICAL**: A serious error, indicating that the program itself may be unable to continue running.

## Performance Monitoring
The application automatically monitors the execution duration of critical components.

### 1. Database Performance
All database queries executed through the `Database` class are timed. Any query taking longer than **1.0 second** is automatically logged to the `performance_logs` table.

### 2. External API Performance
All external API requests (e.g., GitHub REST API, Google Jules API) are timed. Any request with a duration exceeding **1.0 second** is automatically logged to the `performance_logs` table.

## Data Storage
### performance_logs Table
Performance data is stored in the `performance_logs` table with the following schema:
- `performance_log_id`: Unique identifier for the log entry.
- `user_id`: The ID of the user whose action triggered the event (if applicable).
- `type`: The category of the event (e.g., 'DB', 'GitHub API', 'Jules API').
- `target`: The specific query or endpoint being accessed.
- `duration`: The time taken in seconds (float).
- `context`: Additional JSON data (e.g., parameters, headers, status codes).
- `created_at`: Timestamp of the log entry.

## Implementation Details
- **Database**: Instrumented using a custom `PDOStatement` decorator (`TimedPDOStatement`).
- **GitHub API**: Instrumented by wrapping `php-github-api` calls with timing logic in `GitHubService`.
- **Jules API**: Instrumented using a Guzzle middleware in `JulesService`.
- **Logger**: A centralized `Logger` class provides the `logPerformance` method and handles database persistence.
