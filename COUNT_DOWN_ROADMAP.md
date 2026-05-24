# Roadmap: Task Countdown & Delayed Auto-Repeat

## Progress Overview

| Phase | Description | Status |
|-------|-------------|--------|
| 1 | Database Schema & API | 🌑 |
| 2 | Backend Logic (Scheduling) | 🌑 |
| 3 | UI Integration (Next-Gen) | 🌑 |
| 4 | Verification & Edge Cases | 🌑 |

## Goals

- Allow users to specify a time delay between auto-repeated tasks.
- Provide a clear countdown UI for scheduled tasks.
- Ensure reliable execution of scheduled releases via periodic cron jobs.
- Support manual override (immediate trigger) of scheduled tasks.

## Phase 1: Database Schema & API
- [ ] Create migration `src/sql/020_add_autorepeat_delay_and_scheduled_at_to_tasks.sql`.
- [ ] Update `App\Task` model to support the new columns in `upsert` and `create`.
- [ ] Update `api/openapi.yaml` with `autorepeat_delay` and `scheduled_at` fields.
- [ ] Update `/api/task.php` to expose and accept these new fields.

## Phase 2: Backend Logic (Scheduling)
- [ ] Update `WebhookHandler::maybeDuplicateTask` to calculate `scheduled_at` and set status to `scheduled`.
- [ ] Implement `Task::processScheduledTasks()` to release tasks whose time has come.
- [ ] Integrate `processScheduledTasks()` into `src/frontend/cronjob.php`.
- [ ] Add logging to track scheduling events in `task_logs`.

## Phase 3: UI Integration (Next-Gen)
- [ ] Implement `CountdownTimer` component in `web/src/components/`.
- [ ] Update `TaskDetailView.tsx` with configuration inputs for `autorepeat_delay`.
- [ ] Update `TaskStatusSquare.tsx` or similar to handle the `scheduled` state visually.
- [ ] Add "Trigger Now" action to the task detail and dashboard views for scheduled tasks.
- [ ] Update `AutorepeatTasks` table to show the remaining time until the next run.

## Phase 4: Verification & Edge Cases
- [ ] Test immediate auto-repeat (delay = 0) remains unchanged.
- [ ] Test delayed auto-repeat (delay > 0) correctly sets `scheduled_at`.
- [ ] Verify cron job releases the task and adds the `Jules` label.
- [ ] Test behavior when `autorepeat_remaining` reaches 0 (no further scheduling).
- [ ] Verify that manual label removal on a scheduled task correctly impacts the next iteration.
