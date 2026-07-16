-- ============================================================
-- RYBSEN CRM - Module DATA ROOM Investisseurs
-- Tables: dataroom_acces, dataroom_documents, dataroom_logs,
--         dataroom_suggestions, auth_throttle
-- Exécuter dans phpMyAdmin sur u293743867_crmrybsen
-- ============================================================

SET NAMES utf8mb4;

-- Comptes d'accès investisseurs (login + mot de passe créés par l'admin)
CREATE TABLE IF NOT EXISTS dataroom_acces (
  id INT AUTO_INCREMENT PRIMARY KEY,
  investisseur_id INT DEFAULT NULL,
  nom VARCHAR(150) NOT NULL,
  prenom VARCHAR(100) DEFAULT '',
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  societe VARCHAR(150) DEFAULT '',
  pays VARCHAR(80) DEFAULT '',
  telephone VARCHAR(60) DEFAULT '',
  langue CHAR(2) DEFAULT 'fr',
  -- NDA électronique
  nda_signe TINYINT(1) DEFAULT 0,
  nda_date TIMESTAMP NULL DEFAULT NULL,
  nda_ip VARCHAR(45) DEFAULT NULL,
  nda_nom_signe VARCHAR(200) DEFAULT NULL,
  nda_organisation VARCHAR(200) DEFAULT NULL,
  -- Contrôle d'accès
  date_expiration DATE DEFAULT NULL,
  actif TINYINT(1) DEFAULT 1,
  derniere_connexion TIMESTAMP NULL DEFAULT NULL,
  notes TEXT,
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (investisseur_id) REFERENCES investisseurs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Documents de la Data Room
CREATE TABLE IF NOT EXISTS dataroom_documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  categorie VARCHAR(60) NOT NULL DEFAULT 'Autre',
  titre VARCHAR(200) NOT NULL,
  titre_en VARCHAR(200) DEFAULT '',
  description TEXT,
  nom_fichier VARCHAR(250) NOT NULL,      -- nom stocké (aléatoire) sur le disque
  nom_original VARCHAR(250) NOT NULL,     -- nom d'origine à l'affichage
  mime VARCHAR(100) DEFAULT 'application/pdf',
  taille_octets BIGINT DEFAULT 0,
  version VARCHAR(20) DEFAULT 'v1',
  ordre INT DEFAULT 0,
  actif TINYINT(1) DEFAULT 1,
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Journal d'audit complet (qui, quoi, d'où)
CREATE TABLE IF NOT EXISTS dataroom_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  acces_id INT DEFAULT NULL,
  document_id INT DEFAULT NULL,
  action ENUM('login','login_echec','logout','nda_vue','nda_signe','vue_document','suggestion','acces_refuse') NOT NULL,
  ip VARCHAR(45) DEFAULT NULL,
  pays_ip VARCHAR(80) DEFAULT NULL,
  ville_ip VARCHAR(120) DEFAULT NULL,
  user_agent VARCHAR(500) DEFAULT NULL,
  detail VARCHAR(500) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_dr_logs_acces (acces_id, created_at),
  INDEX idx_dr_logs_doc (document_id),
  FOREIGN KEY (acces_id) REFERENCES dataroom_acces(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Suggestions / questions des investisseurs
CREATE TABLE IF NOT EXISTS dataroom_suggestions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  acces_id INT NOT NULL,
  document_id INT DEFAULT NULL,
  message TEXT NOT NULL,
  statut ENUM('nouveau','lu','répondu') DEFAULT 'nouveau',
  reponse TEXT,
  reponse_date TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (acces_id) REFERENCES dataroom_acces(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Restrictions par investisseur : présence d'une ligne = document MASQUÉ
-- pour cet investisseur (par défaut, tout document actif est visible par tous).
CREATE TABLE IF NOT EXISTS dataroom_doc_restrictions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  acces_id INT NOT NULL,
  document_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_restriction (acces_id, document_id),
  FOREIGN KEY (acces_id) REFERENCES dataroom_acces(id) ON DELETE CASCADE,
  FOREIGN KEY (document_id) REFERENCES dataroom_documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Anti-bruteforce (CRM + Data Room)
CREATE TABLE IF NOT EXISTS auth_throttle (
  id INT AUTO_INCREMENT PRIMARY KEY,
  contexte ENUM('crm','dataroom') NOT NULL DEFAULT 'crm',
  identifiant VARCHAR(200) NOT NULL,      -- email ou IP
  tentatives INT DEFAULT 1,
  derniere_tentative TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_throttle (contexte, identifiant)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
