-- =============================================================================
-- Migration 002 — upgrade admin_users → users
-- =============================================================================
-- Run this ONLY if you already imported the original schema.sql (the one
-- that created an `admin_users` table) before the User Management module
-- was added. If you're setting up the database for the first time, just
-- import schema.sql — it already creates the new `users` table directly
-- and you do NOT need this file.
--
-- What this does:
--   1. Renames admin_users -> users
--   2. Renames password_hash -> password, role -> user_access
--   3. Adds profile_image, email, contact_number, status
--   4. Maps the old 'editor' role to 'user' and is_active to status
--
-- Back up your database before running this.
-- =============================================================================
USE `starhorse_cms`;

ALTER TABLE `admin_users` RENAME TO `users`;

ALTER TABLE `users`
  CHANGE COLUMN `password_hash` `password` VARCHAR(255) NOT NULL,
  CHANGE COLUMN `role` `user_access` ENUM('administrator','user') NOT NULL DEFAULT 'user',
  ADD COLUMN `profile_image` VARCHAR(255) NULL DEFAULT NULL AFTER `id`,
  ADD COLUMN `email` VARCHAR(150) NOT NULL DEFAULT '' AFTER `full_name`,
  ADD COLUMN `contact_number` VARCHAR(30) NULL DEFAULT NULL AFTER `email`,
  ADD COLUMN `status` ENUM('active','inactive') NOT NULL DEFAULT 'active' AFTER `contact_number`;

-- Old schema didn't require an email; backfill a placeholder so the new
-- UNIQUE(email) constraint doesn't fail. Update this manually afterward.
UPDATE `users` SET `email` = CONCAT(`username`, '@starhorseshipping.com') WHERE `email` = '';

-- Map the retired 'editor' role to the new 'user' role.
UPDATE `users` SET `user_access` = 'user' WHERE `user_access` NOT IN ('administrator', 'user');

-- Map old is_active (1/0) to the new status enum, then drop the old column.
UPDATE `users` SET `status` = IF(`is_active` = 1, 'active', 'inactive');
ALTER TABLE `users` DROP COLUMN `is_active`;

ALTER TABLE `users`
  ADD UNIQUE KEY `uq_users_email` (`email`);

ALTER TABLE `users`
  DROP INDEX `uq_admin_users_username`,
  ADD UNIQUE KEY `uq_users_username` (`username`);
