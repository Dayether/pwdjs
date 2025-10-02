-- Create table for user search history
CREATE TABLE IF NOT EXISTS search_history (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id VARCHAR(32) NOT NULL,
  query VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_user_query (user_id, query),
  KEY idx_user_created (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
