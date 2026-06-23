-- ============================================================
-- QR-MENU — Schéma de base de données
-- Restaurant pilote, conçu pour évoluer vers multi-tenant
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------- RESTAURANT (table unique pour la V1, prête pour multi-tenant) ----------
CREATE TABLE IF NOT EXISTS restaurant (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(120) NOT NULL,
    devise VARCHAR(8) NOT NULL DEFAULT 'DT',
    fuseau VARCHAR(64) NOT NULL DEFAULT 'Africa/Tunis',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- ZONES ----------
CREATE TABLE IF NOT EXISTS zones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT NOT NULL,
    nom VARCHAR(80) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (restaurant_id) REFERENCES restaurant(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- TABLES (tables physiques du restaurant) ----------
CREATE TABLE IF NOT EXISTS tables_restaurant (
    id INT AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT NOT NULL,
    zone_id INT NULL,
    numero INT NOT NULL,
    qr_token CHAR(32) NOT NULL UNIQUE,
    statut ENUM('libre','occupee','reservee') NOT NULL DEFAULT 'libre',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (restaurant_id) REFERENCES restaurant(id) ON DELETE CASCADE,
    FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE SET NULL,
    UNIQUE KEY uniq_table_resto (restaurant_id, numero)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- UTILISATEURS (serveurs + propriétaire/admin) ----------
CREATE TABLE IF NOT EXISTS utilisateurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT NOT NULL,
    nom VARCHAR(100) NOT NULL,
    email VARCHAR(160) NOT NULL UNIQUE,
    mot_de_passe_hash VARCHAR(255) NOT NULL,
    role ENUM('proprietaire','serveur') NOT NULL,
    zone_id INT NULL COMMENT 'Zone assignée si role=serveur',
    whatsapp_number VARCHAR(20) NULL,
    actif TINYINT(1) NOT NULL DEFAULT 1,
    derniere_connexion DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (restaurant_id) REFERENCES restaurant(id) ON DELETE CASCADE,
    FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- SESSIONS (tokens d'auth, plus robuste que $_SESSION seul sur shared hosting) ----------
CREATE TABLE IF NOT EXISTS sessions (
    id CHAR(64) PRIMARY KEY COMMENT 'token aléatoire',
    utilisateur_id INT NOT NULL,
    user_agent VARCHAR(255) NULL,
    ip VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- CATEGORIES DE MENU ----------
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT NOT NULL,
    nom VARCHAR(80) NOT NULL,
    ordre INT NOT NULL DEFAULT 0,
    FOREIGN KEY (restaurant_id) REFERENCES restaurant(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- ARTICLES DU MENU ----------
CREATE TABLE IF NOT EXISTS articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT NOT NULL,
    categorie_id INT NOT NULL,
    nom VARCHAR(120) NOT NULL,
    description TEXT NULL,
    prix DECIMAL(8,3) NOT NULL,
    photo_url VARCHAR(255) NULL,
    disponible TINYINT(1) NOT NULL DEFAULT 1,
    ordre INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (restaurant_id) REFERENCES restaurant(id) ON DELETE CASCADE,
    FOREIGN KEY (categorie_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- COMMANDES ----------
CREATE TABLE IF NOT EXISTS commandes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT NOT NULL,
    table_id INT NOT NULL,
    serveur_id INT NULL COMMENT 'Assigné automatiquement via zone de la table',
    code VARCHAR(10) NOT NULL COMMENT 'Référence courte ex: A412',
    statut ENUM('nouvelle','en_cours','prete','servie','annulee') NOT NULL DEFAULT 'nouvelle',
    total DECIMAL(10,3) NOT NULL DEFAULT 0,
    note_client TEXT NULL,
    whatsapp_envoye TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    prise_en_charge_at DATETIME NULL,
    prete_at DATETIME NULL,
    servie_at DATETIME NULL,
    FOREIGN KEY (restaurant_id) REFERENCES restaurant(id) ON DELETE CASCADE,
    FOREIGN KEY (table_id) REFERENCES tables_restaurant(id) ON DELETE CASCADE,
    FOREIGN KEY (serveur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL,
    INDEX idx_statut (restaurant_id, statut),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- LIGNES DE COMMANDE ----------
CREATE TABLE IF NOT EXISTS commande_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    commande_id INT NOT NULL,
    article_id INT NULL COMMENT 'NULL autorisé : si l''article est supprimé du menu plus tard, l''historique de commande reste intact',
    nom_article VARCHAR(120) NOT NULL COMMENT 'Copie au moment de la commande (historique fiable même si menu change)',
    prix_unitaire DECIMAL(8,3) NOT NULL,
    quantite INT NOT NULL DEFAULT 1,
    note TEXT NULL,
    FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE,
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- LOGS WHATSAPP (debug + audit) ----------
CREATE TABLE IF NOT EXISTS whatsapp_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    commande_id INT NOT NULL,
    destinataire VARCHAR(20) NOT NULL,
    statut ENUM('envoye','echec') NOT NULL,
    message_id VARCHAR(100) NULL,
    erreur TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- AUDIT LOG (actions admin/serveur, comme CaisseFlow) ----------
CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT NULL,
    ip VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- DONNÉES DE DÉMARRAGE (seed) — restaurant pilote "Le Médina"
-- ============================================================

INSERT INTO restaurant (nom, devise, fuseau) VALUES ('Le Médina', 'DT', 'Africa/Tunis');
SET @restaurant_id = LAST_INSERT_ID();

INSERT INTO zones (restaurant_id, nom) VALUES
    (@restaurant_id, 'Salle'),
    (@restaurant_id, 'Terrasse');
SET @zone_salle = (SELECT id FROM zones WHERE restaurant_id=@restaurant_id AND nom='Salle');
SET @zone_terrasse = (SELECT id FROM zones WHERE restaurant_id=@restaurant_id AND nom='Terrasse');

-- Tables avec tokens QR uniques (générés ici en exemple ; install.php en génère des vrais aléatoires)
INSERT INTO tables_restaurant (restaurant_id, zone_id, numero, qr_token) VALUES
    (@restaurant_id, @zone_salle, 1, MD5(CONCAT('table-1-', RAND()))),
    (@restaurant_id, @zone_salle, 2, MD5(CONCAT('table-2-', RAND()))),
    (@restaurant_id, @zone_salle, 3, MD5(CONCAT('table-3-', RAND()))),
    (@restaurant_id, @zone_salle, 4, MD5(CONCAT('table-4-', RAND()))),
    (@restaurant_id, @zone_terrasse, 5, MD5(CONCAT('table-5-', RAND()))),
    (@restaurant_id, @zone_terrasse, 6, MD5(CONCAT('table-6-', RAND()))),
    (@restaurant_id, @zone_terrasse, 7, MD5(CONCAT('table-7-', RAND()))),
    (@restaurant_id, @zone_terrasse, 8, MD5(CONCAT('table-8-', RAND()))),
    (@restaurant_id, @zone_terrasse, 9, MD5(CONCAT('table-9-', RAND())));

-- Catégories
INSERT INTO categories (restaurant_id, nom, ordre) VALUES
    (@restaurant_id, 'Entrées', 1),
    (@restaurant_id, 'Plats', 2),
    (@restaurant_id, 'Boissons', 3);

SET @cat_entrees = (SELECT id FROM categories WHERE restaurant_id=@restaurant_id AND nom='Entrées');
SET @cat_plats = (SELECT id FROM categories WHERE restaurant_id=@restaurant_id AND nom='Plats');
SET @cat_boissons = (SELECT id FROM categories WHERE restaurant_id=@restaurant_id AND nom='Boissons');

-- Articles
INSERT INTO articles (restaurant_id, categorie_id, nom, description, prix, photo_url, disponible, ordre) VALUES
    (@restaurant_id, @cat_entrees, 'Brik à l\'œuf', 'Feuille de brick croustillante, œuf coulant, thon, persil.', 6.500, 'https://images.unsplash.com/photo-1601050690597-df0568f70950?w=300&q=80', 1, 1),
    (@restaurant_id, @cat_entrees, 'Salade méchouia', 'Poivrons et tomates grillés, ail, câpres, huile d\'olive.', 5.000, 'https://images.unsplash.com/photo-1540420773420-3366772f4999?w=300&q=80', 1, 2),
    (@restaurant_id, @cat_entrees, 'Chorba frik', 'Soupe traditionnelle à l\'agneau et blé concassé.', 7.000, 'https://images.unsplash.com/photo-1547592180-85f173990554?w=300&q=80', 0, 3),
    (@restaurant_id, @cat_plats, 'Couscous royal', 'Semoule fine, agneau, merguez, poulet, légumes de saison.', 18.500, 'https://images.unsplash.com/photo-1585937421612-70a008356fbe?w=300&q=80', 1, 1),
    (@restaurant_id, @cat_plats, 'Tajine malsouka', 'Persillade d\'agneau, pois chiches, œufs, enrobée de malsouka.', 14.000, 'https://images.unsplash.com/photo-1574484284002-952d92456975?w=300&q=80', 1, 2),
    (@restaurant_id, @cat_plats, 'Poisson grillé du jour', 'Selon pêche du marché, riz tunisien et légumes grillés.', 22.000, 'https://images.unsplash.com/photo-1467003909585-2f8a72700288?w=300&q=80', 1, 3),
    (@restaurant_id, @cat_boissons, 'Citronnade maison', 'Citron pressé, menthe fraîche, sucre de canne.', 4.000, 'https://images.unsplash.com/photo-1523677011781-c91d1bbe2f9e?w=300&q=80', 1, 1),
    (@restaurant_id, @cat_boissons, 'Thé à la menthe', 'Thé vert, menthe fraîche, pignons grillés.', 3.000, 'https://images.unsplash.com/photo-1576092768241-dec231879fc3?w=300&q=80', 1, 2);

-- Utilisateurs (mot de passe par défaut défini par install.php — ici placeholder)
-- Le mot de passe en clair "ChangeMoi123!" est juste l'exemple seedé par install.php, à changer immédiatement après 1ère connexion.
INSERT INTO utilisateurs (restaurant_id, nom, email, mot_de_passe_hash, role, zone_id, whatsapp_number) VALUES
    (@restaurant_id, 'Yassine Rezgui', 'proprietaire@lemedina.tn', '$2y$10$PLACEHOLDER_HASH_REPLACED_BY_INSTALL', 'proprietaire', NULL, NULL),
    (@restaurant_id, 'Yassine R. (Serveur)', 'serveur1@lemedina.tn', '$2y$10$PLACEHOLDER_HASH_REPLACED_BY_INSTALL', 'serveur', @zone_terrasse, '+21620000000');
