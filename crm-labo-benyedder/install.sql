-- ============================================================
-- CRM LABO BEN YEDDER — Script d'installation MySQL
-- Traiteur / Pâtisserie — Labo, franchises, points de vente
-- À exécuter dans hPanel → Bases de données → phpMyAdmin → onglet SQL
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
  role ENUM('admin','labo','production','franchise','point_vente','client_terme') NOT NULL DEFAULT 'client_terme',
  -- Un utilisateur "franchise" ou "client_terme" est rattaché à un client ;
  -- un utilisateur "point_vente" est rattaché à un point de vente.
  client_id INT NULL,
  point_vente_id INT NULL,
  avatar CHAR(2) DEFAULT 'BY',
  actif TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- CLIENTS (à terme + franchises) & POINTS DE VENTE
-- ============================================================
CREATE TABLE IF NOT EXISTS clients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(160) NOT NULL,
  type_client ENUM('terme','franchise') NOT NULL DEFAULT 'terme',
  contact_nom VARCHAR(150),
  telephone VARCHAR(50),
  email VARCHAR(150),
  adresse VARCHAR(255),
  actif TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS franchises (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL UNIQUE,
  mode_paiement ENUM('comptant','terme','libre_choix') NOT NULL DEFAULT 'libre_choix',
  territoire VARCHAR(150),
  CONSTRAINT fk_franchise_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS points_vente (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(160) NOT NULL,
  adresse VARCHAR(255),
  responsable VARCHAR(150),
  telephone VARCHAR(50),
  actif TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ajout conditionnel des FK (évite une erreur si ce script est exécuté plusieurs fois)
DELIMITER $$
CREATE PROCEDURE add_user_fk_if_missing()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND CONSTRAINT_NAME='fk_user_client') THEN
    ALTER TABLE users ADD CONSTRAINT fk_user_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND CONSTRAINT_NAME='fk_user_pv') THEN
    ALTER TABLE users ADD CONSTRAINT fk_user_pv FOREIGN KEY (point_vente_id) REFERENCES points_vente(id) ON DELETE SET NULL;
  END IF;
END$$
DELIMITER ;
CALL add_user_fk_if_missing();
DROP PROCEDURE add_user_fk_if_missing;

