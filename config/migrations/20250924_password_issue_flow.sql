-- Migration: Initial password issuance flow
-- Date: 2025-09-24

START TRANSACTION;

-- Allow NULL passwords until admin issues them
ALTER TABLE users
  MODIFY COLUMN `password` VARCHAR(255) NULL;

-- Track uniqueness of issued initial passwords without storing plaintext
CREATE TABLE IF NOT EXISTS used_password_tokens (
  token CHAR(64) NOT NULL PRIMARY KEY,
  user_id VARCHAR(40) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;
