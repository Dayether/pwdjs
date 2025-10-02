-- Add job seeker preference fields to users table
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `expected_salary_currency` varchar(8) DEFAULT 'PHP' AFTER `video_intro`,
  ADD COLUMN IF NOT EXISTS `expected_salary_min` int(11) DEFAULT NULL AFTER `expected_salary_currency`,
  ADD COLUMN IF NOT EXISTS `expected_salary_max` int(11) DEFAULT NULL AFTER `expected_salary_min`,
  ADD COLUMN IF NOT EXISTS `expected_salary_period` enum('monthly','yearly','hourly') DEFAULT 'monthly' AFTER `expected_salary_max`,
  ADD COLUMN IF NOT EXISTS `interests` text DEFAULT NULL AFTER `expected_salary_period`,
  ADD COLUMN IF NOT EXISTS `accessibility_preferences` varchar(255) DEFAULT NULL AFTER `interests`,
  ADD COLUMN IF NOT EXISTS `preferred_location` varchar(255) DEFAULT NULL AFTER `accessibility_preferences`,
  ADD COLUMN IF NOT EXISTS `preferred_work_setup` enum('On-site','Hybrid','Remote') DEFAULT NULL AFTER `preferred_location`;
