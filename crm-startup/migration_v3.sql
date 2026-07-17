-- ============================================================
-- Migration v3 — Startup Tunisia
-- Vérification email + reset mot de passe
-- À exécuter dans phpMyAdmin sur u293743867_startup
-- Compatible MySQL 5.7 et 8.0
-- ============================================================

-- Colonnes de vérification email et reset password
ALTER TABLE `fm_users`
  ADD COLUMN `email_verified`      TINYINT(1)   NOT NULL DEFAULT 0     AFTER `is_active`,
  ADD COLUMN `email_verif_code`    VARCHAR(255) DEFAULT NULL            AFTER `email_verified`,
  ADD COLUMN `email_verif_expires` DATETIME     DEFAULT NULL            AFTER `email_verif_code`,
  ADD COLUMN `reset_token`         VARCHAR(255) DEFAULT NULL            AFTER `email_verif_expires`,
  ADD COLUMN `reset_token_expires` DATETIME     DEFAULT NULL            AFTER `reset_token`;

-- Les comptes existants sont considérés comme vérifiés (créés avant cette migration)
UPDATE `fm_users` SET `email_verified` = 1 WHERE `is_active` = 1;

-- Index pour les lookups de tokens (performance)
ALTER TABLE `fm_users`
  ADD INDEX `idx_reset_token`   (`reset_token`),
  ADD INDEX `idx_email_verified`(`email`, `email_verified`);
