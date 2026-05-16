ALTER TABLE tasks ADD COLUMN jules_token VARCHAR(255);
CREATE UNIQUE INDEX idx_tasks_jules_token ON tasks(jules_token);
