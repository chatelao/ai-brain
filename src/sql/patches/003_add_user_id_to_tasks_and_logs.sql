-- Add user_id to tasks
ALTER TABLE tasks ADD COLUMN user_id INT;
UPDATE tasks SET user_id = (SELECT user_id FROM projects WHERE projects.project_id = tasks.project_id);
-- In some environments (like SQLite during tests), we might not be able to MODIFY or ADD CONSTRAINT easily.
-- But the MigrationService uses these patches.

-- Add user_id to task_logs
ALTER TABLE task_logs ADD COLUMN user_id INT;
UPDATE task_logs SET user_id = (SELECT user_id FROM tasks WHERE tasks.task_id = task_logs.task_id);
