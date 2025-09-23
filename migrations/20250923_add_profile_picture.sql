-- Adds profile_picture column to users table
ALTER TABLE `users`
  ADD COLUMN `profile_picture` VARCHAR(255) NULL AFTER `employer_doc`;
