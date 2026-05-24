# Concept: Task Countdown & Delayed Auto-Repeat

This document analyzes the existing auto-repeat capabilities and proposes a new "Temporal Countdown" mechanism to allow tasks to be repeated $N$ times with a specific time delay between iterations.

## 1. Current State: Iteration Countdown

The system currently supports a count-based "Auto-Repeat" mechanism:

*   **Property**: `autorepeat_remaining` (Integer).
*   **Trigger**: When a task is merged/closed and has the `autorepeat` or `auto-repeat` label.
*   **Behavior**: The system immediately creates a new issue, decrements `autorepeat_remaining`, and adds the `Jules` label to start the agent.
*   **UI**: A pink border is shown around tasks with the autorepeat label, and the remaining count is visible in the Task Detail view.

**Limitation**: There is currently no way to introduce a **delay** between iterations. The next task is created and triggered as soon as the previous one is finished.

## 2. Proposed Feature: Temporal Countdown

The proposal adds a "Temporal Countdown" (Time-based delay) to the auto-repeat logic. This allows users to say: "Repeat this task 5 times, but wait 1 hour between each run."

### 2.1 Database Schema Changes

A new patch `020_add_autorepeat_delay_and_scheduled_at_to_tasks.sql` will be required:

```sql
ALTER TABLE tasks ADD COLUMN autorepeat_delay INT DEFAULT 0; -- Delay in seconds
ALTER TABLE tasks ADD COLUMN scheduled_at DATETIME DEFAULT NULL; -- Future timestamp for execution
```

### 2.2 Backend Logic Enhancements

#### A. `WebhookHandler::maybeDuplicateTask`
When a task is closed with `autorepeat_remaining > 0`:
1.  Calculate `scheduled_at` = `NOW() + autorepeat_delay`.
2.  The new task is created with `status = 'scheduled'` (or a similar waiting state) and the calculated `scheduled_at` timestamp.
3.  The `Jules` label is **not** added yet to prevent immediate execution by the agent.

#### B. `cronjob.php` / `Task::processScheduledTasks`
A new method in `Task.php` will be called by the periodic cron job:
1.  Search for tasks where `status = 'scheduled'` and `scheduled_at <= NOW()`.
2.  For each matching task:
    - Add the `Jules` label to the GitHub issue via `GitHubService`.
    - Update task status to `created` (or trigger agent directly).
    - Clear the `scheduled_at` timestamp.

### 2.3 API Updates (`api/openapi.yaml`)

Update the `Task` schema and relevant endpoints (`/api/task.php`, `/api/tasks.php`):
- Add `autorepeat_delay` (integer).
- Add `scheduled_at` (string, date-time).

### 2.4 Frontend UI Enhancements (Next-Gen UI)

#### A. Task Detail View
- Add an "Auto-Repeat Delay" input field (e.g., minutes or hours) next to the "Auto-Repeat Count".
- When `scheduled_at` is present, display a live countdown timer: "Next run in: 00:45:12".

#### B. Dashboard / AutorepeatTasks Component
- Add a "Scheduled" column or status badge for tasks waiting for their next iteration.
- Show the countdown directly in the "Running Autorepeat Tasks" table.

## 3. Use Case Example

**Scenario**: A user wants to perform a "Daily Security Audit" for 7 days.

1.  **User Sets**:
    - `autorepeat_remaining`: 6 (total 7 runs).
    - `autorepeat_delay`: 86400 (24 hours).
2.  **Day 1**: User triggers the first task.
3.  **Completion**: Task finishes.
4.  **Auto-Repeat**: `WebhookHandler` creates a new task, sets `autorepeat_remaining = 5`, `autorepeat_delay = 86400`, and `scheduled_at = [Current Time + 24h]`.
5.  **Waiting**: The task appears in the UI with a countdown timer.
6.  **Day 2**: Cron job detects `scheduled_at` has passed, adds the `Jules` label, and the agent starts the next audit.
