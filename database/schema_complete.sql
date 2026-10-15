-- =============================================================================
-- Starhorse Shipping Line, Inc. — Complete Database Schema
-- =============================================================================
-- Use this file for ALL new deployments (local or production).
-- It replaces schema.sql + all migrations in a single import.
--
-- Import via phpMyAdmin:  Database → Import → choose this file → Go
-- Import via CLI:         mysql -u <user> -p <dbname> < schema_complete.sql
-- Import on Render/cloud: paste into your DB host's SQL console
--
-- Default admin login created below:
--   Username : admin
--   Password : Starhorse@Admin1
--   ⚠ CHANGE THIS PASSWORD immediately after your first login.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================================
-- USERS — admin panel accounts
-- =============================================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `profile_image`  TEXT          NULL     DEFAULT NULL,
  `username`       VARCHAR(50)   NOT NULL,
  `password`       VARCHAR(255)  NOT NULL,
  `full_name`      VARCHAR(100)  NOT NULL DEFAULT '',
  `email`          VARCHAR(150)  NOT NULL DEFAULT '',
  `contact_number` VARCHAR(30)   NULL     DEFAULT NULL,
  `status`         ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `user_access`    ENUM('administrator','user') NOT NULL DEFAULT 'user',
  `last_login_at`  DATETIME      NULL     DEFAULT NULL,
  `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email`    (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Default administrator — password: Starhorse@Admin1 (bcrypt)
INSERT INTO `users` (`username`, `password`, `full_name`, `email`, `status`, `user_access`)
VALUES (
  'admin',
  '$2b$10$ACs7/pqMZCG0ySL2xZWfw.nTH2LnlAp8wkCvjsk6hN32jssuJRPem',
  'Site Administrator',
  'admin@starhorseshipping.com',
  'active',
  'administrator'
) ON DUPLICATE KEY UPDATE `username` = `username`;

-- =============================================================================
-- LOGIN ATTEMPTS — brute-force throttling
-- =============================================================================
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`     VARCHAR(100) NOT NULL,
  `ip_address`   VARCHAR(45)  NOT NULL,
  `attempted_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_login_attempts_ip_time`       (`ip_address`, `attempted_at`),
  KEY `idx_login_attempts_user_ip_time`  (`username`, `ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- VESSELS
-- =============================================================================
CREATE TABLE IF NOT EXISTS `vessels` (
  `id`            INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(150)   NOT NULL,
  `tagline`       VARCHAR(100)   NULL DEFAULT NULL,
  `length_m`      DECIMAL(8,2)   NULL DEFAULT NULL,
  `breadth_m`     DECIMAL(8,2)   NULL DEFAULT NULL,
  `depth_m`       DECIMAL(8,2)   NULL DEFAULT NULL,
  `gross_tonnage` DECIMAL(10,2)  NULL DEFAULT NULL,
  `description`   TEXT           NULL DEFAULT NULL,
  `status`        ENUM('published','draft') NOT NULL DEFAULT 'published',
  `sort_order`    INT UNSIGNED   NOT NULL DEFAULT 0,
  `created_at`    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_vessels_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================================================
-- VESSEL IMAGES — gallery photos (1 vessel : many images)
-- =============================================================================
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================================================
-- VESSEL FEATURES — reusable feature labels (e.g. "Air-conditioned cabin")
-- =============================================================================
CREATE TABLE IF NOT EXISTS `vessel_features` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `icon`       VARCHAR(50)  NOT NULL DEFAULT 'bi-check2-circle',
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================================================
-- VESSEL FEATURE ASSIGNMENTS — which features belong to which vessel
-- =============================================================================
CREATE TABLE IF NOT EXISTS `vessel_feature_assignments` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `vessel_id`  INT UNSIGNED NOT NULL,
  `feature_id` INT UNSIGNED NOT NULL,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_vfa_vessel`  (`vessel_id`),
  KEY `idx_vfa_feature` (`feature_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================================================
-- FARES
-- =============================================================================
CREATE TABLE IF NOT EXISTS `fares` (
  `id`                   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `origin`               VARCHAR(100)  NOT NULL,
  `destination`          VARCHAR(100)  NOT NULL,
  `passenger_fare_image` TEXT          NULL DEFAULT NULL,
  `cargo_fare_image`     TEXT          NULL DEFAULT NULL,
  `is_vice_versa`        TINYINT(1)    NOT NULL DEFAULT 0,
  `status`               ENUM('published','draft') NOT NULL DEFAULT 'published',
  `sort_order`           INT UNSIGNED  NOT NULL DEFAULT 0,
  `created_at`           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fares_route` (`origin`, `destination`),
  KEY `idx_fares_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================================================
-- FAQS
-- =============================================================================
CREATE TABLE IF NOT EXISTS `faqs` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `question`   VARCHAR(255) NOT NULL,
  `answer`     TEXT         NOT NULL,
  `status`     ENUM('published','draft') NOT NULL DEFAULT 'published',
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_faqs_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================================================
-- ROUTE BANNER — single-row banner image for the Routes section
-- =============================================================================
CREATE TABLE IF NOT EXISTS `route_banner` (
  `id`           TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `banner_image` TEXT             NULL DEFAULT NULL,
  `updated_at`   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `route_banner` (`id`) VALUES (1)
  ON DUPLICATE KEY UPDATE `id` = `id`;

-- =============================================================================
-- ROUTE DESTINATIONS
-- =============================================================================
CREATE TABLE IF NOT EXISTS `route_destinations` (
  `id`                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `location_name`     VARCHAR(150)  NOT NULL,
  `destination_image` TEXT          NULL DEFAULT NULL,
  `status`            ENUM('published','draft') NOT NULL DEFAULT 'published',
  `sort_order`        INT UNSIGNED  NOT NULL DEFAULT 0,
  `created_at`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_route_destinations_location` (`location_name`),
  KEY `idx_route_destinations_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================================================
-- CONTACT SETTINGS — single-row head-office details
-- =============================================================================
CREATE TABLE IF NOT EXISTS `contact_settings` (
  `id`            TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `office_name`   VARCHAR(150)     NOT NULL DEFAULT 'Head Office',
  `address`       VARCHAR(255)     NOT NULL DEFAULT '',
  `tel_number`    VARCHAR(50)      NULL DEFAULT NULL,
  `facebook_name` VARCHAR(150)     NULL DEFAULT NULL,
  `facebook_url`  VARCHAR(255)     NULL DEFAULT NULL,
  `map_embed_url` TEXT             NULL DEFAULT NULL,
  `map_status`    ENUM('published','draft') NOT NULL DEFAULT 'published',
  `status`        ENUM('published','draft') NOT NULL DEFAULT 'published',
  `updated_at`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Default row so the Contact section always has something to show
INSERT INTO `contact_settings` (`id`, `office_name`) VALUES (1, 'Head Office')
  ON DUPLICATE KEY UPDATE `id` = `id`;

-- =============================================================================
-- CONTACT EMAILS
-- =============================================================================
CREATE TABLE IF NOT EXISTS `contact_emails` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`      VARCHAR(150) NOT NULL,
  `label`      VARCHAR(50)  NULL DEFAULT NULL COMMENT 'Admin-only label, e.g. "HR", "Accounting"',
  `status`     ENUM('published','draft') NOT NULL DEFAULT 'published',
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================================================
-- CONTACT PHONES
-- =============================================================================
CREATE TABLE IF NOT EXISTS `contact_phones` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `phone_number` VARCHAR(30)  NOT NULL,
  `label`        VARCHAR(50)  NULL DEFAULT NULL COMMENT 'Admin-only label, e.g. "Main", "Dispatch"',
  `status`       ENUM('published','draft') NOT NULL DEFAULT 'published',
  `sort_order`   INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================================================
-- CONTACT PORTS — port-level phone numbers
-- =============================================================================
CREATE TABLE IF NOT EXISTS `contact_ports` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `port_name`    VARCHAR(150) NOT NULL,
  `phone_number` VARCHAR(30)  NOT NULL,
  `status`       ENUM('published','draft') NOT NULL DEFAULT 'published',
  `sort_order`   INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================================================
-- CAREER POSITIONS — position types (e.g. Deck Officer, Engineer)
-- =============================================================================
CREATE TABLE IF NOT EXISTS `career_positions` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_career_positions_name` (`name`),
  KEY `idx_career_positions_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================================================
-- CAREER POSTINGS — individual job ads
-- =============================================================================
CREATE TABLE IF NOT EXISTS `career_postings` (
  `id`                       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `position_id`              INT UNSIGNED  NOT NULL,
  `title`                    VARCHAR(150)  NOT NULL,
  `short_description`        VARCHAR(255)  NULL DEFAULT NULL,
  `job_description`          TEXT          NULL DEFAULT NULL,
  `qualifications`           TEXT          NULL DEFAULT NULL,
  `responsibilities`         TEXT          NULL DEFAULT NULL,
  `application_instructions` TEXT          NULL DEFAULT NULL,
  `contact_email`            VARCHAR(150)  NULL DEFAULT NULL,
  `recruitment_image`        TEXT          NULL DEFAULT NULL,
  `status`                   ENUM('published','draft') NOT NULL DEFAULT 'draft',
  `sort_order`               INT UNSIGNED  NOT NULL DEFAULT 0,
  `created_at`               DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`               DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_career_postings_status`      (`status`),
  KEY `idx_career_postings_position_id` (`position_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================================================
-- CAREER APPLICANTS — submitted applications
-- =============================================================================
CREATE TABLE IF NOT EXISTS `career_applicants` (
  `id`                     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `posting_id`             INT UNSIGNED  NULL DEFAULT NULL,
  `applied_position_title` VARCHAR(150)  NOT NULL,
  `full_name`              VARCHAR(150)  NOT NULL,
  `email`                  VARCHAR(150)  NOT NULL,
  `contact_number`         VARCHAR(30)   NOT NULL,
  `address`                VARCHAR(255)  NULL DEFAULT NULL,
  `message`                TEXT          NULL DEFAULT NULL,
  `resume_file`            VARCHAR(255)  NULL DEFAULT NULL,
  `status`                 ENUM('new','reviewed','shortlisted','interview','accepted','rejected') NOT NULL DEFAULT 'new',
  `applied_at`             DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`             DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_career_applicants_status`     (`status`),
  KEY `idx_career_applicants_posting_id` (`posting_id`),
  KEY `idx_career_applicants_applied_at` (`applied_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS = 1;
