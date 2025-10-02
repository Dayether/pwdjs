-- Create normalized table for user accessibility preferences
CREATE TABLE IF NOT EXISTS `user_accessibility_prefs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` varchar(40) NOT NULL,
  `tag` varchar(120) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_tag` (`user_id`,`tag`),
  KEY `idx_tag` (`tag`),
  CONSTRAINT `fk_uap_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
