-- SQL Patch: Add user status notification settings
-- This table stores global user preferences for which task statuses should trigger a notification.

CREATE TABLE IF NOT EXISTS user_status_notification_settings (
    user_id INT NOT NULL,
    status VARCHAR(50) NOT NULL,
    is_enabled BOOLEAN DEFAULT TRUE,
    PRIMARY KEY (user_id, status),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