-- ============================================================
-- FOURNISSEURS & MATIÈRES PREMIÈRES
-- ============================================================
CREATE TABLE IF NOT EXISTS fournisseurs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(160) NOT NULL,
  contact VARCHAR(150),
  telephone VARCHAR(50),
  email VARCHAR(150)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS matieres_premieres (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(120) NOT NULL,
  unite VARCHAR(20) NOT NULL DEFAULT 'kg',
  stock_actuel DECIMAL(12,3) NOT NULL DEFAULT 0,
  seuil_alerte DECIMAL(12,3) NOT NULL DEFAULT 0,
  prix_unitaire DECIMAL(10,3) NOT NULL DEFAULT 0,
  fournisseur_id INT NULL,
  actif TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_mp_fournisseur FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- CATALOGUE PRODUITS & RECETTES (nomenclature / BOM)
-- ============================================================
CREATE TABLE IF NOT EXISTS produits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(120) NOT NULL,
  categorie VARCHAR(60) NOT NULL DEFAULT 'Viennoiserie',
  prix_vente DECIMAL(10,3) NOT NULL DEFAULT 0,
  unite VARCHAR(20) NOT NULL DEFAULT 'pièce',
  actif TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS recettes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  produit_id INT NOT NULL,
  matiere_id INT NOT NULL,
  quantite_necessaire DECIMAL(10,4) NOT NULL,
  UNIQUE KEY uniq_recette (produit_id, matiere_id),
  CONSTRAINT fk_recette_produit FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE CASCADE,
  CONSTRAINT fk_recette_matiere FOREIGN KEY (matiere_id) REFERENCES matieres_premieres(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- ÉVÉNEMENTS SPÉCIAUX (saisonnalité, volet traiteur)
-- ============================================================
CREATE TABLE IF NOT EXISTS evenements_speciaux (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(160) NOT NULL,
  type ENUM('ramadan','aid','mariage','autre') NOT NULL DEFAULT 'autre',
  date_debut DATE NOT NULL,
  date_fin DATE NOT NULL,
  notes TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- COMMANDES (3 canaux) & LIGNES
-- ============================================================
CREATE TABLE IF NOT EXISTS commandes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  canal ENUM('terme','franchise','point_vente') NOT NULL,
  type ENUM('reguliere','ponctuelle','evenementielle') NOT NULL DEFAULT 'ponctuelle',
  client_id INT NULL,        -- rempli si canal = terme ou franchise
  point_vente_id INT NULL,   -- rempli si canal = point_vente
  evenement_id INT NULL,
  acompte DECIMAL(10,3) NULL,
  statut ENUM('brouillon','confirmee','en_production','livree','facturee','annulee') NOT NULL DEFAULT 'brouillon',
  date_commande DATE NOT NULL,
  date_livraison_prevue DATE NULL,
  notes TEXT,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_cmd_statut (statut),
  KEY idx_cmd_date (date_commande),
  CONSTRAINT fk_cmd_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cmd_pv FOREIGN KEY (point_vente_id) REFERENCES points_vente(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cmd_evenement FOREIGN KEY (evenement_id) REFERENCES evenements_speciaux(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lignes_commande (
  id INT AUTO_INCREMENT PRIMARY KEY,
  commande_id INT NOT NULL,
  produit_id INT NOT NULL,
  quantite DECIMAL(10,3) NOT NULL,
  prix_unitaire DECIMAL(10,3) NOT NULL,
  KEY idx_lc_commande (commande_id),
  CONSTRAINT fk_lc_commande FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE,
  CONSTRAINT fk_lc_produit FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- PRODUCTION — agrégation des commandes par produit
-- ============================================================
CREATE TABLE IF NOT EXISTS ordres_fabrication (
  id INT AUTO_INCREMENT PRIMARY KEY,
  date_ordre DATE NOT NULL,
  statut ENUM('planifie','en_cours','termine') NOT NULL DEFAULT 'planifie',
  site_production VARCHAR(150) DEFAULT 'Site de production',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lignes_ordre_fabrication (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ordre_id INT NOT NULL,
  produit_id INT NOT NULL,
  quantite_totale DECIMAL(10,3) NOT NULL,
  KEY idx_lof_ordre (ordre_id),
  CONSTRAINT fk_lof_ordre FOREIGN KEY (ordre_id) REFERENCES ordres_fabrication(id) ON DELETE CASCADE,
  CONSTRAINT fk_lof_produit FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Traçabilité : quelles commandes composent chaque ordre de fabrication
CREATE TABLE IF NOT EXISTS ordre_fabrication_commandes (
  ordre_id INT NOT NULL,
  commande_id INT NOT NULL,
  PRIMARY KEY (ordre_id, commande_id),
  CONSTRAINT fk_ofc_ordre FOREIGN KEY (ordre_id) REFERENCES ordres_fabrication(id) ON DELETE CASCADE,
  CONSTRAINT fk_ofc_commande FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Lots de production — traçabilité & DLC
CREATE TABLE IF NOT EXISTS lots (
  id INT AUTO_INCREMENT PRIMARY KEY,
  produit_id INT NOT NULL,
  ordre_id INT NULL,
  numero_lot VARCHAR(60) NOT NULL UNIQUE,
  quantite_produite DECIMAL(10,3) NOT NULL,
  date_fabrication DATE NOT NULL,
  date_peremption DATE NULL,
  KEY idx_lot_produit (produit_id),
  CONSTRAINT fk_lot_produit FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE RESTRICT,
  CONSTRAINT fk_lot_ordre FOREIGN KEY (ordre_id) REFERENCES ordres_fabrication(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- STOCK — mouvements matières premières & produits finis
-- ============================================================
CREATE TABLE IF NOT EXISTS mouvements_stock_matieres (
  id INT AUTO_INCREMENT PRIMARY KEY,
  matiere_id INT NOT NULL,
  type_mouvement ENUM('entree','sortie','ajustement') NOT NULL,
  quantite DECIMAL(12,3) NOT NULL,
  origine ENUM('production','achat','correction') NOT NULL DEFAULT 'correction',
  reference_id INT NULL,
  date_mouvement DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  notes VARCHAR(255),
  KEY idx_msm_matiere (matiere_id),
  CONSTRAINT fk_msm_matiere FOREIGN KEY (matiere_id) REFERENCES matieres_premieres(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mouvements_stock_produits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  produit_id INT NOT NULL,
  type_mouvement ENUM('entree','sortie','ajustement') NOT NULL,
  quantite DECIMAL(10,3) NOT NULL,
  origine ENUM('production','vente','dispatch','perte','correction') NOT NULL DEFAULT 'correction',
  reference_id INT NULL,
  date_mouvement DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  notes VARCHAR(255),
  KEY idx_msp_produit (produit_id),
  CONSTRAINT fk_msp_produit FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Stock local vitrine par point de vente
CREATE TABLE IF NOT EXISTS stocks_points_vente (
  point_vente_id INT NOT NULL,
  produit_id INT NOT NULL,
  quantite DECIMAL(10,3) NOT NULL DEFAULT 0,
  PRIMARY KEY (point_vente_id, produit_id),
  CONSTRAINT fk_spv_pv FOREIGN KEY (point_vente_id) REFERENCES points_vente(id) ON DELETE CASCADE,
  CONSTRAINT fk_spv_produit FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Invendus / pertes / casse
CREATE TABLE IF NOT EXISTS pertes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  source ENUM('point_vente','livraison','production') NOT NULL,
  point_vente_id INT NULL,
  produit_id INT NOT NULL,
  quantite DECIMAL(10,3) NOT NULL,
  motif VARCHAR(255),
  date_perte DATE NOT NULL,
  CONSTRAINT fk_perte_pv FOREIGN KEY (point_vente_id) REFERENCES points_vente(id) ON DELETE SET NULL,
  CONSTRAINT fk_perte_produit FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- DISPATCH / LIVRAISON
-- ============================================================
CREATE TABLE IF NOT EXISTS livraisons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ordre_id INT NULL,
  canal ENUM('terme','franchise','point_vente') NOT NULL,
  destination_client_id INT NULL,
  destination_point_vente_id INT NULL,
  date_livraison DATE NOT NULL,
  statut ENUM('preparee','en_route','livree') NOT NULL DEFAULT 'preparee',
  CONSTRAINT fk_liv_ordre FOREIGN KEY (ordre_id) REFERENCES ordres_fabrication(id) ON DELETE SET NULL,
  CONSTRAINT fk_liv_client FOREIGN KEY (destination_client_id) REFERENCES clients(id) ON DELETE SET NULL,
  CONSTRAINT fk_liv_pv FOREIGN KEY (destination_point_vente_id) REFERENCES points_vente(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lignes_livraison (
  id INT AUTO_INCREMENT PRIMARY KEY,
  livraison_id INT NOT NULL,
  commande_id INT NOT NULL,
  produit_id INT NOT NULL,
  quantite DECIMAL(10,3) NOT NULL,
  lot_id INT NULL,
  KEY idx_ll_livraison (livraison_id),
  CONSTRAINT fk_ll_livraison FOREIGN KEY (livraison_id) REFERENCES livraisons(id) ON DELETE CASCADE,
  CONSTRAINT fk_ll_commande FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE,
  CONSTRAINT fk_ll_produit FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ll_lot FOREIGN KEY (lot_id) REFERENCES lots(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- FACTURATION & PAIEMENT
-- ============================================================
CREATE TABLE IF NOT EXISTS factures (
  id INT AUTO_INCREMENT PRIMARY KEY,
  numero VARCHAR(40) NOT NULL UNIQUE,
  client_id INT NULL,
  point_vente_id INT NULL,
  commande_id INT NULL,
  montant_ht DECIMAL(10,3) NOT NULL DEFAULT 0,
  taux_tva DECIMAL(5,2) NOT NULL DEFAULT 19,
  montant_ttc DECIMAL(10,3) NOT NULL DEFAULT 0,
  mode_paiement ENUM('comptant','terme') NOT NULL DEFAULT 'comptant',
  statut ENUM('brouillon','emise','partiellement_payee','payee','impayee') NOT NULL DEFAULT 'brouillon',
  date_emission DATE NOT NULL,
  date_echeance DATE NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_fact_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
  CONSTRAINT fk_fact_pv FOREIGN KEY (point_vente_id) REFERENCES points_vente(id) ON DELETE SET NULL,
  CONSTRAINT fk_fact_commande FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS paiements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  facture_id INT NOT NULL,
  montant DECIMAL(10,3) NOT NULL,
  date_paiement DATE NOT NULL,
  mode ENUM('especes','carte','cheque','virement') NOT NULL DEFAULT 'especes',
  reference VARCHAR(100),
  notes VARCHAR(255),
  KEY idx_pai_facture (facture_id),
  CONSTRAINT fk_pai_facture FOREIGN KEY (facture_id) REFERENCES factures(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Déclaration de paiement par une franchise/client à terme depuis son portail —
-- reste "en_attente" tant que l'admin/labo n'a pas validé (création réelle d'un
-- mouvement dans `paiements`) ou rejeté. Jamais de mise à jour directe du solde.
CREATE TABLE IF NOT EXISTS declarations_paiement (
  id INT AUTO_INCREMENT PRIMARY KEY,
  facture_id INT NOT NULL,
  montant DECIMAL(10,3) NOT NULL,
  date_declaration DATE NOT NULL,
  mode ENUM('especes','carte','cheque','virement') NOT NULL DEFAULT 'virement',
  reference VARCHAR(100),
  notes VARCHAR(255),
  statut ENUM('en_attente','validee','rejetee') NOT NULL DEFAULT 'en_attente',
  declare_par INT NULL,
  valide_par INT NULL,
  valide_le DATETIME NULL,
  paiement_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_decl_facture (facture_id),
  KEY idx_decl_statut (statut),
  CONSTRAINT fk_decl_facture FOREIGN KEY (facture_id) REFERENCES factures(id) ON DELETE CASCADE,
  CONSTRAINT fk_decl_declare_par FOREIGN KEY (declare_par) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_decl_valide_par FOREIGN KEY (valide_par) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_decl_paiement FOREIGN KEY (paiement_id) REFERENCES paiements(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- PARAMÈTRES (nom établissement, devise, feature flags admin)
-- ============================================================
CREATE TABLE IF NOT EXISTS parametres (
  cle VARCHAR(60) NOT NULL PRIMARY KEY,
  valeur TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- ÉVOLUTION v2 — cuisines de production, catalogue par compte,
-- seuils configurables, stock temps réel par entité, inventaires
-- (idempotent : ce bloc peut être rejoué sans risque de doublon
--  ni d'écrasement des données existantes)
-- ============================================================

-- Cuisines / ateliers de production (viennoiserie, libanais, glacier, …)
CREATE TABLE IF NOT EXISTS cuisines_production (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(120) NOT NULL,
  description VARCHAR(255),
  actif TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Catégories de produits — liste gérée par l'admin, assignée à une cuisine par défaut.
-- (produits.categorie reste un libellé texte relié à categories.nom par convention.)
CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(80) NOT NULL UNIQUE,
  cuisine_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cat_cuisine FOREIGN KEY (cuisine_id) REFERENCES cuisines_production(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Catalogue autorisé par compte externe. Règle : si une cible n'a AUCUNE ligne ici,
-- elle voit le catalogue complet (comportement par défaut) ; dès qu'elle a ≥1 ligne,
-- elle ne voit QUE les produits listés.
CREATE TABLE IF NOT EXISTS catalogue_autorise (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cible_type ENUM('client','point_vente') NOT NULL,
  cible_id INT NOT NULL,
  produit_id INT NOT NULL,
  UNIQUE KEY uniq_catalogue (cible_type, cible_id, produit_id),
  KEY idx_ca_cible (cible_type, cible_id),
  CONSTRAINT fk_ca_produit FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Stock détenu par les clients à terme / franchises (livré − consommé/retour) pour visibilité admin.
CREATE TABLE IF NOT EXISTS stocks_clients (
  client_id INT NOT NULL,
  produit_id INT NOT NULL,
  quantite DECIMAL(10,3) NOT NULL DEFAULT 0,
  PRIMARY KEY (client_id, produit_id),
  CONSTRAINT fk_sc_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
  CONSTRAINT fk_sc_produit FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inventaires physiques rapides (tous périmètres : labo, point de vente, client)
CREATE TABLE IF NOT EXISTS inventaires (
  id INT AUTO_INCREMENT PRIMARY KEY,
  perimetre ENUM('labo','point_vente','client') NOT NULL,
  point_vente_id INT NULL,
  client_id INT NULL,
  date_inventaire DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  realise_par INT NULL,
  notes VARCHAR(255),
  CONSTRAINT fk_inv_pv FOREIGN KEY (point_vente_id) REFERENCES points_vente(id) ON DELETE CASCADE,
  CONSTRAINT fk_inv_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
  CONSTRAINT fk_inv_user FOREIGN KEY (realise_par) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inventaire_lignes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  inventaire_id INT NOT NULL,
  produit_id INT NOT NULL,
  quantite_theorique DECIMAL(10,3) NOT NULL DEFAULT 0,
  quantite_physique DECIMAL(10,3) NOT NULL DEFAULT 0,
  ecart DECIMAL(10,3) NOT NULL DEFAULT 0,
  KEY idx_invl_inv (inventaire_id),
  CONSTRAINT fk_invl_inv FOREIGN KEY (inventaire_id) REFERENCES inventaires(id) ON DELETE CASCADE,
  CONSTRAINT fk_invl_produit FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ajout conditionnel des nouvelles colonnes sur les tables existantes (idempotent)
DROP PROCEDURE IF EXISTS upgrade_schema_v2;
DELIMITER $$
CREATE PROCEDURE upgrade_schema_v2()
BEGIN
  -- Seuils d'alerte configurables (quantité absolue OU % d'un stock de référence)
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='matieres_premieres' AND COLUMN_NAME='seuil_mode') THEN
    ALTER TABLE matieres_premieres
      ADD COLUMN seuil_mode ENUM('quantite','pourcentage') NOT NULL DEFAULT 'quantite',
      ADD COLUMN seuil_pourcentage DECIMAL(5,2) NOT NULL DEFAULT 0,
      ADD COLUMN stock_reference DECIMAL(12,3) NOT NULL DEFAULT 0;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='produits' AND COLUMN_NAME='seuil_mode') THEN
    ALTER TABLE produits
      ADD COLUMN seuil_mode ENUM('quantite','pourcentage') NOT NULL DEFAULT 'quantite',
      ADD COLUMN seuil_quantite DECIMAL(10,3) NOT NULL DEFAULT 0,
      ADD COLUMN seuil_pourcentage DECIMAL(5,2) NOT NULL DEFAULT 0,
      ADD COLUMN stock_reference DECIMAL(10,3) NOT NULL DEFAULT 0;
  END IF;
  -- Rattachement d'un utilisateur "production" à une cuisine
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='cuisine_id') THEN
    ALTER TABLE users ADD COLUMN cuisine_id INT NULL;
    ALTER TABLE users ADD CONSTRAINT fk_user_cuisine FOREIGN KEY (cuisine_id) REFERENCES cuisines_production(id) ON DELETE SET NULL;
  END IF;
  -- Cuisine attribuée à un ordre de fabrication (par défaut selon la catégorie, surchargeable)
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ordres_fabrication' AND COLUMN_NAME='cuisine_id') THEN
    ALTER TABLE ordres_fabrication ADD COLUMN cuisine_id INT NULL;
    ALTER TABLE ordres_fabrication ADD CONSTRAINT fk_of_cuisine FOREIGN KEY (cuisine_id) REFERENCES cuisines_production(id) ON DELETE SET NULL;
  END IF;
  -- Motif de perte : casse / périmé (sortie de stock) vs invendu (conservé/stocké)
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pertes' AND COLUMN_NAME='type_perte') THEN
    ALTER TABLE pertes ADD COLUMN type_perte ENUM('casse','perime','invendu') NOT NULL DEFAULT 'casse';
  END IF;
  -- Référence client (UUID) d'une vente : garantit l'idempotence de la synchro hors-ligne
  -- (une même vente encaissée hors-ligne puis synchronisée n'est jamais enregistrée deux fois).
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='factures' AND COLUMN_NAME='client_ref') THEN
    ALTER TABLE factures ADD COLUMN client_ref VARCHAR(64) NULL,
      ADD UNIQUE KEY uniq_facture_client_ref (client_ref);
  END IF;
END$$
DELIMITER ;
CALL upgrade_schema_v2();
DROP PROCEDURE upgrade_schema_v2;

-- Cuisines de départ (idempotent)
INSERT INTO cuisines_production (nom, description)
SELECT * FROM (SELECT 'Viennoiserie' nom, 'Croissants, pains au chocolat, feuilletés' description) v
WHERE NOT EXISTS (SELECT 1 FROM cuisines_production WHERE nom = v.nom);
INSERT INTO cuisines_production (nom, description)
SELECT * FROM (SELECT 'Pâtisserie traditionnelle', 'Baklawa, kaak, cornes de gazelle') v
WHERE NOT EXISTS (SELECT 1 FROM cuisines_production WHERE nom = 'Pâtisserie traditionnelle');
INSERT INTO cuisines_production (nom, description)
SELECT * FROM (SELECT 'Glacier', 'Glaces et desserts glacés') v
WHERE NOT EXISTS (SELECT 1 FROM cuisines_production WHERE nom = 'Glacier');

-- Catégories connues rattachées à leur cuisine (idempotent)
INSERT INTO categories (nom, cuisine_id)
SELECT v.nom, (SELECT id FROM cuisines_production WHERE nom = v.cuis)
FROM (SELECT 'Viennoiserie' nom, 'Viennoiserie' cuis
      UNION ALL SELECT 'Pâtisserie traditionnelle', 'Pâtisserie traditionnelle') v
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE nom = v.nom);

-- Rattrape toute autre catégorie déjà présente dans le catalogue (cuisine non assignée)
INSERT INTO categories (nom)
SELECT DISTINCT p.categorie FROM produits p
WHERE p.categorie IS NOT NULL AND p.categorie <> ''
  AND NOT EXISTS (SELECT 1 FROM categories c WHERE c.nom = p.categorie);

-- ============================================================
-- VUES — statistiques & analyse
-- ============================================================

-- Coût matière et marge estimée par produit (à partir de la recette)
CREATE OR REPLACE VIEW v_marge_produits AS
SELECT
  p.id AS produit_id,
  p.nom,
  p.prix_vente,
  COALESCE(SUM(r.quantite_necessaire * m.prix_unitaire), 0) AS cout_matiere,
  p.prix_vente - COALESCE(SUM(r.quantite_necessaire * m.prix_unitaire), 0) AS marge_estimee
FROM produits p
LEFT JOIN recettes r ON r.produit_id = p.id
LEFT JOIN matieres_premieres m ON m.id = r.matiere_id
GROUP BY p.id, p.nom, p.prix_vente;

-- Matières premières sous leur seuil d'alerte.
-- Deux modes au choix par matière : seuil en quantité absolue (seuil_alerte),
-- ou seuil en pourcentage d'un stock de référence (stock_reference * seuil_pourcentage%).
CREATE OR REPLACE VIEW v_stock_bas AS
SELECT * FROM matieres_premieres
WHERE actif = 1 AND (
  (seuil_mode = 'quantite'   AND stock_actuel <= seuil_alerte)
  OR (seuil_mode = 'pourcentage' AND stock_reference > 0 AND stock_actuel <= stock_reference * seuil_pourcentage / 100)
);

-- Stock produits finis (grand livre des mouvements, pas de colonne stock_actuel dédiée)
CREATE OR REPLACE VIEW v_stock_produits AS
SELECT
  p.id AS produit_id,
  p.nom,
  COALESCE(SUM(CASE
    WHEN m.type_mouvement = 'entree' THEN m.quantite
    WHEN m.type_mouvement = 'sortie' THEN -m.quantite
    ELSE m.quantite
  END), 0) AS stock_actuel
FROM produits p
LEFT JOIN mouvements_stock_produits m ON m.produit_id = p.id
GROUP BY p.id, p.nom;

-- Encours des clients à terme (factures émises non soldées)
CREATE OR REPLACE VIEW v_encours_clients AS
SELECT
  c.id AS client_id,
  c.nom,
  COALESCE(SUM(f.montant_ttc - COALESCE(p.paye, 0)), 0) AS encours
FROM clients c
LEFT JOIN factures f ON f.client_id = c.id AND f.mode_paiement = 'terme' AND f.statut != 'brouillon'
LEFT JOIN (
  SELECT facture_id, SUM(montant) AS paye FROM paiements GROUP BY facture_id
) p ON p.facture_id = f.id
GROUP BY c.id, c.nom;

-- ============================================================
-- DONNÉES DE DÉPART
-- ============================================================

-- Compte admin initial — mot de passe temporaire: BenYedder2026! (à changer après 1re connexion)
INSERT INTO users (nom, email, password_hash, role, avatar) VALUES
('Administrateur', 'admin@benyedder.tn', '$2y$12$D.Dgx.doHV.ZLZ3sxRRgPeE4xVnTwfU6.UseVz/eLSkclvRmaKGSy', 'admin', 'BY')
ON DUPLICATE KEY UPDATE nom = nom;

INSERT INTO parametres (cle, valeur) VALUES
('business', 'Traiteur Pâtisserie Ben Yedder'),
('currency', 'DT'),
('tva_defaut', '19'),
('feature_stock_matieres', '1'),
('feature_traceabilite_lots', '1'),
('feature_pertes', '1'),
('feature_evenementiel', '1')
ON DUPLICATE KEY UPDATE valeur = valeur;

-- Note: nom n'est pas une clé UNIQUE sur ces tables, donc chaque ligne est
-- insérée uniquement si elle n'existe pas déjà (évite les doublons si ce
-- script est exécuté plusieurs fois).
INSERT INTO matieres_premieres (nom, unite, stock_actuel, seuil_alerte, prix_unitaire)
SELECT * FROM (SELECT 'Farine' nom, 'kg' unite, 200.000 stock_actuel, 30.000 seuil_alerte, 1.200 prix_unitaire) v
WHERE NOT EXISTS (SELECT 1 FROM matieres_premieres WHERE nom = v.nom);
INSERT INTO matieres_premieres (nom, unite, stock_actuel, seuil_alerte, prix_unitaire)
SELECT * FROM (SELECT 'Beurre', 'kg', 80.000, 15.000, 14.500) v
WHERE NOT EXISTS (SELECT 1 FROM matieres_premieres WHERE nom = 'Beurre');
INSERT INTO matieres_premieres (nom, unite, stock_actuel, seuil_alerte, prix_unitaire)
SELECT * FROM (SELECT 'Sucre', 'kg', 100.000, 20.000, 2.100) v
WHERE NOT EXISTS (SELECT 1 FROM matieres_premieres WHERE nom = 'Sucre');
INSERT INTO matieres_premieres (nom, unite, stock_actuel, seuil_alerte, prix_unitaire)
SELECT * FROM (SELECT 'Œufs', 'unité', 500.000, 100.000, 0.350) v
WHERE NOT EXISTS (SELECT 1 FROM matieres_premieres WHERE nom = 'Œufs');
INSERT INTO matieres_premieres (nom, unite, stock_actuel, seuil_alerte, prix_unitaire)
SELECT * FROM (SELECT 'Amandes', 'kg', 40.000, 8.000, 22.000) v
WHERE NOT EXISTS (SELECT 1 FROM matieres_premieres WHERE nom = 'Amandes');
INSERT INTO matieres_premieres (nom, unite, stock_actuel, seuil_alerte, prix_unitaire)
SELECT * FROM (SELECT 'Miel', 'kg', 25.000, 5.000, 18.000) v
WHERE NOT EXISTS (SELECT 1 FROM matieres_premieres WHERE nom = 'Miel');

INSERT INTO produits (nom, categorie, prix_vente, unite)
SELECT * FROM (SELECT 'Croissant' nom, 'Viennoiserie' categorie, 0.900 prix_vente, 'pièce' unite) v
WHERE NOT EXISTS (SELECT 1 FROM produits WHERE nom = 'Croissant');
INSERT INTO produits (nom, categorie, prix_vente, unite)
SELECT * FROM (SELECT 'Pain au chocolat', 'Viennoiserie', 1.000, 'pièce') v
WHERE NOT EXISTS (SELECT 1 FROM produits WHERE nom = 'Pain au chocolat');
INSERT INTO produits (nom, categorie, prix_vente, unite)
SELECT * FROM (SELECT 'Baklawa', 'Pâtisserie traditionnelle', 1.500, 'pièce') v
WHERE NOT EXISTS (SELECT 1 FROM produits WHERE nom = 'Baklawa');
INSERT INTO produits (nom, categorie, prix_vente, unite)
SELECT * FROM (SELECT 'Kaak Warka', 'Pâtisserie traditionnelle', 1.200, 'pièce') v
WHERE NOT EXISTS (SELECT 1 FROM produits WHERE nom = 'Kaak Warka');

INSERT INTO recettes (produit_id, matiere_id, quantite_necessaire)
SELECT p.id, m.id, v.qte FROM (
  SELECT 'Croissant' pnom, 'Farine' mnom, 0.0500 qte
  UNION ALL SELECT 'Croissant', 'Beurre', 0.0250
  UNION ALL SELECT 'Croissant', 'Sucre', 0.0050
  UNION ALL SELECT 'Baklawa', 'Amandes', 0.0300
  UNION ALL SELECT 'Baklawa', 'Miel', 0.0200
  UNION ALL SELECT 'Baklawa', 'Farine', 0.0200
) v
JOIN produits p ON p.nom = v.pnom
JOIN matieres_premieres m ON m.nom = v.mnom
ON DUPLICATE KEY UPDATE quantite_necessaire = VALUES(quantite_necessaire);
