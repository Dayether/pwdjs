-- Migration: Add extra employer validation fields
-- Adds company owner and contact person details for stricter employer verification.
-- Safe to run multiple times (IF NOT EXISTS guards) on MySQL 8.0+.

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS company_owner_name VARCHAR(150) NULL AFTER employer_doc,
  ADD COLUMN IF NOT EXISTS contact_person_position VARCHAR(120) NULL AFTER company_owner_name,
  ADD COLUMN IF NOT EXISTS contact_person_phone VARCHAR(40) NULL AFTER contact_person_position;
