-- Migration: Add moderation workflow columns to jobs table
-- Date: 2025-10-09
-- Adds columns for admin review of job postings.

ALTER TABLE jobs
  ADD COLUMN IF NOT EXISTS moderation_status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending' AFTER status,
  ADD COLUMN IF NOT EXISTS moderation_reason TEXT NULL AFTER moderation_status,
  ADD COLUMN IF NOT EXISTS moderation_decided_at DATETIME NULL AFTER moderation_reason,
  ADD COLUMN IF NOT EXISTS moderation_decided_by VARCHAR(64) NULL AFTER moderation_decided_at;

-- Backfill existing rows: mark all current jobs as Approved if still default Pending (legacy rows prior to moderation introduction)
UPDATE jobs SET moderation_status='Approved' WHERE moderation_status='Pending';
