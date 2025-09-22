-- Migration: Create support_ticket_replies table
-- Date: 2025-09-22
-- Purpose: Store threaded replies (admin or user) for support tickets

START TRANSACTION;

CREATE TABLE IF NOT EXISTS support_ticket_replies (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_id VARCHAR(40) NOT NULL,
  sender_role ENUM('admin','user') NOT NULL,
  sender_user_id VARCHAR(40) NULL,
  message TEXT NOT NULL,
  email_sent TINYINT(1) NOT NULL DEFAULT 0,
  email_error VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (ticket_id),
  CONSTRAINT fk_support_ticket_replies_ticket FOREIGN KEY (ticket_id) REFERENCES support_tickets(ticket_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;
