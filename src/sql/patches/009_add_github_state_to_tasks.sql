ALTER TABLE tasks ADD COLUMN github_state VARCHAR(20) DEFAULT 'open';
CREATE INDEX idx_tasks_github_state ON tasks(github_state);
CREATE INDEX idx_tasks_project_id ON tasks(project_id);
CREATE INDEX idx_tasks_user_id ON tasks(user_id);
