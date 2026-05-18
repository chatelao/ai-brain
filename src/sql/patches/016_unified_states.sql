-- Migration to unify task states
-- Maps old statuses to the new unified states

-- 1. Update existing tasks
UPDATE tasks SET status = 'QUEUED' WHERE status = 'pending';
UPDATE tasks SET status = 'ANALYZING' WHERE status IN ('analyzed', 'researching');
UPDATE tasks SET status = 'PLANNING' WHERE status = 'planning';
UPDATE tasks SET status = 'EXECUTING' WHERE status IN ('in_progress', 'coding');
UPDATE tasks SET status = 'VERIFYING' WHERE status IN ('testing', 'implemented');
UPDATE tasks SET status = 'FINISHED' WHERE status = 'completed';
UPDATE tasks SET status = 'FAILED' WHERE status IN ('failed', 'failed_jules', 'failed_pr');

-- 2. Update notification settings
-- This is more complex because status names changed. We should migrate the settings if they exist.
INSERT INTO project_status_notification_settings (project_id, status, is_enabled)
SELECT project_id, 'QUEUED', is_enabled FROM project_status_notification_settings WHERE status = 'pending'
ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled);

INSERT INTO project_status_notification_settings (project_id, status, is_enabled)
SELECT project_id, 'ANALYZING', is_enabled FROM project_status_notification_settings WHERE status = 'researching'
ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled);

INSERT INTO project_status_notification_settings (project_id, status, is_enabled)
SELECT project_id, 'PLANNING', is_enabled FROM project_status_notification_settings WHERE status = 'planning'
ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled);

INSERT INTO project_status_notification_settings (project_id, status, is_enabled)
SELECT project_id, 'EXECUTING', is_enabled FROM project_status_notification_settings WHERE status IN ('in_progress', 'coding')
ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled);

INSERT INTO project_status_notification_settings (project_id, status, is_enabled)
SELECT project_id, 'VERIFYING', is_enabled FROM project_status_notification_settings WHERE status IN ('testing', 'implemented')
ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled);

INSERT INTO project_status_notification_settings (project_id, status, is_enabled)
SELECT project_id, 'FINISHED', is_enabled FROM project_status_notification_settings WHERE status = 'completed'
ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled);

INSERT INTO project_status_notification_settings (project_id, status, is_enabled)
SELECT project_id, 'FAILED', is_enabled FROM project_status_notification_settings WHERE status IN ('failed_jules', 'failed_pr')
ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled);

-- Delete old settings
DELETE FROM project_status_notification_settings WHERE status NOT IN ('QUEUED', 'ANALYZING', 'PLANNING', 'EXECUTING', 'VERIFYING', 'FINISHED', 'FAILED');
