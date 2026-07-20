# RYBSEN CRM — Instructions Claude Code

Tu travailles sur le projet CRM déployé sur crm.rybsen.com (Hostinger).

## Dépôt GitHub
- Repo : yarezgui-collab/Rybsen-CRM
- Branche de travail : créer une branche depuis main (ex: feat/crm-rybsen-xxx)
- Ne jamais pousser directement sur main sans PR

## Déploiement SFTP automatique
- Le déploiement se fait via GitHub Actions au merge sur main
- Hôte : 194.36.184.184 · Port : 65002
- Utilisateur SFTP : u293743867.crm.rybsen.com
- Home SFTP = /home/u293743867/domains/crm.rybsen.com/public_html (destination lftp : ".")
- URL prod : https://crm.rybsen.com
- Secrets GitHub requis : CRM_SFTP_PASSWORD (dans yarezgui-collab/Rybsen-CRM → Settings → Secrets)

## Workflow GitHub Actions
- Fichier : .github/workflows/deploy-crm-rybsen.yml
- Déclenché sur push vers main (paths: crm-rybsen/**)
- Utilise lftp mirror pour sync récursive du dossier crm-rybsen/
- Exclut : config.php, *.sql, .git*, config.example.php

## Structure du projet
- Dossier source : crm-rybsen/ à la racine du repo
- Stack : PHP 8+ PDO MySQL sur Hostinger shared hosting
- Modules dans crm-rybsen/modules/
- API centralisée : crm-rybsen/api/api.php
- Assets : crm-rybsen/assets/ (style.css, app.js)
- Layout partagé : crm-rybsen/includes/header.php + footer.php

## Fichiers SENSIBLES — ne jamais commiter
- crm-rybsen/config.php (credentials MySQL)
- Le .gitignore dans crm-rybsen/ exclut déjà config.php

## Base de données MySQL (Hostinger)
- Base : u293743867_crmrybsen
- Encodage : utf8mb4
- Décimales monétaires : DECIMAL(10,3)
- Le config.php sur le serveur contient host / user / pass / dbname

## Contraintes critiques
1. config.php ne doit JAMAIS être commité dans git
2. Les credentials SFTP restent uniquement en secrets GitHub Actions
3. Toujours créer une branche de travail, merger via PR vers main
4. Ne jamais recalculer ou écraser des valeurs monétaires existantes en base
5. Utiliser RYBSEN.escape() pour tout affichage de données utilisateur (anti-XSS)
6. Pattern JS : loadX() → applyFiltersX() → renderX(data)

---

# Startup.TN CRM — Instructions Claude Code

## Dépôt GitHub
- Repo : yarezgui-collab/Rybsen-CRM
- Dossier source : crm-startup/
- Ne jamais pousser directement sur `main` sans PR

## Déploiement SFTP automatique
- Hôte : 194.36.184.184 · Port : 65002
- Utilisateur : u293743867.startup.rybsen.fr
- Chemin distant : domains/rybsen.fr/public_html/startup
- URL prod : https://startup.rybsen.fr/startup
- Secrets GitHub requis : STARTUP_SFTP_HOST / STARTUP_SFTP_PORT / STARTUP_SFTP_USER / STARTUP_SFTP_PASSWORD

## Workflow GitHub Actions
- Fichier : .github/workflows/deploy-startup.yml
- Déclenché sur push `main` (paths: `crm-startup/**`)
- Déploie tous les fichiers PHP, SQL et .htaccess sauf `config.php`
- `config.php` est géré manuellement sur le serveur (contient credentials DB)

## Contraintes critiques startup
1. `crm-startup/config.php` ne doit JAMAIS être commité dans git
2. Credentials SFTP uniquement en secrets GitHub Actions (préfixe STARTUP_)
3. Toujours créer une branche feature, merger via PR → main
4. Appliquer migration_v3.sql manuellement via phpMyAdmin au premier déploiement

---

# CRM Labo Ben Yedder — Instructions Claude Code

Traiteur Pâtisserie Ben Yedder : labo de fabrication distribuant vers clients à terme,
franchises et points de vente. Voir `crm-labo-benyedder/README.md` pour le contexte métier complet.

## Dépôt GitHub
- Repo : yarezgui-collab/Rybsen-CRM
- Dossier source : crm-labo-benyedder/
- Ne jamais pousser directement sur `main` sans PR

## Déploiement SFTP automatique — 100% automatisé, y compris config.php
- Fichier : .github/workflows/deploy-crm-labo-benyedder.yml
- Déclenché sur push `main` (paths: `crm-labo-benyedder/**`)
- URL prod : https://tby.rybsen.fr
- Chemin distant : domains/rybsen.fr/public_html/tby (même compte SFTP que crm-patisserie)
- config.php n'est jamais commité : généré à chaque run depuis des secrets GitHub, puis déployé
  par SFTP avec le reste (DB_HOST fixé à 'localhost' dans le workflow)
- Après le SFTP, le workflow appelle run_demo_data.php (protégé par jeton) pour charger les
  données de démonstration — idempotent, sans risque à chaque déploiement
- Secrets GitHub requis :
  - Réutilisés (déjà existants) : PATISSERIE_SFTP_HOST / PATISSERIE_SFTP_PORT / PATISSERIE_SFTP_USER / PATISSERIE_SFTP_PASSWORD
  - Propres à ce projet (créés) : BENYEDDER_DB_NAME (= nom base = nom utilisateur MySQL, u293743867_Tby) / BENYEDDER_DB_PASSWORD
  - Propre à ce projet (à créer) : BENYEDDER_MIGRATION_TOKEN (jeton aléatoire protégeant run_demo_data.php)
- Exclut du déploiement : *.sql, config.example.php, .gitignore
- Le schéma est appliqué automatiquement à chaque déploiement via run_migration.php (protégé par
  BENYEDDER_MIGRATION_TOKEN) : ce endpoint exécute install.sql côté serveur, en respectant les
  DELIMITER (procédures stockées). Idempotent — CREATE TABLE IF NOT EXISTS, procédures
  conditionnelles (information_schema) pour les colonnes, CREATE OR REPLACE pour les vues ; ne
  crée que ce qui manque, n'écrase jamais les données existantes. Le workflow bloque le déploiement
  si une requête échoue ("ok":false). run_migration.php est appelé AVANT run_demo_data.php.
- *.sql (dont install.sql) n'est pas déployé par SFTP : run_migration.php lit install.sql… donc
  install.sql DOIT être déployé. Il est inclus dans le SFTP (l'exclusion *.sql concerne les autres
  dumps) — vérifier que install.sql est bien présent sur le serveur pour que la migration fonctionne.

## Structure du projet
- Stack : PHP 8+ PDO MySQL sur Hostinger shared hosting (même pattern que crm-rybsen/)
- Modules dans crm-labo-benyedder/modules/, API centralisée dans crm-labo-benyedder/api/api.php
- Layout partagé : crm-labo-benyedder/includes/header.php + footer.php (nav adaptée par rôle)
- Pattern JS : loadX() → applyFiltersX() → renderX(data), namespace global `LABO` (LABO.escape() anti-XSS)

## Modèle de données
- install.sql : schéma complet (clients/franchises/points de vente, catalogue produits +
  matières premières + recettes/BOM, commandes multi-canal, production/ordres de fabrication,
  lots & traçabilité DLC, stock, pertes/invendus, livraisons, facturation, déclarations de
  paiement, événements spéciaux)
- Vues utiles : v_marge_produits, v_stock_bas, v_stock_produits, v_encours_clients
- Rôles utilisateurs : admin, labo, production, franchise, point_vente, client_terme —
  chaque rôle a sa propre interface (nav filtrée dans includes/header.php)
- Portails externes (franchise/point_vente/client_terme) : portée toujours dérivée de la
  session (`$user['client_id']` / `$user['point_vente_id']`), jamais du body envoyé par le
  client — voir `monScope()` dans api/api.php. Toute nouvelle action `mes_*` doit suivre ce
  pattern pour ne pas réintroduire une faille d'accès croisé entre comptes.
- Paiement déclaré par une franchise/client à terme = statut `en_attente` dans
  `declarations_paiement`, jamais un impact direct sur le solde ; seul admin/labo peut
  `declaration_valider` (crée le vrai mouvement dans `paiements`) ou `declaration_rejeter`.

## Contraintes critiques
1. `crm-labo-benyedder/config.php` ne doit JAMAIS être commité dans git
2. Credentials SFTP uniquement en secrets GitHub Actions (préfixe BENYEDDER_)
3. Toujours créer une branche feature, merger via PR → main
4. Ne jamais recalculer ou écraser des valeurs monétaires existantes en base
5. DECIMAL(10,3) pour toutes les valeurs monétaires et quantités (cohérence avec crm-rybsen)
6. La décrémentation de stock matières premières doit toujours passer par la recette (BOM),
   jamais de modification manuelle du stock hors mouvement tracé

## Évolutions v2 (cuisines, catalogue par compte, stock temps réel, hors-ligne)
- Nouvelles tables (dans install.sql, ajoutées de façon idempotente via la procédure
  `upgrade_schema_v2` pour les colonnes, `CREATE TABLE IF NOT EXISTS` pour les tables) :
  `cuisines_production`, `categories` (nom↔cuisine_id), `catalogue_autorise`
  (cible_type client/point_vente + cible_id → produit_id ; vide = catalogue complet),
  `stocks_clients`, `inventaires` / `inventaire_lignes`. Colonnes ajoutées :
  seuils configurables sur `matieres_premieres` et `produits` (seuil_mode quantite|pourcentage,
  seuil_pourcentage, stock_reference), `users.cuisine_id`, `ordres_fabrication.cuisine_id`,
  `pertes.type_perte` (casse|perime|invendu), `factures.client_ref` (UNIQUE, idempotence caisse).
- Sur une base existante, re-exécuter install.sql applique la mise à niveau sans rien écraser.
- Production multi-cuisines : `of_generate` crée un OF par cuisine (catégorie du produit →
  categories.cuisine_id) ; un compte `production` porte `cuisine_id` et ne voit que ses OF ;
  livraison bloquée tant que toutes les cuisines d'une commande n'ont pas terminé.
- Invendu (`type_perte='invendu'`) = conservé, n'impacte PAS le stock ; casse/périmé = sortie.
- Hors-ligne (points de vente) : PWA (sw.js + manifest.webmanifest + assets/offline.js). La
  caisse met les ventes en file locale et les synchronise via `caisse_vente_save` avec un
  `client_ref` (UUID) — l'action est idempotente : une vente rejouée n'est jamais doublée.
  Toute nouvelle action encaissant hors-ligne doit suivre ce pattern client_ref/idempotence.
