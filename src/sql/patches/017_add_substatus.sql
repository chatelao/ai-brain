-- Migration to add substatus and unify states
ALTER TABLE tasks ADD COLUMN substatus VARCHAR(50) AFTER status;

-- Migrate existing tasks to the new model
UPDATE tasks SET substatus = status;
UPDATE tasks SET status = 'CREATED' WHERE substatus = 'QUEUED';
UPDATE tasks SET status = 'PROCESSING' WHERE substatus IN ('ANALYZING', 'PLANNING', 'EXECUTING', 'VERIFYING');
UPDATE tasks SET status = 'FINISHED' WHERE substatus = 'FINISHED';
UPDATE tasks SET status = 'FAILED' WHERE substatus = 'FAILED';

-- Further refine substatuses if possible (heuristic)
UPDATE tasks SET substatus = 'JULES_FAILED' WHERE status = 'FAILED' AND jules_status IN ('failed', 'error');
UPDATE tasks SET substatus = 'PR_FAILED' WHERE status = 'FAILED' AND substatus != 'JULES_FAILED';

-- Update notification settings (broadcast filters)
-- For now, we will use the substatus for broadcast filters to maintain granularity if needed,
-- but the backend logic will be updated to check both.
INSERT INTO project_status_notification_settings (project_id, status, is_enabled)
SELECT project_id, 'CREATED', is_enabled FROM project_status_notification_settings WHERE status = 'QUEUED'
ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled);

INSERT INTO project_status_notification_settings (project_id, status, is_enabled)
SELECT project_id, 'PROCESSING', is_enabled FROM project_status_notification_settings WHERE status = 'ANALYZING'
ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled);

INSERT INTO project_status_notification_settings (project_id, status, is_enabled)
SELECT project_id, 'JULES_FAILED', is_enabled FROM project_status_notification_settings WHERE status = 'FAILED'
ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled);

INSERT INTO project_status_notification_settings (project_id, status, is_enabled)
SELECT project_id, 'PR_FAILED', is_enabled FROM project_status_notification_settings WHERE status = 'FAILED'
ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled);
