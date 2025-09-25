-- Migration: Add job_image to jobs for thumbnail/banner
-- Date: 2025-09-25

START TRANSACTION;

ALTER TABLE jobs
  ADD COLUMN job_image VARCHAR(255) NULL AFTER salary_period;

COMMIT;
