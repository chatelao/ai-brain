-- Add automations_enabled column to users table
ALTER TABLE users ADD COLUMN automations_enabled BOOLEAN DEFAULT TRUE;
