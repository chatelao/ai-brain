ALTER TABLE tasks ADD COLUMN github_pr_data TEXT;
ALTER TABLE tasks ADD COLUMN github_comments_data TEXT;
ALTER TABLE tasks ADD COLUMN github_data_updated_at TIMESTAMP;

ALTER TABLE projects ADD COLUMN roadmap_data TEXT;
ALTER TABLE projects ADD COLUMN roadmap_updated_at TIMESTAMP;
