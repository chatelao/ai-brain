ALTER TABLE users RENAME COLUMN id TO user_id;
ALTER TABLE user_github_accounts RENAME COLUMN id TO github_account_id;
ALTER TABLE projects RENAME COLUMN id TO project_id;
ALTER TABLE tasks RENAME COLUMN id TO task_id;
ALTER TABLE task_logs RENAME COLUMN id TO task_log_id;
ALTER TABLE user_telegram_accounts RENAME COLUMN id TO telegram_account_id;
ALTER TABLE issue_templates RENAME COLUMN id TO issue_template_id;
ALTER TABLE migrations RENAME COLUMN id TO migration_id;
