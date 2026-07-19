# CRM Labo Ben Yedder

CRM pour Traiteur Pâtisserie Ben Yedder — labo de fabrication, franchises et points de vente.

## Contexte métier

Le labo agrège les commandes de 3 canaux par produit et quantité totale, envoie l'ordre de
fabrication au site de production, puis dispatch les produits reçus vers les 3 canaux :

- **Clients à terme** — commandes régulières et ponctuelles, regroupées dans un même envoi,
  facturées à terme (encours).
- **Franchises** — mode de paiement libre (comptant, terme, ou au choix par franchise).
- **Points de vente** — vente passager sur place + réapprovisionnement vitrine.

Le labo produit sur commande **et** sur stock (anticipation des pics de consommation :
Ramadan, Aïd, événementiel).

## Stack

PHP 8 + PDO MySQL, même pattern que `crm-rybsen/` (modules/, api/, includes/, assets/).

## Rôles & interfaces

| Rôle | Accès |
|---|---|
| `admin` | Tout — CRUD complet, utilisateurs, paramètres/fonctionnalités |
| `labo` | Clients, franchises, points de vente, catalogue, commandes, production, stock |
| `production` | Ordres de fabrication |
| `franchise` | Ses commandes, ses factures |
| `point_vente` | Vente passager, réappro vitrine, stock local |
| `client_terme` | Ses commandes, ses factures/encours |

## Installation

1. Copier `config.example.php` en `config.php` et renseigner les identifiants MySQL Hostinger.
2. Exécuter `install.sql` dans phpMyAdmin (ou `mysql < install.sql`) sur la base cible.
3. Se connecter avec le compte admin initial :
   - email : `admin@benyedder.tn`
   - mot de passe temporaire : `BenYedder2026!` (à changer après la première connexion)

## État d'avancement

**Opérationnel :**
- Authentification multi-rôles
- Modèle de données complet (25 tables + 3 vues : marge produit, stock bas, encours clients)
- Module Clients à terme (CRUD)
- Module Catalogue : produits, matières premières, recettes/BOM avec calcul de marge

**À développer (placeholders en place, nav déjà branchée) :**
- Commandes multi-canal + agrégation
- Production (ordres de fabrication, lots/DLC)
- Stock (mouvements, décrémentation auto via recette, pertes/invendus)
- Livraisons / dispatch
- Facturation & paiements
- Statistiques & événements spéciaux
- Gestion utilisateurs & paramètres (admin)

## Déploiement — 100% automatique

- URL prod : https://tby.rybsen.fr
- Chemin distant : `domains/rybsen.fr/public_html/tby`
- Déclenché via GitHub Actions (`.github/workflows/deploy-crm-labo-benyedder.yml`) au push sur
  `main` dans `crm-labo-benyedder/**`.
- `config.php` n'est **jamais commité** : il est généré à la volée à chaque déploiement à partir
  de secrets GitHub, puis envoyé par SFTP avec le reste des fichiers.

### Secrets GitHub requis

| Secret | Rôle | Statut |
|---|---|---|
| `PATISSERIE_SFTP_HOST` / `_PORT` / `_USER` / `_PASSWORD` | Connexion SFTP (compte partagé avec crm-patisserie) | ✅ Déjà existants |
| `BENYEDDER_DB_NAME` | Nom de la base MySQL = nom d'utilisateur (`u293743867_Tby`) | ⚠️ À créer |
| `BENYEDDER_DB_PASSWORD` | Mot de passe MySQL | ⚠️ À créer |

À ajouter dans Settings → Secrets and variables → Actions du repo. `DB_HOST` est fixé à
`localhost` dans le workflow (même hébergement que le PHP).

La base doit déjà contenir le schéma : exécuter `install.sql` une fois via phpMyAdmin avant le
premier déploiement (non automatisé, pour éviter d'écraser des données en cas de re-déploiement).
