-- 20251004_add_used_password_tokens_fk.sql
-- Adds foreign key for used_password_tokens -> users to ensure cleanup on user removal.

-- Ensure column types match; then add constraint if missing.

-- Drop existing orphan tokens referencing non-existent users (prevent FK failure)
DELETE upt FROM used_password_tokens upt
LEFT JOIN users u ON u.user_id = upt.user_id
WHERE u.user_id IS NULL;

-- Add FK if not already present
SET @fk := (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='used_password_tokens' AND REFERENCED_TABLE_NAME='users' LIMIT 1);

SET @sql := IF(@fk IS NULL, 'ALTER TABLE used_password_tokens ADD CONSTRAINT fk_used_password_tokens_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
