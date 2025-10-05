-- 20251004_add_soft_delete_and_skill_flag.sql
-- Non-breaking additions: soft delete + archive + skill active flag
-- Safe to run multiple times (guards) where possible.

-- Users: soft delete column
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL AFTER profile_picture;

-- Jobs: archive column
ALTER TABLE jobs
  ADD COLUMN IF NOT EXISTS archived_at DATETIME NULL AFTER created_at;

-- Skills: active flag
ALTER TABLE skills
  ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER name;

-- Optional index to quickly filter active skills
ALTER TABLE skills
  ADD INDEX IF NOT EXISTS idx_skills_active (is_active);
