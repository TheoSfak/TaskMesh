-- Migration: Add notify_subtask_created column to user_email_preferences
-- Run this if you already have the user_email_preferences table

USE taskmesh_db;

-- Add the new column after notify_task_completed
ALTER TABLE user_email_preferences 
ADD COLUMN notify_subtask_created BOOLEAN DEFAULT TRUE 
AFTER notify_task_completed;

-- Update existing records to enable it by default
UPDATE user_email_preferences 
SET notify_subtask_created = TRUE 
WHERE notify_subtask_created IS NULL;

SELECT 'Migration completed successfully! Added notify_subtask_created column.' as status;
