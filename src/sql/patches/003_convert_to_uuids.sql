-- Migration patch to convert all integer IDs to UUIDs

-- 1. Temporary columns to store old IDs (to map foreign keys)
ALTER TABLE users ADD COLUMN old_user_id INT;
UPDATE users SET old_user_id = user_id;

ALTER TABLE user_github_accounts ADD COLUMN old_github_account_id INT;
UPDATE user_github_accounts SET old_github_account_id = github_account_id;

ALTER TABLE projects ADD COLUMN old_project_id INT;
UPDATE projects SET old_project_id = project_id;

ALTER TABLE tasks ADD COLUMN old_task_id INT;
UPDATE tasks SET old_task_id = task_id;

-- 2. Drop Foreign Keys (MySQL specific)
-- Since we are moving to UUIDs, we need to change column types.
-- In MySQL, we can't change a column type if it's part of a foreign key.
SET FOREIGN_KEY_CHECKS = 0;

-- 3. Change ID columns to CHAR(36) and update values
-- Users
ALTER TABLE users MODIFY user_id CHAR(36);
UPDATE users SET user_id = LOWER(CONCAT(HEX(RANDOM_BYTES(4)), '-', HEX(RANDOM_BYTES(2)), '-4', SUBSTR(HEX(RANDOM_BYTES(2)), 2, 3), '-', HEX(FLOOR(ASCII(RANDOM_BYTES(1)) / 64) + 8), SUBSTR(HEX(RANDOM_BYTES(2)), 2, 3), '-', HEX(RANDOM_BYTES(6))));
-- Note: The above is a rough UUIDv4 generator for MySQL if UUID() is not available or we want to be fancy.
-- But MySQL 8.0+ has UUID(). For older versions it might be harder.
-- Let's use UUID() as it's standard in modern MySQL.
UPDATE users SET user_id = UUID();

-- User GitHub Accounts
ALTER TABLE user_github_accounts MODIFY github_account_id CHAR(36);
ALTER TABLE user_github_accounts MODIFY user_id CHAR(36);
UPDATE user_github_accounts SET github_account_id = UUID();
UPDATE user_github_accounts t SET user_id = (SELECT user_id FROM users u WHERE u.old_user_id = t.user_id);

-- Projects
ALTER TABLE projects MODIFY project_id CHAR(36);
ALTER TABLE projects MODIFY user_id CHAR(36);
ALTER TABLE projects MODIFY github_account_id CHAR(36);
UPDATE projects SET project_id = UUID();
UPDATE projects t SET user_id = (SELECT user_id FROM users u WHERE u.old_user_id = t.user_id);
UPDATE projects t SET github_account_id = (SELECT github_account_id FROM user_github_accounts u WHERE u.old_github_account_id = t.github_account_id);

-- Tasks
ALTER TABLE tasks MODIFY task_id CHAR(36);
ALTER TABLE tasks MODIFY project_id CHAR(36);
UPDATE tasks SET task_id = UUID();
UPDATE tasks t SET project_id = (SELECT project_id FROM projects u WHERE u.old_project_id = t.project_id);

-- Task Logs
ALTER TABLE task_logs MODIFY task_log_id CHAR(36);
ALTER TABLE task_logs MODIFY task_id CHAR(36);
UPDATE task_logs SET task_log_id = UUID();
UPDATE task_logs t SET task_id = (SELECT task_id FROM tasks u WHERE u.old_task_id = t.task_id);

-- User Telegram Accounts
ALTER TABLE user_telegram_accounts MODIFY telegram_account_id CHAR(36);
ALTER TABLE user_telegram_accounts MODIFY user_id CHAR(36);
UPDATE user_telegram_accounts SET telegram_account_id = UUID();
UPDATE user_telegram_accounts t SET user_id = (SELECT user_id FROM users u WHERE u.old_user_id = t.user_id);

-- Issue Templates
ALTER TABLE issue_templates MODIFY issue_template_id CHAR(36);
ALTER TABLE issue_templates MODIFY user_id CHAR(36);
UPDATE issue_templates SET issue_template_id = UUID();
UPDATE issue_templates t SET user_id = (SELECT user_id FROM users u WHERE u.old_user_id = t.user_id);

-- Migrations
ALTER TABLE migrations MODIFY migration_id CHAR(36);
UPDATE migrations SET migration_id = UUID();

-- 4. Clean up old ID columns
ALTER TABLE users DROP COLUMN old_user_id;
ALTER TABLE user_github_accounts DROP COLUMN old_github_account_id;
ALTER TABLE projects DROP COLUMN old_project_id;
ALTER TABLE tasks DROP COLUMN old_task_id;

-- 5. Restore Foreign Keys and Constraints (MySQL)
SET FOREIGN_KEY_CHECKS = 1;
