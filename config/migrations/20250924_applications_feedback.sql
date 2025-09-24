-- Migration: Employer feedback on application approval
-- Date: 2025-09-24

START TRANSACTION;

ALTER TABLE applications
  ADD COLUMN employer_feedback TEXT NULL AFTER status,
  ADD COLUMN decision_at TIMESTAMP NULL DEFAULT NULL AFTER employer_feedback;

COMMIT;
