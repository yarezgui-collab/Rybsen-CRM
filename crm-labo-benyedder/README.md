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
| `admin` | Tout — CRUD complet, utilisateurs, paramètres/fonctionnalités, validation des paiements déclarés |
| `labo` | Clients, franchises, points de vente, catalogue, commandes, production, stock, facturation |
| `production` | Ordres de fabrication |
| `franchise` | Portail autonome : ses commandes, catalogue en lecture, ses factures/encours, déclaration de paiement |
| `point_vente` | Portail autonome : caisse (vente passager), réapprovisionnement, son stock vitrine |
| `client_terme` | Portail autonome : ses commandes, catalogue en lecture, ses factures/encours, déclaration de paiement |

Chaque portail externe est **isolé côté serveur** : l'entité (client_id / point_vente_id) vient
toujours de la session, jamais de ce qu'envoie le navigateur — impossible pour une franchise de
voir les données d'une autre, même en modifiant les requêtes. Vérifié par 35 tests automatisés
incluant des tentatives d'accès croisées (IDOR).

## Installation

1. Copier `config.example.php` en `config.php` et renseigner les identifiants MySQL Hostinger
   (inutile en production : le déploiement automatique le génère depuis les secrets GitHub).
2. Exécuter `install.sql` dans phpMyAdmin (ou `mysql < install.sql`) sur la base cible.
   Idempotent — peut être rejoué sans risque de doublons.
3. Les données de démonstration (4 clients répartis sur les 3 canaux + 2 produits) sont
   chargées **automatiquement à chaque déploiement** via `run_demo_data.php`, protégé par le
   secret `BENYEDDER_MIGRATION_TOKEN` et idempotent (aucun doublon si rejoué). Pas d'action
   manuelle nécessaire.
4. Se connecter avec le compte admin initial :
   - email : `admin@benyedder.tn`
   - mot de passe temporaire : `BenYedder2026!` (à changer dans Utilisateurs → Changer mon mot de passe)

## État d'avancement

**Opérationnel — vérifié de bout en bout (voir rapport de vérification) :**
- Authentification multi-rôles + gestion des comptes (admin) + changement de mot de passe
- Modèle de données complet (25 tables + 4 vues : marge produit, stock bas, stock produits finis, encours clients)
- Clients à terme, Franchises, Points de vente (CRUD)
- Catalogue : produits, matières premières, recettes/BOM avec calcul de marge en direct
- Commandes multi-canal (terme/franchise/point de vente), workflow de statuts
- Production : agrégation des commandes confirmées en ordres de fabrication, clôture avec
  création de lots tracés (numéro, DLC) et décrémentation automatique des matières premières
  selon la recette
- Stock : mouvements matières/produits, corrections manuelles, pertes/invendus, stock vitrine
  par point de vente, vente passager avec encaissement immédiat
- Livraisons/Dispatch : depuis un ordre terminé vers le canal d'origine, mise à jour du stock
- Facturation : génération depuis une commande livrée, mode de paiement résolu automatiquement
  par canal, paiements partiels/complets, statuts encours
- Statistiques : marge par produit, stock bas, consommation matières, ventes par canal,
  produits les plus vendus, encours clients
- Événements spéciaux (calendrier saisonnier / traiteur) et paramètres/fonctionnalités (admin)
- **Portails self-service** franchise / point de vente / client à terme : commandes en libre
  service, catalogue en lecture, factures & encours, déclaration de paiement (avec validation
  admin obligatoire avant impact sur le solde), caisse et stock vitrine pour les points de vente
- Comptes de démonstration pour tester chaque portail (voir plus bas)

Aucune limitation connue à ce stade — l'ensemble du flux (admin ↔ 3 portails externes) a été
vérifié de bout en bout, y compris les scénarios d'attaque (accès croisé entre deux comptes).

## Comptes de démonstration

| Portail | Email | Mot de passe |
|---|---|---|
| Franchise (Sfax) | `demo.franchise@benyedder.tn` | `Demo2026!` |
| Client à terme (Café de la Gare) | `demo.client@benyedder.tn` | `Demo2026!` |
| Point de vente (Boutique El Menzah) | `demo.pointvente@benyedder.tn` | `Demo2026!` |

Créés automatiquement par `run_demo_data.php` à chaque déploiement.

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
| `BENYEDDER_DB_NAME` | Nom de la base MySQL = nom d'utilisateur (`u293743867_Tby`) | ✅ Créé |
| `BENYEDDER_DB_PASSWORD` | Mot de passe MySQL | ✅ Créé |
| `BENYEDDER_MIGRATION_TOKEN` | Jeton partagé protégeant `run_demo_data.php` (valeur aléatoire, n'importe quelle chaîne longue) | ⚠️ À créer |

À ajouter dans Settings → Secrets and variables → Actions du repo. `DB_HOST` est fixé à
`localhost` dans le workflow (même hébergement que le PHP).

La base doit déjà contenir le schéma : exécuter `install.sql` une fois via phpMyAdmin avant le
premier déploiement (non automatisé, pour éviter d'écraser des données en cas de re-déploiement).
