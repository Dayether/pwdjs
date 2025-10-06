-- 20251006_add_status_reasons.sql
-- Adds columns to store last admin-provided reasons for status changes.
-- Run this after previous migrations.

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS last_status_reason TEXT NULL AFTER job_seeker_status;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS last_suspension_reason TEXT NULL AFTER last_status_reason;

-- Optional: view sample
-- SELECT user_id, role, employer_status, job_seeker_status, last_status_reason, last_suspension_reason FROM users LIMIT 20;
