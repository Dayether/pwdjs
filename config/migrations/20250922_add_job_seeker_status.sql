-- Migration: Add job_seeker_status column to users
-- Run this manually (e.g., via phpMyAdmin or mysql CLI) before using the suspend feature.

ALTER TABLE users
  ADD COLUMN job_seeker_status ENUM('Active','Suspended') NOT NULL DEFAULT 'Active' AFTER pwd_id_status;

-- If ENUM not preferred, you can alternatively use (uncomment and adjust):
-- ALTER TABLE users ADD COLUMN job_seeker_status VARCHAR(20) NOT NULL DEFAULT 'Active' AFTER pwd_id_status;

-- Verification query (optional):
-- SELECT user_id, role, job_seeker_status FROM users WHERE role='job_seeker' LIMIT 20;
