# RYBSEN CRM — Instructions Claude Code

Tu travailles sur le projet CRM déployé sur crm.rybsen.com (Hostinger).

## Dépôt GitHub
- Repo : yarezgui-collab/Rybsen-CRM
- Branche de travail : créer une branche depuis main (ex: feat/crm-rybsen-xxx)
- Ne jamais pousser directement sur main sans PR

## Déploiement SFTP automatique
- Le déploiement se fait via GitHub Actions au merge sur main
- Chemin serveur : domains/rybsen.com/public_html/crm/
- URL prod : crm.rybsen.com ou rybsen.com/crm/
- Les secrets SFTP (SFTP_HOST, SFTP_PORT, SFTP_USER, SFTP_PASSWORD) sont déjà configurés
  dans le repo GitHub yarezgui-collab/Rybsen-CRM

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
