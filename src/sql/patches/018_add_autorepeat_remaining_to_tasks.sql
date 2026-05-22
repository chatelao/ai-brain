-- Add autorepeat_remaining column to tasks table
ALTER TABLE tasks ADD COLUMN autorepeat_remaining INT DEFAULT 0;
