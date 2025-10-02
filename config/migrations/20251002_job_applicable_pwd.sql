-- Add applicable PWD categories to jobs
ALTER TABLE jobs
  ADD COLUMN applicable_pwd_types TEXT NULL AFTER accessibility_tags;

-- Index for LIKE/FIND_IN_SET queries (optional, for small text CSV it's fine to skip)
