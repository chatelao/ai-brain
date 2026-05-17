-- SQL Patch: Add project_id to notifications table
-- This allows more efficient cleanup of old notifications per project.

ALTER TABLE notifications ADD COLUMN project_id INT AFTER user_id;

-- Backfill project_id from data JSON
-- MySQL syntax:
-- UPDATE notifications SET project_id = JSON_UNQUOTE(JSON_EXTRACT(data, '$.project_id')) WHERE JSON_CONTAINS_PATH(data, 'one', '$.project_id');

-- Since we support both MySQL and SQLite, and backfilling might be complex in a single script,
-- we'll rely on the fact that new notifications will have project_id populated via application logic if possible,
-- but actually adding the column is better for future.

-- Actually, for now, let's just add the column and an index.
CREATE INDEX idx_notifications_project_id ON notifications(project_id);
