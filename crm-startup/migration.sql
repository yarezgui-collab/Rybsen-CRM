-- ============================================================
-- Migration v2 — Startup Tunisia
-- Nouveaux champs profil startup + table submissions update
-- À exécuter dans phpMyAdmin sur u293743867_startup
-- ============================================================

-- Ajout des champs profil enrichi sur fm_users
ALTER TABLE `fm_users`
  ADD COLUMN IF NOT EXISTS `website`        VARCHAR(300) DEFAULT NULL AFTER `sector`,
  ADD COLUMN IF NOT EXISTS `city`           VARCHAR(100) DEFAULT NULL AFTER `website`,
  ADD COLUMN IF NOT EXISTS `founded_year`   YEAR DEFAULT NULL AFTER `city`,
  ADD COLUMN IF NOT EXISTS `founders_count` TINYINT DEFAULT 1 AFTER `founded_year`,
  ADD COLUMN IF NOT EXISTS `ceo_name`       VARCHAR(150) DEFAULT NULL AFTER `founders_count`,
  ADD COLUMN IF NOT EXISTS `elevator_pitch` VARCHAR(500) DEFAULT NULL AFTER `ceo_name`,
  ADD COLUMN IF NOT EXISTS `problem`        TEXT DEFAULT NULL AFTER `elevator_pitch`,
  ADD COLUMN IF NOT EXISTS `solution`       TEXT DEFAULT NULL AFTER `problem`,
  ADD COLUMN IF NOT EXISTS `has_tech_team`  TINYINT(1) DEFAULT 0 AFTER `solution`,
  ADD COLUMN IF NOT EXISTS `revenue_range`  ENUM('pre-revenue','0-10k','10k-50k','50k-200k','200k-1m','1m+') DEFAULT 'pre-revenue' AFTER `has_tech_team`,
  ADD COLUMN IF NOT EXISTS `users_count`    VARCHAR(50) DEFAULT NULL AFTER `revenue_range`,
  ADD COLUMN IF NOT EXISTS `funding_raised` VARCHAR(100) DEFAULT NULL AFTER `users_count`,
  ADD COLUMN IF NOT EXISTS `funding_target` VARCHAR(100) DEFAULT NULL AFTER `funding_raised`,
  ADD COLUMN IF NOT EXISTS `funding_type`   VARCHAR(200) DEFAULT NULL AFTER `funding_target`,
  ADD COLUMN IF NOT EXISTS `looking_for`    VARCHAR(300) DEFAULT NULL AFTER `funding_type`,
  ADD COLUMN IF NOT EXISTS `submissions_count` INT DEFAULT 0 AFTER `looking_for`;

-- Index pour le leaderboard
ALTER TABLE `fm_users` ADD INDEX IF NOT EXISTS `idx_submissions_count` (`submissions_count`);

