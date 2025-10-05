-- 20251004_add_expected_salary_index.sql
-- Adds composite index for user expected salary range filtering.

ALTER TABLE users
  ADD INDEX IF NOT EXISTS idx_expected_salary (expected_salary_min, expected_salary_max, expected_salary_period);
