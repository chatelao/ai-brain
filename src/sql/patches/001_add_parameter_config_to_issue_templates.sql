-- Add parameter_config column to issue_templates
ALTER TABLE issue_templates ADD COLUMN parameter_config JSON AFTER body_template;
