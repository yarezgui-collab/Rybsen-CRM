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
- Secrets GitHub requis :
  - Réutilisés (déjà existants) : PATISSERIE_SFTP_HOST / PATISSERIE_SFTP_PORT / PATISSERIE_SFTP_USER / PATISSERIE_SFTP_PASSWORD
  - Propres à ce projet (à créer) : BENYEDDER_DB_NAME (= nom base = nom utilisateur MySQL, ex: u293743867_Tby) / BENYEDDER_DB_PASSWORD
- Exclut du déploiement : *.sql, config.example.php, .gitignore
- La base MySQL doit déjà contenir le schéma : install.sql exécuté manuellement via phpMyAdmin
  avant le premier déploiement (non automatisé, pour ne jamais écraser des données existantes)

## Structure du projet
- Stack : PHP 8+ PDO MySQL sur Hostinger shared hosting (même pattern que crm-rybsen/)
- Modules dans crm-labo-benyedder/modules/, API centralisée dans crm-labo-benyedder/api/api.php
- Layout partagé : crm-labo-benyedder/includes/header.php + footer.php (nav adaptée par rôle)
- Pattern JS : loadX() → applyFiltersX() → renderX(data), namespace global `LABO` (LABO.escape() anti-XSS)

## Modèle de données
- install.sql : schéma complet (clients/franchises/points de vente, catalogue produits +
  matières premières + recettes/BOM, commandes multi-canal, production/ordres de fabrication,
  lots & traçabilité DLC, stock, pertes/invendus, livraisons, facturation, événements spéciaux)
- Vues utiles : v_marge_produits, v_stock_bas, v_encours_clients
- Rôles utilisateurs : admin, labo, production, franchise, point_vente, client_terme —
  chaque rôle a sa propre interface (nav filtrée dans includes/header.php)

## Contraintes critiques
1. `crm-labo-benyedder/config.php` ne doit JAMAIS être commité dans git
2. Credentials SFTP uniquement en secrets GitHub Actions (préfixe BENYEDDER_)
3. Toujours créer une branche feature, merger via PR → main
4. Ne jamais recalculer ou écraser des valeurs monétaires existantes en base
5. DECIMAL(10,3) pour toutes les valeurs monétaires et quantités (cohérence avec crm-rybsen)
6. La décrémentation de stock matières premières doit toujours passer par la recette (BOM),
   jamais de modification manuelle du stock hors mouvement tracé
