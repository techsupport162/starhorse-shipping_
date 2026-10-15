-- =============================================================================
-- Starhorse Shipping Line, Inc. — Database Schema
-- =============================================================================
-- Import this file into phpMyAdmin (or `mysql -u root -p < schema.sql`) to
-- create the database used by BOTH the public website and the admin CMS
-- (starhorse-vessel-admin/).
--
-- Default admin login created below:
--   Username: admin
--   Password: Starhorse@Admin1
--   -> CHANGE THIS PASSWORD IMMEDIATELY AFTER YOUR FIRST LOGIN.
-- =============================================================================

CREATE DATABASE IF NOT EXISTS `starhorse_cms`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `starhorse_cms`;

-- -----------------------------------------------------------------------------
-- admin_users — accounts for starhorse-vessel-admin
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(50)  NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name`     VARCHAR(100) NOT NULL DEFAULT '',
  `role`          ENUM('administrator','editor') NOT NULL DEFAULT 'administrator',
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `last_login_at` DATETIME     NULL DEFAULT NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default admin account — password is "Starhorse@Admin1" (bcrypt hash below)
INSERT INTO `admin_users` (`username`, `password_hash`, `full_name`, `role`)
VALUES (
  'admin',
  '$2b$10$ACs7/pqMZCG0ySL2xZWfw.nTH2LnlAp8wkCvjsk6hN32jssuJRPem',
  'Site Administrator',
  'administrator'
)
ON DUPLICATE KEY UPDATE `username` = `username`;

-- -----------------------------------------------------------------------------
-- vessels — core fleet records, managed via the Vessel module
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vessels` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`           VARCHAR(150)   NOT NULL,
  `length_m`       DECIMAL(8,2)   NULL DEFAULT NULL,
  `breadth_m`      DECIMAL(8,2)   NULL DEFAULT NULL,
  `depth_m`        DECIMAL(8,2)   NULL DEFAULT NULL,
  `gross_tonnage`  DECIMAL(10,2)  NULL DEFAULT NULL,
  `description`    TEXT           NULL DEFAULT NULL,
  `status`         ENUM('published','draft') NOT NULL DEFAULT 'published',
  `sort_order`     INT UNSIGNED   NOT NULL DEFAULT 0,
  `created_at`     DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_vessels_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- vessel_images — gallery images tied to a vessel (1-to-many)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vessel_images` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `vessel_id`   INT UNSIGNED NOT NULL,
  `image`       TEXT         NOT NULL,
  `sort_order`  INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_vessel_images_vessel_id` (`vessel_id`),
  CONSTRAINT `fk_vessel_images_vessel`
    FOREIGN KEY (`vessel_id`) REFERENCES `vessels` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- Sample vessel so the fleet page and admin table aren't empty on first run.
-- Safe to delete from the admin panel once you add your real fleet.
-- -----------------------------------------------------------------------------
INSERT INTO `vessels` (`name`, `length_m`, `breadth_m`, `depth_m`, `gross_tonnage`, `description`, `status`)
VALUES (
  'M/V VIRGEN DE PEÑAFRANCIA I',
  39.00, 7.00, 1.82, 175.00,
  'Flagship inter-island cargo and passenger vessel serving the Bicol–Mindoro route.',
  'published'
);
