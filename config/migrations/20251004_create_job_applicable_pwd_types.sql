-- 20251004_create_job_applicable_pwd_types.sql
-- Normalizes jobs.applicable_pwd_types (single-value phase) into a mapping table.
-- Future-proof for multi-valued entries (comma-separated) if later introduced.

CREATE TABLE IF NOT EXISTS job_applicable_pwd_types (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  job_id VARCHAR(40) NOT NULL,
  pwd_type VARCHAR(120) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_job_type (job_id, pwd_type),
  KEY idx_type (pwd_type),
  CONSTRAINT fk_job_pwd_type_job FOREIGN KEY (job_id) REFERENCES jobs(job_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Backfill: parse existing values (assumes either NULL or single value OR comma-separated future-safe).
INSERT IGNORE INTO job_applicable_pwd_types (job_id, pwd_type)
SELECT j.job_id, TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(t.val, ',', n.n), ',', -1)) AS pwd_type
FROM jobs j
JOIN (
  SELECT job_id, COALESCE(applicable_pwd_types,'') AS val FROM jobs WHERE applicable_pwd_types IS NOT NULL AND applicable_pwd_types <> ''
) t ON t.job_id = j.job_id
JOIN (
  SELECT a.N+ b.N * 10 + 1 n
  FROM (SELECT 0 N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
        UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) a,
       (SELECT 0 N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
        UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) b
) n ON n.n <= 1 + LENGTH(t.val) - LENGTH(REPLACE(t.val, ',', ''))
WHERE TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(t.val, ',', n.n), ',', -1)) <> '';

-- (Optional) Keep old column for now (non-destructive). A later migration can drop it once code is updated.
