-- Migration to add task_external_peers table and substatus column
CREATE TABLE task_external_peers (
    peer_id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    source VARCHAR(50) NOT NULL,
    id VARCHAR(255) NOT NULL,
    state VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE(task_id, source, id),
    FOREIGN KEY (task_id) REFERENCES tasks(task_id) ON DELETE CASCADE
);

ALTER TABLE tasks ADD COLUMN substatus VARCHAR(50) AFTER status;

-- Initial data migration from existing task columns
INSERT INTO task_external_peers (task_id, source, id, state)
SELECT task_id, 'GH.Issue', issue_number, github_state FROM tasks WHERE issue_number IS NOT NULL;

INSERT INTO task_external_peers (task_id, source, id, state)
SELECT task_id, 'Jules.Session', jules_session_id, jules_status FROM tasks WHERE jules_session_id IS NOT NULL AND jules_session_id != '';

INSERT INTO task_external_peers (task_id, source, id, state)
SELECT task_id, 'GH.PullRequest', pr_url, 'open' FROM tasks WHERE pr_url IS NOT NULL AND pr_url != '';

-- Setup initial status/substatus
UPDATE tasks SET status = 'CREATED', substatus = NULL;

-- Simple broadcast migration
INSERT INTO project_status_notification_settings (project_id, status, is_enabled)
SELECT DISTINCT project_id, 'CREATED', 1 FROM projects
ON DUPLICATE KEY UPDATE is_enabled = 1;

INSERT INTO project_status_notification_settings (project_id, status, is_enabled)
SELECT DISTINCT project_id, 'PROCESSING', 1 FROM projects
ON DUPLICATE KEY UPDATE is_enabled = 1;

INSERT INTO project_status_notification_settings (project_id, status, is_enabled)
SELECT DISTINCT project_id, 'FINISHED', 1 FROM projects
ON DUPLICATE KEY UPDATE is_enabled = 1;

INSERT INTO project_status_notification_settings (project_id, status, is_enabled)
SELECT DISTINCT project_id, 'FAILED', 1 FROM projects
ON DUPLICATE KEY UPDATE is_enabled = 1;
