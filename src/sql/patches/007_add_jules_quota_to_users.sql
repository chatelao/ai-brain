ALTER TABLE users ADD COLUMN jules_quota_usage INT DEFAULT 0;
ALTER TABLE users ADD COLUMN jules_quota_limit INT DEFAULT 0;
ALTER TABLE users ADD COLUMN jules_quota_updated_at DATETIME;
