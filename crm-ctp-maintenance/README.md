# CTP Maintenance — GMAO PrePresse (filiale Kodak)

CRM / GMAO pour un service technique **CTP (Computer-to-Plate)** : gestion du parc
machines clients, contrats & calendrier de maintenance préventive, interventions /
réparations (SAV), catalogue et stock de pièces détachées, commandes fournisseur, et
portail client. Même stack et mêmes conventions que les autres CRM du dépôt
(`crm-rybsen/`, `crm-labo-benyedder/`).

## Stack
- PHP 8+ / PDO MySQL (Hostinger shared hosting)
- API centralisée : `api/api.php`
- Layout partagé : `includes/header.php` + `footer.php` (nav filtrée par rôle)
- Assets : `assets/style.css`, `assets/app.js` (namespace global `CTP`, `CTP.escape()` anti-XSS)
- Pattern JS : `load()` → `render()`, modales pour l'édition
- Schéma idempotent : `install.sql` appliqué par `run_migration.php`

## Rôles
| Rôle | Accès |
|------|-------|
| `admin` | Tout, y compris utilisateurs et suppressions |
| `technicien` | Clients, parc, contrats, calendrier préventif, interventions, pièces |
| `magasinier` | Parc (lecture), interventions (pièces), catalogue & stock, commandes fournisseur |
| `client` | Portail : ses machines, ses interventions, signalement de panne |

Le portail client dérive **toujours** sa portée de la session (`$user['client_id']`),
jamais du corps de requête — voir `monClientId()` dans `api/api.php`. Toute nouvelle
action `mes_*` doit suivre ce pattern.

## Modèle de données (`install.sql`)
- `users`, `clients`
- `machines` — parc CTP (modèle, n° série, technologie, compteur plaques, garantie, statut)
- `contrats` — préventif / full service / garantie, fréquence + prochaine échéance, SLA
- `interventions` — préventive / corrective / installation / mise à jour, workflow de statuts
- `pieces` — catalogue + stock + seuil d'alerte
- `intervention_pieces` — pièces consommées par intervention
- `commandes_pieces` / `commande_lignes` — commandes fournisseur, réception (partielle)
- `mouvements_stock` — **traçabilité complète** de tout mouvement de stock
- Vues : `v_pieces_stock_bas`, `v_maintenance_due`, `v_interventions_ouvertes`

### Règles métier importantes
1. Le stock des pièces ne se modifie **jamais** directement : il passe toujours par un
   mouvement tracé (`moveStock()`) — consommation en intervention, réception de commande,
   ajustement manuel motivé.
2. La réception d'une commande incrémente le stock et gère les réceptions partielles
   (statut `partielle` → `recue`).
3. Planifier une maintenance préventive depuis un contrat crée une intervention et fait
   avancer `prochaine_maintenance` selon la fréquence du contrat.
4. Valeurs monétaires en `DECIMAL(10,3)` (cohérence avec les autres CRM).

## Installation

### En local / manuel
1. Copier `config.example.php` → `config.php` et renseigner les identifiants MySQL +
   un `MIGRATION_TOKEN` aléatoire.
2. Appliquer le schéma : soit exécuter `install.sql` dans phpMyAdmin, soit
   `curl -X POST -H "X-Migration-Token: <token>" https://<url>/run_migration.php`.
3. Se connecter avec le compte admin par défaut :
   - **Email** : `admin@ctp.rybsen.com`
   - **Mot de passe** : `kodak2026` → **à changer immédiatement** (module Utilisateurs).

`config.php` n'est **jamais** commité (voir `.gitignore` + `.htaccess`).

## Déploiement automatique (GitHub Actions)
Fichier : `.github/workflows/deploy-crm-ctp-maintenance.yml` — déclenché sur push `main`
(paths `crm-ctp-maintenance/**`). Il génère `config.php` depuis les secrets, déploie par
SFTP, puis applique `install.sql` via `run_migration.php` (idempotent, bloque le déploiement
si une requête échoue).

Secrets GitHub à créer (Settings → Secrets and variables → Actions) :

| Secret | Exemple |
|--------|---------|
| `CTP_SFTP_HOST` | `194.36.184.184` |
| `CTP_SFTP_PORT` | `65002` |
| `CTP_SFTP_USER` | utilisateur SFTP du sous-domaine |
| `CTP_SFTP_PASSWORD` | mot de passe SFTP |
| `CTP_REMOTE_PATH` | `domains/rybsen.fr/public_html/ctp` |
| `CTP_URL` | `https://ctp.rybsen.fr` |
| `CTP_DB_NAME` | nom de la base = utilisateur MySQL |
| `CTP_DB_PASSWORD` | mot de passe MySQL |
| `CTP_MIGRATION_TOKEN` | jeton aléatoire long |

> Provisionner d'abord le sous-domaine + la base MySQL côté Hostinger, puis créer les
> secrets ci-dessus. `install.sql` est déployé (nécessaire à `run_migration.php`) mais
> reste non téléchargeable publiquement (`.htaccess`).
