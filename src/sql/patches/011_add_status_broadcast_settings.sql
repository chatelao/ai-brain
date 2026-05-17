-- SQL Patch: Add status broadcast settings
-- This table stores which task statuses should trigger a broadcast (Telegram, Browser)
-- while ensuring all events still appear in the in-app event list.

CREATE TABLE IF NOT EXISTS project_status_notification_settings (
    project_id INT NOT NULL,
    status VARCHAR(50) NOT NULL,
    is_enabled BOOLEAN DEFAULT TRUE,
    PRIMARY KEY (project_id, status),
    FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
