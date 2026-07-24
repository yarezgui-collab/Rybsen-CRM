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
-- MAINTENANCES PRÉVENTIVES PLANIFIÉES (calendrier contractuel)
-- Cœur du SAV Kodak CTP : chaque machine sous contrat porte un
-- calendrier de visites — « préventive » (PM contractuelle) et
-- « prévisionnelle » (contrôle intermédiaire). Marquer « réalisée »
-- génère/rattache une intervention.
-- ============================================================
CREATE TABLE IF NOT EXISTS maintenances_planifiees (
  id INT AUTO_INCREMENT PRIMARY KEY,
  contrat_id INT NULL,
  machine_id INT NOT NULL,
  client_id INT NOT NULL,
  type ENUM('preventive','previsionnelle') NOT NULL DEFAULT 'preventive',
  rang INT NULL,                       -- ordre de la visite dans l'année
  date_prevue DATE NOT NULL,
  date_realisee DATE NULL,
  intervention_id INT NULL,
  statut ENUM('planifiee','realisee','reportee','annulee') NOT NULL DEFAULT 'planifiee',
  technicien_id INT NULL,              -- technicien affecté (planning / tournées)
  notes VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_mp_contrat FOREIGN KEY (contrat_id) REFERENCES contrats(id) ON DELETE SET NULL,
  CONSTRAINT fk_mp_machine FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE CASCADE,
  CONSTRAINT fk_mp_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
  CONSTRAINT fk_mp_intervention FOREIGN KEY (intervention_id) REFERENCES interventions(id) ON DELETE SET NULL,
  CONSTRAINT fk_mp_tech FOREIGN KEY (technicien_id) REFERENCES users(id) ON DELETE SET NULL,
  UNIQUE KEY uq_mp (machine_id, date_prevue, type),
  INDEX idx_mp_date (date_prevue),
  INDEX idx_mp_statut (statut),
  INDEX idx_mp_tech (technicien_id)
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

-- NB : la vue v_maintenances_planifiees est (re)créée plus bas, APRÈS la mise à
-- niveau des colonnes, pour inclure technicien_id sur les bases déjà existantes.

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
-- Affectation technicien sur une visite préventive (planning / tournées) :
CALL ctp_add_col('maintenances_planifiees', 'technicien_id', "technicien_id INT NULL AFTER statut");

DROP PROCEDURE IF EXISTS ctp_add_col;

-- Vue calendrier des visites planifiées (avec technicien + ville pour les tournées).
-- Recréée ici pour prendre en compte technicien_id même sur une base préexistante.
CREATE OR REPLACE VIEW v_maintenances_planifiees AS
  SELECT mp.*, m.modele, m.n_serie, cl.raison_sociale, cl.ville,
         ct.numero AS contrat_numero, u.nom AS technicien_nom,
         DATEDIFF(mp.date_prevue, CURDATE()) AS jours_restants
  FROM maintenances_planifiees mp
  JOIN machines m ON m.id = mp.machine_id
  JOIN clients cl ON cl.id = mp.client_id
  LEFT JOIN contrats ct ON ct.id = mp.contrat_id
  LEFT JOIN users u ON u.id = mp.technicien_id
  WHERE mp.statut = 'planifiee';

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

-- ============================================================
-- DONNÉES RÉELLES — Référentiel clients Kodak CTP (idempotent)
-- Import insert-if-missing : clients par code_client, machines par
-- n_serie, contrats par numero, visites par (machine,date,type),
-- interventions par numero. Rejouable sans doublon.
-- ============================================================
INSERT INTO clients (code_client,raison_sociale,ville,secteur,actif,notes) SELECT 'HPS','HPS','MEGHIRA','Imprimerie',1,NULL FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM clients WHERE code_client='HPS');
INSERT INTO clients (code_client,raison_sociale,ville,secteur,actif,notes) SELECT 'NOVAPRINT','NOVAPRINT','SFAX','Imprimerie',1,NULL FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM clients WHERE code_client='NOVAPRINT');
INSERT INTO clients (code_client,raison_sociale,ville,secteur,actif,notes) SELECT 'I_O_R_T','I.O.R.T.','BEN AROUS','Imprimerie',1,NULL FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM clients WHERE code_client='I_O_R_T');
INSERT INTO clients (code_client,raison_sociale,ville,secteur,actif,notes) SELECT 'ETABLISSEMENT_BOUAZIZ_CIE','ETABLISSEMENT BOUAZIZ CIE','SFAX','Imprimerie',1,NULL FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM clients WHERE code_client='ETABLISSEMENT_BOUAZIZ_CIE');
INSERT INTO clients (code_client,raison_sociale,ville,secteur,actif,notes) SELECT 'MIP_LIVRE','MIP LIVRE','Borj Louzir','Imprimerie',1,NULL FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM clients WHERE code_client='MIP_LIVRE');
INSERT INTO clients (code_client,raison_sociale,ville,secteur,actif,notes) SELECT 'BM_IMPRIM','BM IMPRIM','SFAX','Imprimerie',1,NULL FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM clients WHERE code_client='BM_IMPRIM');
INSERT INTO clients (code_client,raison_sociale,ville,secteur,actif,notes) SELECT 'SITPEC','SITPEC','HAMMAMT','Imprimerie',1,NULL FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM clients WHERE code_client='SITPEC');
INSERT INTO clients (code_client,raison_sociale,ville,secteur,actif,notes) SELECT 'OMEGA_EDITIONS','OMEGA EDITIONS','KSAR SAÏD','Imprimerie',1,NULL FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM clients WHERE code_client='OMEGA_EDITIONS');
INSERT INTO clients (code_client,raison_sociale,ville,secteur,actif,notes) SELECT 'IMPREMERIE_INTERNATIONALE_DE_TUNIS','IMPREMERIE INTERNATIONALE DE TUNIS','SANHEJA MANOUBA','Imprimerie',1,'Sous contrôle juridique' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM clients WHERE code_client='IMPREMERIE_INTERNATIONALE_DE_TUNIS');
INSERT INTO clients (code_client,raison_sociale,ville,secteur,actif,notes) SELECT 'PRINTEC','PRINTEC','BEN AROUS','Imprimerie',1,NULL FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM clients WHERE code_client='PRINTEC');
INSERT INTO clients (code_client,raison_sociale,ville,secteur,actif,notes) SELECT 'DGHS','DGHS','KSAR SAÏD','Imprimerie',1,NULL FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM clients WHERE code_client='DGHS');
INSERT INTO clients (code_client,raison_sociale,ville,secteur,actif,notes) SELECT 'IMPRIMERIE_KOUBAA','IMPRIMERIE KOUBAA','SFAX','Imprimerie',1,NULL FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM clients WHERE code_client='IMPRIMERIE_KOUBAA');
INSERT INTO clients (code_client,raison_sociale,ville,secteur,actif,notes) SELECT 'STE_LA_BOITE_METALLIQUE_TNNE','STE LA BOITE METALLIQUE TNNE','KORBA','Imprimerie',1,NULL FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM clients WHERE code_client='STE_LA_BOITE_METALLIQUE_TNNE');
INSERT INTO clients (code_client,raison_sociale,ville,secteur,actif,notes) SELECT 'SOGIM','SOGIM','GABES','Imprimerie',1,NULL FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM clients WHERE code_client='SOGIM');
INSERT INTO clients (code_client,raison_sociale,ville,secteur,actif,notes) SELECT 'TEC_MMP','TEC MMP','SFAX','Imprimerie',1,NULL FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM clients WHERE code_client='TEC_MMP');
INSERT INTO clients (code_client,raison_sociale,ville,secteur,actif,notes) SELECT 'STAG','STAG','ZI UTIQUE','Imprimerie',1,NULL FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM clients WHERE code_client='STAG');
INSERT INTO clients (code_client,raison_sociale,ville,secteur,actif,notes) SELECT 'SOTEPA_GRAPHIQUE','SOTEPA GRAPHIQUE','TUNIS','Imprimerie',1,NULL FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM clients WHERE code_client='SOTEPA_GRAPHIQUE');
INSERT INTO clients (code_client,raison_sociale,ville,secteur,actif,notes) SELECT 'TOP_PRINTING','TOP PRINTING','Mégrine','Imprimerie',1,NULL FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM clients WHERE code_client='TOP_PRINTING');
INSERT INTO clients (code_client,raison_sociale,ville,secteur,actif,notes) SELECT 'TECHNO_PRINT','TECHNO PRINT','SFAX','Imprimerie',1,NULL FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM clients WHERE code_client='TECHNO_PRINT');
INSERT INTO clients (code_client,raison_sociale,ville,secteur,actif,notes) SELECT 'NOUHA_ECO_PRINT','NOUHA ECO PRINT','SFAX','Imprimerie',1,NULL FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM clients WHERE code_client='NOUHA_ECO_PRINT');
INSERT INTO clients (code_client,raison_sociale,ville,secteur,actif,notes) SELECT 'SIDE','SIDE','Ben Arous','Imprimerie',1,NULL FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM clients WHERE code_client='SIDE');
INSERT INTO clients (code_client,raison_sociale,ville,secteur,actif,notes) SELECT 'DAR_EL_FOUNOUN','DAR EL FOUNOUN','BEN AROUS','Imprimerie',0,'inactif (client avec hamza ayari)' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM clients WHERE code_client='DAR_EL_FOUNOUN');
INSERT INTO clients (code_client,raison_sociale,ville,secteur,actif,notes) SELECT 'SGIE','SGIE',NULL,'Imprimerie',0,NULL FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM clients WHERE code_client='SGIE');
INSERT INTO clients (code_client,raison_sociale,ville,secteur,actif,notes) SELECT 'IMPRIMERIE_PRINCIPALE','IMPRIMERIE PRINCIPALE','BEN AROUS','Imprimerie',0,NULL FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM clients WHERE code_client='IMPRIMERIE_PRINCIPALE');
INSERT INTO machines (client_id,modele,gamme,n_serie,technologie,date_installation,localisation,statut) SELECT c.id,'TRENDSETTER 800','Trendsetter','TT1565','thermique',NULL,'MEGHIRA','en_service' FROM clients c WHERE c.code_client='HPS' AND NOT EXISTS (SELECT 1 FROM machines WHERE n_serie='TT1565');
INSERT INTO machines (client_id,modele,gamme,n_serie,technologie,date_installation,localisation,statut) SELECT c.id,'ACHIEVE 800 PR - S SPEED','Achieve','TJ4886','thermique','2015-05-24','SFAX','en_service' FROM clients c WHERE c.code_client='NOVAPRINT' AND NOT EXISTS (SELECT 1 FROM machines WHERE n_serie='TJ4886');
INSERT INTO machines (client_id,modele,gamme,n_serie,technologie,date_installation,localisation,statut) SELECT c.id,'TRENDSETTER Q800 F-SPD PRT CNL TDL','Trendsetter','TJ2615','thermique','2017-12-18','BEN AROUS','en_service' FROM clients c WHERE c.code_client='I_O_R_T' AND NOT EXISTS (SELECT 1 FROM machines WHERE n_serie='TJ2615');
INSERT INTO machines (client_id,modele,gamme,n_serie,technologie,date_installation,localisation,statut) SELECT c.id,'ACHIEVE 800 PR - S SPEED','Achieve','TJ0013','thermique','2013-06-27','SFAX','en_service' FROM clients c WHERE c.code_client='ETABLISSEMENT_BOUAZIZ_CIE' AND NOT EXISTS (SELECT 1 FROM machines WHERE n_serie='TJ0013');
INSERT INTO machines (client_id,modele,gamme,n_serie,technologie,date_installation,localisation,statut) SELECT c.id,'ACHIEVE 800 PR - S SPEED','Achieve','TJ3903','thermique','2021-06-18','Borj Louzir','en_service' FROM clients c WHERE c.code_client='MIP_LIVRE' AND NOT EXISTS (SELECT 1 FROM machines WHERE n_serie='TJ3903');
INSERT INTO machines (client_id,modele,gamme,n_serie,technologie,date_installation,localisation,statut) SELECT c.id,'TRENDSETTER 800','Trendsetter','TT2215','thermique','2012-12-20','SFAX','en_service' FROM clients c WHERE c.code_client='BM_IMPRIM' AND NOT EXISTS (SELECT 1 FROM machines WHERE n_serie='TT2215');
INSERT INTO machines (client_id,modele,gamme,n_serie,technologie,date_installation,localisation,statut) SELECT c.id,'TRENDSETTER 800','Trendsetter','TJ3109','thermique',NULL,'SFAX','en_service' FROM clients c WHERE c.code_client='BM_IMPRIM' AND NOT EXISTS (SELECT 1 FROM machines WHERE n_serie='TJ3109');
INSERT INTO machines (client_id,modele,gamme,n_serie,technologie,date_installation,localisation,statut) SELECT c.id,'KODAK MAGNUS Q800 F-SPD W XPO TDL','Magnus','M81869','thermique','2014-05-14','HAMMAMT','en_service' FROM clients c WHERE c.code_client='SITPEC' AND NOT EXISTS (SELECT 1 FROM machines WHERE n_serie='M81869');
INSERT INTO machines (client_id,modele,gamme,n_serie,technologie,date_installation,localisation,statut) SELECT c.id,'ACHIEVE 800 PR - S SPEED','Achieve','TJ5106','thermique','2024-12-17','KSAR SAÏD','en_service' FROM clients c WHERE c.code_client='OMEGA_EDITIONS' AND NOT EXISTS (SELECT 1 FROM machines WHERE n_serie='TJ5106');
INSERT INTO machines (client_id,modele,gamme,n_serie,technologie,date_installation,localisation,statut) SELECT c.id,'TRENDSETTER Q800 F-SPD PRT CNL TDL','Trendsetter','TJ2710','thermique','2018-04-26','KSAR SAÏD','en_service' FROM clients c WHERE c.code_client='OMEGA_EDITIONS' AND NOT EXISTS (SELECT 1 FROM machines WHERE n_serie='TJ2710');
INSERT INTO machines (client_id,modele,gamme,n_serie,technologie,date_installation,localisation,statut) SELECT c.id,'TS Q1600 F-SPD W PC TDL','Trendsetter','TL0315','thermique','2017-06-29','SANHEJA MANOUBA','en_service' FROM clients c WHERE c.code_client='IMPREMERIE_INTERNATIONALE_DE_TUNIS' AND NOT EXISTS (SELECT 1 FROM machines WHERE n_serie='TL0315');
INSERT INTO machines (client_id,modele,gamme,n_serie,technologie,date_installation,localisation,statut) SELECT c.id,'TRENDSETTER 400','Trendsetter','TT1345','thermique','2011-09-16','BEN AROUS','en_service' FROM clients c WHERE c.code_client='PRINTEC' AND NOT EXISTS (SELECT 1 FROM machines WHERE n_serie='TT1345');
INSERT INTO machines (client_id,modele,gamme,n_serie,technologie,date_installation,localisation,statut) SELECT c.id,'TRENDSETTER Q400 F-SPD PRT CNL TDL','Trendsetter','TJ4802','thermique','2023-12-07','KSAR SAÏD','en_service' FROM clients c WHERE c.code_client='DGHS' AND NOT EXISTS (SELECT 1 FROM machines WHERE n_serie='TJ4802');
INSERT INTO machines (client_id,modele,gamme,n_serie,technologie,date_installation,localisation,statut) SELECT c.id,'TRENDSETTER Q800 F-SPD PRT CNL TDL','Trendsetter','TJ3156','thermique','2019-03-22','SFAX','en_service' FROM clients c WHERE c.code_client='IMPRIMERIE_KOUBAA' AND NOT EXISTS (SELECT 1 FROM machines WHERE n_serie='TJ3156');
INSERT INTO machines (client_id,modele,gamme,n_serie,technologie,date_installation,localisation,statut) SELECT c.id,'KCS TS Q1600 FSPD','KCS','TL0394','thermique','2020-03-11','KORBA','en_service' FROM clients c WHERE c.code_client='STE_LA_BOITE_METALLIQUE_TNNE' AND NOT EXISTS (SELECT 1 FROM machines WHERE n_serie='TL0394');
INSERT INTO machines (client_id,modele,gamme,n_serie,technologie,date_installation,localisation,statut) SELECT c.id,'ACHIEVE 800 PR - S SPEED','Achieve','TJ1905','thermique','2016-12-01','GABES','en_service' FROM clients c WHERE c.code_client='SOGIM' AND NOT EXISTS (SELECT 1 FROM machines WHERE n_serie='TJ1905');
INSERT INTO machines (client_id,modele,gamme,n_serie,technologie,date_installation,localisation,statut) SELECT c.id,'ACHIEVE 800 PR - S SPEED','Achieve','TJ5236','thermique','2025-06-04','GABES','en_service' FROM clients c WHERE c.code_client='SOGIM' AND NOT EXISTS (SELECT 1 FROM machines WHERE n_serie='TJ5236');
INSERT INTO machines (client_id,modele,gamme,n_serie,technologie,date_installation,localisation,statut) SELECT c.id,'MAGNUS Q800 F-SPD W XPO TDL','Magnus','M81940','thermique','2014-12-02','SFAX','en_service' FROM clients c WHERE c.code_client='TEC_MMP' AND NOT EXISTS (SELECT 1 FROM machines WHERE n_serie='M81940');
INSERT INTO machines (client_id,modele,gamme,n_serie,technologie,date_installation,localisation,statut) SELECT c.id,'TRENDSETTER 800','Trendsetter','TT2216','thermique','2013-08-01','ZI UTIQUE','en_service' FROM clients c WHERE c.code_client='STAG' AND NOT EXISTS (SELECT 1 FROM machines WHERE n_serie='TT2216');
INSERT INTO machines (client_id,modele,gamme,n_serie,technologie,date_installation,localisation,statut) SELECT c.id,'ACHIEVE 800 PR - S SPEED','Achieve','TJ2734','thermique','2018-05-04','TUNIS','en_service' FROM clients c WHERE c.code_client='SOTEPA_GRAPHIQUE' AND NOT EXISTS (SELECT 1 FROM machines WHERE n_serie='TJ2734');
INSERT INTO machines (client_id,modele,gamme,n_serie,technologie,date_installation,localisation,statut) SELECT c.id,'TRENDSETTER 800','Trendsetter','TT0432','thermique','2010-10-07','Mégrine','en_service' FROM clients c WHERE c.code_client='TOP_PRINTING' AND NOT EXISTS (SELECT 1 FROM machines WHERE n_serie='TT0432');
INSERT INTO machines (client_id,modele,gamme,n_serie,technologie,date_installation,localisation,statut) SELECT c.id,'ACHIEVE 400 PR - S SPEED','Achieve','TJ0611','thermique','2014-11-01','SFAX','en_service' FROM clients c WHERE c.code_client='TECHNO_PRINT' AND NOT EXISTS (SELECT 1 FROM machines WHERE n_serie='TJ0611');
INSERT INTO machines (client_id,modele,gamme,n_serie,technologie,date_installation,localisation,statut) SELECT c.id,'Trendsetter 800 III Quantum (S Speed)','Trendsetter','TG0099','thermique',NULL,'SFAX','en_service' FROM clients c WHERE c.code_client='NOUHA_ECO_PRINT' AND NOT EXISTS (SELECT 1 FROM machines WHERE n_serie='TG0099');
INSERT INTO machines (client_id,modele,gamme,n_serie,technologie,date_installation,localisation,statut) SELECT c.id,'ACHIEVE 800','Achieve','TJ0399','thermique','2015-03-16','Ben Arous','en_service' FROM clients c WHERE c.code_client='SIDE' AND NOT EXISTS (SELECT 1 FROM machines WHERE n_serie='TJ0399');
INSERT INTO machines (client_id,modele,gamme,n_serie,technologie,date_installation,localisation,statut) SELECT c.id,'KODAK TRENDSETTER Q800 F-SPD PRT CNL TDL','Trendsetter','TJ1574','thermique','2019-03-22','BEN AROUS','hors_service' FROM clients c WHERE c.code_client='DAR_EL_FOUNOUN' AND NOT EXISTS (SELECT 1 FROM machines WHERE n_serie='TJ1574');
INSERT INTO contrats (numero,client_id,machine_id,type,date_debut,prochaine_maintenance,statut,notes) SELECT 'CTR-KODAK-TL0394',c.id,m.id,'preventif','2020-03-11','2026-04-20','actif',NULL FROM clients c JOIN machines m ON m.n_serie='TL0394' WHERE c.code_client='STE_LA_BOITE_METALLIQUE_TNNE' AND NOT EXISTS (SELECT 1 FROM contrats WHERE numero='CTR-KODAK-TL0394');
INSERT INTO contrats (numero,client_id,machine_id,type,date_debut,prochaine_maintenance,statut,notes) SELECT 'CTR-KODAK-TJ1905',c.id,m.id,'preventif','2016-12-01','2026-01-29','actif',NULL FROM clients c JOIN machines m ON m.n_serie='TJ1905' WHERE c.code_client='SOGIM' AND NOT EXISTS (SELECT 1 FROM contrats WHERE numero='CTR-KODAK-TJ1905');
INSERT INTO contrats (numero,client_id,machine_id,type,date_debut,prochaine_maintenance,statut,notes) SELECT 'CTR-KODAK-TJ5236',c.id,m.id,'preventif','2025-06-04','2026-01-29','actif',NULL FROM clients c JOIN machines m ON m.n_serie='TJ5236' WHERE c.code_client='SOGIM' AND NOT EXISTS (SELECT 1 FROM contrats WHERE numero='CTR-KODAK-TJ5236');
INSERT INTO contrats (numero,client_id,machine_id,type,date_debut,prochaine_maintenance,statut,notes) SELECT 'CTR-KODAK-M81940',c.id,m.id,'preventif','2014-12-02','2026-07-15','actif',NULL FROM clients c JOIN machines m ON m.n_serie='M81940' WHERE c.code_client='TEC_MMP' AND NOT EXISTS (SELECT 1 FROM contrats WHERE numero='CTR-KODAK-M81940');
INSERT INTO contrats (numero,client_id,machine_id,type,date_debut,prochaine_maintenance,statut,notes) SELECT 'CTR-KODAK-TT2216',c.id,m.id,'preventif','2013-08-01','2026-03-07','actif',NULL FROM clients c JOIN machines m ON m.n_serie='TT2216' WHERE c.code_client='STAG' AND NOT EXISTS (SELECT 1 FROM contrats WHERE numero='CTR-KODAK-TT2216');
INSERT INTO contrats (numero,client_id,machine_id,type,date_debut,prochaine_maintenance,statut,notes) SELECT 'CTR-KODAK-TJ2734',c.id,m.id,'preventif','2018-05-04','2026-01-30','actif',NULL FROM clients c JOIN machines m ON m.n_serie='TJ2734' WHERE c.code_client='SOTEPA_GRAPHIQUE' AND NOT EXISTS (SELECT 1 FROM contrats WHERE numero='CTR-KODAK-TJ2734');
INSERT INTO contrats (numero,client_id,machine_id,type,date_debut,prochaine_maintenance,statut,notes) SELECT 'CTR-KODAK-TT0432',c.id,m.id,'preventif','2010-10-07','2026-02-26','actif',NULL FROM clients c JOIN machines m ON m.n_serie='TT0432' WHERE c.code_client='TOP_PRINTING' AND NOT EXISTS (SELECT 1 FROM contrats WHERE numero='CTR-KODAK-TT0432');
INSERT INTO contrats (numero,client_id,machine_id,type,date_debut,prochaine_maintenance,statut,notes) SELECT 'CTR-KODAK-TJ0611',c.id,m.id,'preventif','2014-11-01','2026-05-22','actif',NULL FROM clients c JOIN machines m ON m.n_serie='TJ0611' WHERE c.code_client='TECHNO_PRINT' AND NOT EXISTS (SELECT 1 FROM contrats WHERE numero='CTR-KODAK-TJ0611');
INSERT INTO contrats (numero,client_id,machine_id,type,date_debut,prochaine_maintenance,statut,notes) SELECT 'CTR-KODAK-TG0099',c.id,m.id,'preventif','2026-05-22','2026-05-22','actif',NULL FROM clients c JOIN machines m ON m.n_serie='TG0099' WHERE c.code_client='NOUHA_ECO_PRINT' AND NOT EXISTS (SELECT 1 FROM contrats WHERE numero='CTR-KODAK-TG0099');
INSERT INTO contrats (numero,client_id,machine_id,type,date_debut,prochaine_maintenance,statut,notes) SELECT 'CTR-KODAK-TJ0399',c.id,m.id,'preventif','2015-03-16',NULL,'suspendu','En cours de validation' FROM clients c JOIN machines m ON m.n_serie='TJ0399' WHERE c.code_client='SIDE' AND NOT EXISTS (SELECT 1 FROM contrats WHERE numero='CTR-KODAK-TJ0399');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'preventive',1,'2026-04-20','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-TL0394' WHERE m.n_serie='TL0394' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-04-20' AND type='preventive');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'previsionnelle',1,'2026-07-30','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-TL0394' WHERE m.n_serie='TL0394' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-07-30' AND type='previsionnelle');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'previsionnelle',2,'2026-09-30','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-TL0394' WHERE m.n_serie='TL0394' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-09-30' AND type='previsionnelle');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'preventive',2,'2026-12-28','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-TL0394' WHERE m.n_serie='TL0394' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-12-28' AND type='preventive');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'preventive',1,'2026-01-29','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-TJ1905' WHERE m.n_serie='TJ1905' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-01-29' AND type='preventive');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'previsionnelle',1,'2026-04-16','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-TJ1905' WHERE m.n_serie='TJ1905' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-04-16' AND type='previsionnelle');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'previsionnelle',2,'2026-05-21','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-TJ1905' WHERE m.n_serie='TJ1905' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-05-21' AND type='previsionnelle');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'preventive',2,'2026-07-22','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-TJ1905' WHERE m.n_serie='TJ1905' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-07-22' AND type='preventive');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'previsionnelle',3,'2026-10-19','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-TJ1905' WHERE m.n_serie='TJ1905' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-10-19' AND type='previsionnelle');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'previsionnelle',4,'2026-12-19','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-TJ1905' WHERE m.n_serie='TJ1905' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-12-19' AND type='previsionnelle');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'preventive',1,'2026-01-29','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-TJ5236' WHERE m.n_serie='TJ5236' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-01-29' AND type='preventive');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'previsionnelle',1,'2026-04-16','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-TJ5236' WHERE m.n_serie='TJ5236' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-04-16' AND type='previsionnelle');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'previsionnelle',2,'2026-05-21','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-TJ5236' WHERE m.n_serie='TJ5236' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-05-21' AND type='previsionnelle');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'preventive',2,'2026-07-22','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-TJ5236' WHERE m.n_serie='TJ5236' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-07-22' AND type='preventive');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'previsionnelle',3,'2026-10-19','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-TJ5236' WHERE m.n_serie='TJ5236' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-10-19' AND type='previsionnelle');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'previsionnelle',4,'2026-12-19','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-TJ5236' WHERE m.n_serie='TJ5236' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-12-19' AND type='previsionnelle');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'preventive',1,'2026-07-15','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-M81940' WHERE m.n_serie='M81940' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-07-15' AND type='preventive');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'previsionnelle',1,'2026-07-23','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-M81940' WHERE m.n_serie='M81940' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-07-23' AND type='previsionnelle');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'previsionnelle',2,'2026-10-20','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-M81940' WHERE m.n_serie='M81940' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-10-20' AND type='previsionnelle');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'preventive',2,'2026-12-20','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-M81940' WHERE m.n_serie='M81940' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-12-20' AND type='preventive');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'preventive',1,'2026-03-07','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-TT2216' WHERE m.n_serie='TT2216' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-03-07' AND type='preventive');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'previsionnelle',1,'2026-05-29','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-TT2216' WHERE m.n_serie='TT2216' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-05-29' AND type='previsionnelle');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'previsionnelle',2,'2026-07-31','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-TT2216' WHERE m.n_serie='TT2216' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-07-31' AND type='previsionnelle');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'preventive',2,'2026-09-24','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-TT2216' WHERE m.n_serie='TT2216' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-09-24' AND type='preventive');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'previsionnelle',3,'2026-10-24','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-TT2216' WHERE m.n_serie='TT2216' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-10-24' AND type='previsionnelle');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'previsionnelle',4,'2026-12-24','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-TT2216' WHERE m.n_serie='TT2216' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-12-24' AND type='previsionnelle');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'preventive',1,'2026-01-30','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-TJ2734' WHERE m.n_serie='TJ2734' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-01-30' AND type='preventive');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'previsionnelle',1,'2026-04-13','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-TJ2734' WHERE m.n_serie='TJ2734' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-04-13' AND type='previsionnelle');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'previsionnelle',2,'2026-05-04','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-TJ2734' WHERE m.n_serie='TJ2734' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-05-04' AND type='previsionnelle');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'preventive',2,'2026-06-30','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-TJ2734' WHERE m.n_serie='TJ2734' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-06-30' AND type='preventive');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'previsionnelle',3,'2026-09-30','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-TJ2734' WHERE m.n_serie='TJ2734' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-09-30' AND type='previsionnelle');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'previsionnelle',4,'2029-11-27','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-TJ2734' WHERE m.n_serie='TJ2734' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2029-11-27' AND type='previsionnelle');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'preventive',1,'2026-02-26','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-TT0432' WHERE m.n_serie='TT0432' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-02-26' AND type='preventive');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'previsionnelle',1,'2026-04-27','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-TT0432' WHERE m.n_serie='TT0432' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-04-27' AND type='previsionnelle');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'previsionnelle',2,'2026-06-30','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-TT0432' WHERE m.n_serie='TT0432' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-06-30' AND type='previsionnelle');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'preventive',2,'2026-08-26','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-TT0432' WHERE m.n_serie='TT0432' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-08-26' AND type='preventive');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'previsionnelle',3,'2026-10-26','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-TT0432' WHERE m.n_serie='TT0432' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-10-26' AND type='previsionnelle');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'previsionnelle',4,'2026-12-26','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-TT0432' WHERE m.n_serie='TT0432' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-12-26' AND type='previsionnelle');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'preventive',1,'2026-05-22','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-TJ0611' WHERE m.n_serie='TJ0611' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-05-22' AND type='preventive');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'previsionnelle',1,'2026-07-23','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-TJ0611' WHERE m.n_serie='TJ0611' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-07-23' AND type='previsionnelle');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'previsionnelle',2,'2026-10-20','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-TJ0611' WHERE m.n_serie='TJ0611' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-10-20' AND type='previsionnelle');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'preventive',2,'2026-12-20','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-TJ0611' WHERE m.n_serie='TJ0611' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-12-20' AND type='preventive');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'preventive',1,'2026-05-22','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-TG0099' WHERE m.n_serie='TG0099' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-05-22' AND type='preventive');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'previsionnelle',1,'2026-07-23','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-TG0099' WHERE m.n_serie='TG0099' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-07-23' AND type='previsionnelle');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'previsionnelle',2,'2026-10-20','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-TG0099' WHERE m.n_serie='TG0099' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-10-20' AND type='previsionnelle');
INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut) SELECT ct.id,m.id,m.client_id,'preventive',2,'2026-12-20','planifiee' FROM machines m JOIN contrats ct ON ct.numero='CTR-KODAK-TG0099' WHERE m.n_serie='TG0099' AND NOT EXISTS (SELECT 1 FROM maintenances_planifiees WHERE machine_id=m.id AND date_prevue='2026-12-20' AND type='preventive');
INSERT INTO interventions (numero,machine_id,client_id,type,origine,priorite,statut,date_fin,description,resolution) SELECT 'INT-KODAK-TJ4886-20260128',m.id,m.client_id,'corrective','interne','normale','cloturee','2026-01-28','Dernière intervention (reprise historique)','Dernière intervention (reprise historique)' FROM machines m WHERE m.n_serie='TJ4886' AND NOT EXISTS (SELECT 1 FROM interventions WHERE numero='INT-KODAK-TJ4886-20260128');
INSERT INTO interventions (numero,machine_id,client_id,type,origine,priorite,statut,date_fin,description,resolution) SELECT 'INT-KODAK-TJ5106-20260226',m.id,m.client_id,'corrective','interne','normale','cloturee','2026-02-26','Dernière intervention (reprise historique)','Dernière intervention (reprise historique)' FROM machines m WHERE m.n_serie='TJ5106' AND NOT EXISTS (SELECT 1 FROM interventions WHERE numero='INT-KODAK-TJ5106-20260226');
INSERT INTO interventions (numero,machine_id,client_id,type,origine,priorite,statut,date_fin,description,resolution) SELECT 'INT-KODAK-TJ4802-20260202',m.id,m.client_id,'corrective','interne','normale','cloturee','2026-02-02','Dernière intervention (reprise historique)','Dernière intervention (reprise historique)' FROM machines m WHERE m.n_serie='TJ4802' AND NOT EXISTS (SELECT 1 FROM interventions WHERE numero='INT-KODAK-TJ4802-20260202');
INSERT INTO interventions (numero,machine_id,client_id,type,origine,priorite,statut,date_fin,description,resolution) SELECT 'INT-KODAK-TJ0013-20260522',m.id,m.client_id,'preventive','interne','normale','cloturee','2026-05-22','Changement filtre à air','Changement filtre à air' FROM machines m WHERE m.n_serie='TJ0013' AND NOT EXISTS (SELECT 1 FROM interventions WHERE numero='INT-KODAK-TJ0013-20260522');
