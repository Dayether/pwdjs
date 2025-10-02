-- Reviews for employers/companies
CREATE TABLE IF NOT EXISTS employer_reviews (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  employer_id VARCHAR(32) NOT NULL,
  reviewer_user_id VARCHAR(32) NULL,
  rating TINYINT UNSIGNED NOT NULL CHECK (rating BETWEEN 1 AND 5),
  comment TEXT NULL,
  status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Approved',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_employer_created (employer_id, created_at),
  KEY idx_employer_status (employer_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;