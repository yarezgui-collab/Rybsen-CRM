-- ============================================================
-- CRM LABO BEN YEDDER — Données de démonstration
-- À exécuter APRÈS install.sql, via phpMyAdmin, uniquement pour
-- une démo commerciale. Sûr à exécuter plusieurs fois (idempotent
-- via WHERE NOT EXISTS, pas de doublons). Facile à identifier et
-- supprimer ensuite (voir requêtes de nettoyage en bas de fichier).
-- ============================================================

SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- 4 clients de démonstration, répartis sur les 3 canaux
-- ------------------------------------------------------------

-- 2 clients à terme
INSERT INTO clients (nom, type_client, contact_nom, telephone, email, adresse)
SELECT 'Café de la Gare', 'terme', 'M. Trabelsi', '20 123 456', 'contact@cafedelagare.tn', 'Avenue Habib Bourguiba, Tunis'
WHERE NOT EXISTS (SELECT 1 FROM clients WHERE nom = 'Café de la Gare');

INSERT INTO clients (nom, type_client, contact_nom, telephone, email, adresse)
SELECT 'Hôtel El Manar', 'terme', 'Mme Bouazizi', '71 456 789', 'achats@hotelmanar.tn', 'Rue du Lac Windermere, Tunis'
WHERE NOT EXISTS (SELECT 1 FROM clients WHERE nom = 'Hôtel El Manar');

-- 1 franchise
INSERT INTO clients (nom, type_client, contact_nom, telephone, email, adresse)
SELECT 'Franchise Ben Yedder Sfax', 'franchise', 'M. Ayari', '74 123 456', 'sfax@benyedder.tn', 'Route de Tunis, Sfax'
WHERE NOT EXISTS (SELECT 1 FROM clients WHERE nom = 'Franchise Ben Yedder Sfax');

INSERT INTO franchises (client_id, mode_paiement, territoire)
SELECT id, 'libre_choix', 'Sfax' FROM clients WHERE nom = 'Franchise Ben Yedder Sfax'
ON DUPLICATE KEY UPDATE mode_paiement = mode_paiement;

-- 1 point de vente
INSERT INTO points_vente (nom, adresse, responsable, telephone)
SELECT 'Boutique El Menzah', 'Avenue Charles Nicolle, El Menzah, Tunis', 'M. Ghariani', '71 987 654'
WHERE NOT EXISTS (SELECT 1 FROM points_vente WHERE nom = 'Boutique El Menzah');

-- ------------------------------------------------------------
-- 2 produits supplémentaires + recettes (complète les 4 déjà en base)
-- ------------------------------------------------------------
INSERT INTO produits (nom, categorie, prix_vente, unite)
SELECT 'Msemen', 'Viennoiserie', 0.700, 'pièce'
WHERE NOT EXISTS (SELECT 1 FROM produits WHERE nom = 'Msemen');

INSERT INTO produits (nom, categorie, prix_vente, unite)
SELECT 'Samsa', 'Pâtisserie traditionnelle', 1.300, 'pièce'
WHERE NOT EXISTS (SELECT 1 FROM produits WHERE nom = 'Samsa');

INSERT INTO recettes (produit_id, matiere_id, quantite_necessaire)
SELECT p.id, m.id, v.qte FROM (
  SELECT 'Msemen' pnom, 'Farine' mnom, 0.0600 qte
  UNION ALL SELECT 'Msemen', 'Beurre', 0.0150
  UNION ALL SELECT 'Samsa', 'Amandes', 0.0250
  UNION ALL SELECT 'Samsa', 'Miel', 0.0150
  UNION ALL SELECT 'Samsa', 'Farine', 0.0150
) v
JOIN produits p ON p.nom = v.pnom
JOIN matieres_premieres m ON m.nom = v.mnom
ON DUPLICATE KEY UPDATE quantite_necessaire = VALUES(quantite_necessaire);

-- ============================================================
-- Pour retirer uniquement ces données de démo plus tard :
-- ============================================================
-- DELETE FROM franchises WHERE client_id IN (SELECT id FROM clients WHERE nom='Franchise Ben Yedder Sfax');
-- DELETE FROM clients WHERE nom IN ('Café de la Gare','Hôtel El Manar','Franchise Ben Yedder Sfax');
-- DELETE FROM points_vente WHERE nom='Boutique El Menzah';
-- DELETE FROM recettes WHERE produit_id IN (SELECT id FROM produits WHERE nom IN ('Msemen','Samsa'));
-- DELETE FROM produits WHERE nom IN ('Msemen','Samsa');
-- (Ne supprime pas les commandes/factures liées si vous avez déjà fait une démo — vérifiez les dépendances avant.)
