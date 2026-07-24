-- ============================================================
-- CRM / GMAO CTP PREPRESSE — Script d'installation MySQL
-- Filiale Kodak — parc CTP, maintenance, réparations, pièces
-- Idempotent : CREATE TABLE IF NOT EXISTS, procédure de mise à
-- niveau conditionnelle (information_schema), CREATE OR REPLACE
-- pour les vues. Ne crée que ce qui manque, n'écrase jamais les
-- données existantes.
-- À exécuter via run_migration.php ou phpMyAdmin (onglet SQL).
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ============================================================
-- UTILISATEURS & AUTHENTIFICATION
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','technicien','magasinier','client') NOT NULL DEFAULT 'client',
  -- Un utilisateur "client" (portail) est rattaché à un client.
  client_id INT NULL,
  telephone VARCHAR(50) NULL,
  avatar CHAR(2) DEFAULT 'CT',
  actif TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- CLIENTS (imprimeries / studios prepresse)
-- ============================================================
CREATE TABLE IF NOT EXISTS clients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code_client VARCHAR(40) NULL UNIQUE,
  raison_sociale VARCHAR(180) NOT NULL,
  contact_nom VARCHAR(150) NULL,
  telephone VARCHAR(50) NULL,
  email VARCHAR(150) NULL,
  adresse VARCHAR(255) NULL,
  ville VARCHAR(120) NULL,
  secteur VARCHAR(120) NULL,          -- offset, packaging, presse, labeur…
  actif TINYINT(1) DEFAULT 1,
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- PARC MACHINES CTP
-- ============================================================
CREATE TABLE IF NOT EXISTS machines (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  modele VARCHAR(120) NOT NULL,        -- Trendsetter, Magnus, Achieve, Generation News…
  gamme VARCHAR(80) NULL,              -- famille commerciale
  n_serie VARCHAR(120) NOT NULL UNIQUE,
  technologie ENUM('thermique','violet','uv','flexo','autre') NOT NULL DEFAULT 'thermique',
  format VARCHAR(80) NULL,             -- VLF, 8-up, 4-up…
  date_installation DATE NULL,
  date_fin_garantie DATE NULL,
  compteur_plaques BIGINT NOT NULL DEFAULT 0,
  localisation VARCHAR(180) NULL,      -- site / atelier
  statut ENUM('en_service','maintenance','en_panne','hors_service','retire') NOT NULL DEFAULT 'en_service',
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_machine_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
  INDEX idx_machine_client (client_id),
  INDEX idx_machine_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- CONTRATS DE MAINTENANCE
-- ============================================================
CREATE TABLE IF NOT EXISTS contrats (
  id INT AUTO_INCREMENT PRIMARY KEY,
  numero VARCHAR(40) NOT NULL UNIQUE,
  client_id INT NOT NULL,
  machine_id INT NULL,                 -- NULL = tout le parc du client
  type ENUM('preventif','full_service','garantie','a_la_demande') NOT NULL DEFAULT 'preventif',
  date_debut DATE NOT NULL,
  date_fin DATE NULL,
  frequence_jours INT NULL,            -- périodicité maintenance préventive
  prochaine_maintenance DATE NULL,
  montant_annuel DECIMAL(10,3) NOT NULL DEFAULT 0,
  sla_heures INT NULL,                 -- délai d'intervention garanti
  statut ENUM('actif','suspendu','expire') NOT NULL DEFAULT 'actif',
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_contrat_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
  CONSTRAINT fk_contrat_machine FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE SET NULL,
  INDEX idx_contrat_client (client_id),
  INDEX idx_contrat_prochaine (prochaine_maintenance)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- PIÈCES DÉTACHÉES (catalogue + stock)
-- ============================================================
CREATE TABLE IF NOT EXISTS pieces (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reference VARCHAR(80) NOT NULL UNIQUE,   -- réf. pièce Kodak
  designation VARCHAR(200) NOT NULL,
  categorie VARCHAR(120) NULL,             -- optique, laser, consommable, mécanique…
  compatibilite VARCHAR(200) NULL,         -- modèles compatibles
  fournisseur VARCHAR(150) NULL,
  prix_achat DECIMAL(10,3) NOT NULL DEFAULT 0,
  prix_vente DECIMAL(10,3) NOT NULL DEFAULT 0,
  stock INT NOT NULL DEFAULT 0,
  seuil_alerte INT NOT NULL DEFAULT 0,
  emplacement VARCHAR(80) NULL,
  actif TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- INTERVENTIONS (maintenance préventive + réparations / SAV)
-- ============================================================
CREATE TABLE IF NOT EXISTS interventions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  numero VARCHAR(40) NOT NULL UNIQUE,
  machine_id INT NOT NULL,
  client_id INT NOT NULL,              -- dénormalisé pour la portée du portail client
  contrat_id INT NULL,
  type ENUM('preventive','corrective','installation','mise_a_jour') NOT NULL DEFAULT 'corrective',
  origine ENUM('client','preventif','interne') NOT NULL DEFAULT 'interne',
  priorite ENUM('basse','normale','haute','urgente') NOT NULL DEFAULT 'normale',
  statut ENUM('nouvelle','planifiee','en_cours','en_attente_piece','resolue','cloturee','annulee') NOT NULL DEFAULT 'nouvelle',
  technicien_id INT NULL,
  date_planifiee DATETIME NULL,
  date_debut DATETIME NULL,
  date_fin DATETIME NULL,
  compteur_releve BIGINT NULL,
  description TEXT NULL,               -- symptôme / objet
  diagnostic TEXT NULL,
  resolution TEXT NULL,
  temps_passe_h DECIMAL(6,2) NOT NULL DEFAULT 0,
  cout_main_oeuvre DECIMAL(10,3) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_int_machine FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE CASCADE,
  CONSTRAINT fk_int_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
  CONSTRAINT fk_int_contrat FOREIGN KEY (contrat_id) REFERENCES contrats(id) ON DELETE SET NULL,
  CONSTRAINT fk_int_tech FOREIGN KEY (technicien_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_int_statut (statut),
  INDEX idx_int_machine (machine_id),
  INDEX idx_int_client (client_id),
  INDEX idx_int_tech (technicien_id),
  INDEX idx_int_planif (date_planifiee)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pièces consommées par une intervention
CREATE TABLE IF NOT EXISTS intervention_pieces (
  id INT AUTO_INCREMENT PRIMARY KEY,
  intervention_id INT NOT NULL,
  piece_id INT NOT NULL,
  quantite INT NOT NULL DEFAULT 1,
  prix_unitaire DECIMAL(10,3) NOT NULL DEFAULT 0,
  CONSTRAINT fk_ip_intervention FOREIGN KEY (intervention_id) REFERENCES interventions(id) ON DELETE CASCADE,
  CONSTRAINT fk_ip_piece FOREIGN KEY (piece_id) REFERENCES pieces(id) ON DELETE RESTRICT,
  INDEX idx_ip_intervention (intervention_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- COMMANDES DE PIÈCES (fournisseur)
-- ============================================================
CREATE TABLE IF NOT EXISTS commandes_pieces (
  id INT AUTO_INCREMENT PRIMARY KEY,
  numero VARCHAR(40) NOT NULL UNIQUE,
  fournisseur VARCHAR(150) NULL,
  statut ENUM('brouillon','commandee','partielle','recue','annulee') NOT NULL DEFAULT 'brouillon',
  date_commande DATE NULL,
  date_reception_prevue DATE NULL,
  date_reception DATE NULL,
  montant_total DECIMAL(10,3) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_cmd_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS commande_lignes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  commande_id INT NOT NULL,
  piece_id INT NOT NULL,
  quantite INT NOT NULL DEFAULT 1,
  quantite_recue INT NOT NULL DEFAULT 0,
  prix_unitaire DECIMAL(10,3) NOT NULL DEFAULT 0,
  CONSTRAINT fk_cl_commande FOREIGN KEY (commande_id) REFERENCES commandes_pieces(id) ON DELETE CASCADE,
  CONSTRAINT fk_cl_piece FOREIGN KEY (piece_id) REFERENCES pieces(id) ON DELETE RESTRICT,
  INDEX idx_cl_commande (commande_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- MOUVEMENTS DE STOCK (traçabilité complète)
-- ============================================================
CREATE TABLE IF NOT EXISTS mouvements_stock (
  id INT AUTO_INCREMENT PRIMARY KEY,
  piece_id INT NOT NULL,
  type ENUM('entree','sortie','ajustement') NOT NULL,
  quantite INT NOT NULL,               -- signé : +entrée / -sortie
  stock_apres INT NOT NULL,
  motif VARCHAR(200) NULL,
  ref_type VARCHAR(40) NULL,           -- intervention | commande | manuel
  ref_id INT NULL,
  user_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_mvt_piece FOREIGN KEY (piece_id) REFERENCES pieces(id) ON DELETE CASCADE,
  INDEX idx_mvt_piece (piece_id),
  INDEX idx_mvt_ref (ref_type, ref_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- VUES UTILES
-- ============================================================
CREATE OR REPLACE VIEW v_pieces_stock_bas AS
  SELECT * FROM pieces
  WHERE actif = 1 AND stock <= seuil_alerte;

CREATE OR REPLACE VIEW v_maintenance_due AS
  SELECT c.id AS contrat_id, c.numero, c.client_id, cl.raison_sociale,
         c.machine_id, c.type, c.prochaine_maintenance, c.sla_heures,
         DATEDIFF(c.prochaine_maintenance, CURDATE()) AS jours_restants
  FROM contrats c
  JOIN clients cl ON cl.id = c.client_id
  WHERE c.statut = 'actif'
    AND c.prochaine_maintenance IS NOT NULL;

CREATE OR REPLACE VIEW v_interventions_ouvertes AS
  SELECT i.*, m.modele, m.n_serie, cl.raison_sociale, u.nom AS technicien_nom
  FROM interventions i
  JOIN machines m ON m.id = i.machine_id
  JOIN clients cl ON cl.id = i.client_id
  LEFT JOIN users u ON u.id = i.technicien_id
  WHERE i.statut NOT IN ('cloturee','annulee');

-- ============================================================
-- MISE À NIVEAU CONDITIONNELLE DES COLONNES (idempotent)
-- Ajoute une colonne uniquement si elle n'existe pas déjà.
-- ============================================================
DROP PROCEDURE IF EXISTS ctp_add_col;
DELIMITER //
CREATE PROCEDURE ctp_add_col(IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col
  ) THEN
    SET @s = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN ', ddl);
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END //
DELIMITER ;

-- Exemples de futures évolutions (déjà présentes ci-dessus, servent de gabarit) :
CALL ctp_add_col('machines', 'gamme', "gamme VARCHAR(80) NULL AFTER modele");
CALL ctp_add_col('users', 'telephone', "telephone VARCHAR(50) NULL AFTER client_id");

DROP PROCEDURE IF EXISTS ctp_add_col;

-- ============================================================
-- SEED : compte administrateur par défaut (créé si absent)
-- Email : admin@ctp.rybsen.com  ·  Mot de passe : kodak2026
-- (à changer immédiatement après le premier login)
-- ============================================================
INSERT INTO users (nom, email, password_hash, role, avatar, actif)
SELECT 'Administrateur', 'admin@ctp.rybsen.com',
       '$2y$12$Vd9N1skAETcGzQT9FWcLdOc9QO2ZrusIR/XMNChFnl1dtwxtLQYH6',
       'admin', 'AD', 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'admin@ctp.rybsen.com');
