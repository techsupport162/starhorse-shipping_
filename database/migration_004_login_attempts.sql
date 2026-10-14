-- =============================================================================
-- Migration 004 — admin login throttling
-- -----------------------------------------------------------------------------
-- Records failed admin sign-ins so login.php can temporarily block repeated
-- password guessing (see functions/login_throttle_functions.php). Rows are
-- pruned automatically; nothing here needs manual maintenance.
--
-- Run ONCE in phpMyAdmin: select the `starhorse_cms` database → Import → Go.
-- =============================================================================

USE `starhorse_cms`;

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`     VARCHAR(100) NOT NULL,
  `ip_address`   VARCHAR(45)  NOT NULL,
  `attempted_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_login_attempts_ip_time` (`ip_address`, `attempted_at`),
  KEY `idx_login_attempts_user_ip_time` (`username`, `ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
