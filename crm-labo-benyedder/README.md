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

1. Copier `config.example.php` en `config.php` et renseigner les identifiants MySQL Hostinger
   (inutile en production : le déploiement automatique le génère depuis les secrets GitHub).
2. Exécuter `install.sql` dans phpMyAdmin (ou `mysql < install.sql`) sur la base cible.
   Idempotent — peut être rejoué sans risque de doublons.
3. (Optionnel, démo commerciale) Exécuter `demo_data.sql` pour ajouter 4 clients de
   démonstration répartis sur les 3 canaux + 2 produits supplémentaires. Idempotent également.
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

**Non couvert dans cette itération :**
- Portails self-service pour franchise / point de vente / client à terme (ces rôles existent
  et sont protégés côté permissions, mais n'ont pas encore d'interface dédiée limitée à leurs
  propres données — seuls admin/labo/production ont une interface complète aujourd'hui)

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
