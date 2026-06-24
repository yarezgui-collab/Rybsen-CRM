-- ============================================================
-- Schéma de base de données — Pâtisserie CRM
-- Base : u293743867_pby
-- À exécuter dans hPanel → Bases de données → phpMyAdmin → onglet SQL
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ------------------------------------------------------------
-- Table : produits
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS produits (
  id        VARCHAR(40)     NOT NULL PRIMARY KEY,
  nom       VARCHAR(120)    NOT NULL,
  prix      DECIMAL(10,3)   NOT NULL DEFAULT 0,
  categorie VARCHAR(40)     NOT NULL DEFAULT 'Autres',
  cree_le   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Table : chauffeurs (et leurs identifiants)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS chauffeurs (
  id            VARCHAR(40)   NOT NULL PRIMARY KEY,
  nom           VARCHAR(120)  NOT NULL,
  utilisateur   VARCHAR(80)   NOT NULL,
  mot_de_passe  VARCHAR(255)  NOT NULL,
  cree_le       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_utilisateur (utilisateur)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Table : clients
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS clients (
  id          VARCHAR(40)   NOT NULL PRIMARY KEY,
  nom         VARCHAR(160)  NOT NULL,
  chauffeur_id VARCHAR(40)  NOT NULL,
  cree_le     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_chauffeur (chauffeur_id),
  CONSTRAINT fk_client_chauffeur FOREIGN KEY (chauffeur_id) REFERENCES chauffeurs(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Table : livraisons (sorties du Laboratoire)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS livraisons (
  id            VARCHAR(40)     NOT NULL PRIMARY KEY,
  jour          DATE            NOT NULL,
  client_id     VARCHAR(40)     NOT NULL,
  chauffeur_id  VARCHAR(40)     NOT NULL,
  produit_id    VARCHAR(40)     NOT NULL,
  produit_nom   VARCHAR(120)    NOT NULL,   -- copie figée du nom au moment de la saisie
  quantite      INT             NOT NULL,
  prix_unitaire DECIMAL(10,3)   NOT NULL,
  cree_le       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_jour (jour),
  KEY idx_client (client_id),
  KEY idx_chauffeur_liv (chauffeur_id),
  CONSTRAINT fk_liv_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
  CONSTRAINT fk_liv_chauffeur FOREIGN KEY (chauffeur_id) REFERENCES chauffeurs(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Table : retours (articles non conformes retournés par un client,
-- toujours liés à une livraison précise)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS retours (
  id            VARCHAR(40)     NOT NULL PRIMARY KEY,
  livraison_id  VARCHAR(40)     NOT NULL,
  jour          DATE            NOT NULL,    -- jour du retour (peut différer du jour de la livraison)
  quantite      INT             NOT NULL,
  motif         VARCHAR(255)    NULL,
  cree_le       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_livraison (livraison_id),
  KEY idx_jour_retour (jour),
  CONSTRAINT fk_retour_livraison FOREIGN KEY (livraison_id) REFERENCES livraisons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Table : encaissements (argent récupéré par les chauffeurs)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS encaissements (
  id            VARCHAR(40)     NOT NULL PRIMARY KEY,
  jour          DATE            NOT NULL,
  client_id     VARCHAR(40)     NOT NULL,
  chauffeur_id  VARCHAR(40)     NOT NULL,
  montant       DECIMAL(10,3)   NOT NULL,
  cree_le       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_jour_enc (jour),
  KEY idx_client_enc (client_id),
  KEY idx_chauffeur_enc (chauffeur_id),
  CONSTRAINT fk_enc_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
  CONSTRAINT fk_enc_chauffeur FOREIGN KEY (chauffeur_id) REFERENCES chauffeurs(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Table : rapports_jour (tableaux journaliers sauvegardés, historique)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rapports_jour (
  jour        DATE            NOT NULL PRIMARY KEY,
  rempli      DECIMAL(12,3)   NOT NULL DEFAULT 0,
  recupere    DECIMAL(12,3)   NOT NULL DEFAULT 0,
  ecart       DECIMAL(12,3)   NOT NULL DEFAULT 0,
  detail_json JSON            NOT NULL,
  sauve_le    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Table : prix_client (tarifs spéciaux par client, écrasent le prix catalogue)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS prix_client (
  client_id  VARCHAR(40)   NOT NULL,
  produit_id VARCHAR(40)   NOT NULL,
  prix       DECIMAL(10,3) NOT NULL,
  PRIMARY KEY (client_id, produit_id),
  KEY idx_pc_client  (client_id),
  KEY idx_pc_produit (produit_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Table : parametres (nom établissement, monnaie, identifiants labo/propriétaire)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS parametres (
  cle     VARCHAR(60)   NOT NULL PRIMARY KEY,
  valeur  TEXT          NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Données de départ (modifiables ensuite depuis les Réglages)
-- ============================================================

INSERT INTO chauffeurs (id, nom, utilisateur, mot_de_passe) VALUES
  ('chauffeur1', 'Chauffeur 1', 'chauffeur1', '1111'),
  ('chauffeur2', 'Chauffeur 2', 'chauffeur2', '2222')
ON DUPLICATE KEY UPDATE nom = nom;

INSERT INTO produits (id, nom, prix, categorie) VALUES
  ('p1', 'Croissant', 0.500, 'Viennoiserie'),
  ('p2', 'Pain au chocolat', 0.600, 'Viennoiserie'),
  ('p3', 'Pain aux raisins', 0.700, 'Viennoiserie'),
  ('p4', 'Croissant amandes', 0.900, 'Viennoiserie')
ON DUPLICATE KEY UPDATE nom = nom;

INSERT INTO clients (id, nom, chauffeur_id) VALUES
  ('c1', 'Café de la Gare', 'chauffeur1'),
  ('c2', 'Boulangerie Centrale', 'chauffeur1'),
  ('c3', 'Hôtel El Manar', 'chauffeur2'),
  ('c4', 'Épicerie du Coin', 'chauffeur2')
ON DUPLICATE KEY UPDATE nom = nom;

INSERT INTO parametres (cle, valeur) VALUES
  ('business',           'Ma Pâtisserie'),
  ('currency',            'DT'),
  ('auth_labo_user',      'labo'),
  ('auth_labo_pass',      '0000'),
  ('auth_proprio_user',   'admin'),
  ('auth_proprio_pass',   '9999')
ON DUPLICATE KEY UPDATE valeur = valeur;
