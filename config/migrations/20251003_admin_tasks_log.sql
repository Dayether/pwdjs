-- Admin tasks audit log
CREATE TABLE IF NOT EXISTS admin_tasks_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  task VARCHAR(100) NOT NULL,
  actor_user_id VARCHAR(40) NOT NULL,
  mode VARCHAR(20) NOT NULL,
  users_scanned INT NOT NULL DEFAULT 0,
  users_updated INT NOT NULL DEFAULT 0,
  jobs_scanned INT NOT NULL DEFAULT 0,
  jobs_updated INT NOT NULL DEFAULT 0,
  details TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;