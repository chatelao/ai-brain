-- Add blockly_config column to users and projects tables
ALTER TABLE users ADD COLUMN blockly_config TEXT;
ALTER TABLE projects ADD COLUMN blockly_config TEXT;
