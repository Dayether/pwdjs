-- Migration: Require non-null business_permit_number for employers
-- Version: 2025-09-21 Full (B1)
-- Purpose: Clean legacy NULL/blank values, preserve uniqueness, enforce NOT NULL.
-- Existing State Observed (from dump):
--   * Column: users.business_permit_number VARCHAR(100) NULL UNIQUE (uniq_business_permit)
--   * Some employers have '' (empty string) or NULL.
-- Plan:
--   1. Diagnostics (optional selects)
--   2. Normalize blanks -> NULL
--   3. Backfill NULL with placeholder pattern MISSING-<last4 user_id>
--   4. (Optional) Uppercase normalization (commented out; enable if desired)
--   5. Shrink length to 64 + NOT NULL (adjust if you prefer to keep 100)
--   6. Retain existing UNIQUE (already enforces uniqueness)
-- Safety: Wrap in transaction (InnoDB). If any step fails, rollback aborts.
-- NOTE: If two users would generate SAME placeholder (rare but possible if last4 collide), second update will fail uniqueness when altering (or earlier if unique already). You may manually fix before rerun.

START TRANSACTION;

-- 1. Diagnostics (you can comment these out after first run)
SELECT 'DIAG_total_employers' AS label, COUNT(*) AS val FROM users WHERE role='employer';
SELECT 'DIAG_null_or_blank' AS label, COUNT(*) AS val FROM users WHERE role='employer' AND (business_permit_number IS NULL OR business_permit_number='' OR business_permit_number=' ');
SELECT user_id, company_name, business_permit_number FROM users WHERE role='employer' AND (business_permit_number IS NULL OR business_permit_number='' OR business_permit_number=' ') LIMIT 25;

-- 2. Normalize blanks to NULL
UPDATE users
  SET business_permit_number = NULL
  WHERE role='employer' AND (business_permit_number='' OR business_permit_number=' ');

-- 3. Backfill NULL with placeholder values
-- Placeholder design: MISSING- + last 4 chars of user_id (ensures length < 64, traceable)
-- If collision with existing real permit, adjust manually.
UPDATE users
  SET business_permit_number = CONCAT('MISSING-', RIGHT(user_id,4))
  WHERE role='employer' AND business_permit_number IS NULL;

-- 4. (Optional) Normalize to uppercase (uncomment if you want uniform case)
-- UPDATE users SET business_permit_number = UPPER(business_permit_number) WHERE role='employer';

-- 5. Enforce NOT NULL + adjust length (to 64) – keep UNIQUE key as-is
ALTER TABLE users
  MODIFY business_permit_number VARCHAR(64) NOT NULL;

-- 6. (Optional) Add CHECK pattern (MariaDB 10.4 supports but not enforced strictly earlier)
-- ALTER TABLE users
--   ADD CONSTRAINT chk_business_permit_format CHECK (business_permit_number REGEXP '^[A-Za-z0-9\-\/]{4,64}$');

-- Post diagnostics
SELECT 'DIAG_after_nulls' AS label, COUNT(*) AS val FROM users WHERE role='employer' AND (business_permit_number IS NULL OR business_permit_number='');

COMMIT;

-- Rollback (manual):
-- START TRANSACTION;
-- ALTER TABLE users MODIFY business_permit_number VARCHAR(100) NULL;
-- ALTER TABLE users DROP CHECK chk_business_permit_format; -- if added
-- Optionally revert placeholders: UPDATE users SET business_permit_number=NULL WHERE business_permit_number LIKE 'MISSING-%';
-- COMMIT;
