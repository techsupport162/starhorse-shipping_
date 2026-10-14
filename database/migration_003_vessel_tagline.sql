-- =============================================================================
-- Migration 003 — per-vessel carousel label
-- -----------------------------------------------------------------------------
-- Adds `vessels.tagline`: the small label shown above each vessel's name in
-- the public "Our Vessels" carousel (e.g. "Our Flagship Vessel"). Vessels
-- with no tagline fall back to "Our Fleet".
--
-- Run ONCE in phpMyAdmin: select the `starhorse_cms` database → Import → Go.
-- =============================================================================

USE `starhorse_cms`;

ALTER TABLE `vessels`
  ADD COLUMN `tagline` VARCHAR(100) NULL DEFAULT NULL AFTER `name`;
