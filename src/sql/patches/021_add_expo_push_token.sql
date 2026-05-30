-- Add Expo Push Token to users table for native mobile notifications
ALTER TABLE users ADD COLUMN expo_push_token VARCHAR(255) DEFAULT NULL;
