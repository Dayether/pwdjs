-- 20251004_alter_job_reports_reporter_fk.sql
-- Change reporter_user_id FK from CASCADE to SET NULL to preserve reports after user deletion.

-- Ensure reporter_user_id is nullable (if not already)
ALTER TABLE job_reports
  MODIFY reporter_user_id VARCHAR(40) NULL;

-- Detect existing FK name
SET @fk_name := (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='job_reports' AND COLUMN_NAME='reporter_user_id' AND REFERENCED_TABLE_NAME='users' LIMIT 1);

-- Drop FK if exists
SET @sql_drop := IF(@fk_name IS NOT NULL, CONCAT('ALTER TABLE job_reports DROP FOREIGN KEY ', @fk_name), 'DO 0');
PREPARE stmt FROM @sql_drop; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Recreate FK with SET NULL if not already recreated
ALTER TABLE job_reports
  ADD CONSTRAINT fk_job_reports_reporter_user FOREIGN KEY (reporter_user_id) REFERENCES users(user_id) ON DELETE SET NULL;
