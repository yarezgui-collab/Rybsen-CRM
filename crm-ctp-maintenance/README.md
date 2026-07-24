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
- `maintenances_planifiees` — **calendrier de visites préventives par machine sous contrat**
  (type *préventive* PM / *prévisionnelle*, date prévue → réalisée). Cœur du SAV Kodak CTP.
- `interventions` — préventive / corrective / installation / mise à jour, workflow de statuts
- `pieces` — catalogue + stock + seuil d'alerte
- `intervention_pieces` — pièces consommées par intervention
- `commandes_pieces` / `commande_lignes` — commandes fournisseur, réception (partielle)
- `mouvements_stock` — **traçabilité complète** de tout mouvement de stock
- Vues : `v_pieces_stock_bas`, `v_maintenance_due`, `v_maintenances_planifiees`, `v_interventions_ouvertes`

### Données réelles pré-chargées (parc Kodak CTP)
`install.sql` importe, **de façon idempotente** (insert-if-missing sur `code_client` /
`n_serie` / `numero` de contrat / (machine+date+type) de visite), le référentiel réel
extrait du fichier Excel fourni :
- **24 clients** (imprimeries), **25 machines** CTP (Trendsetter / Achieve / Magnus / TS Q1600)
- **10 contrats** préventifs actifs + **46 visites préventives planifiées** (calendrier 2026)
- Clients inactifs et statuts métier (sous contrôle juridique, contrat en validation)
- Interventions historiques (dernières interventions connues, changement filtre à air)

Ré-exécuter la migration ne crée jamais de doublon : seules les entités absentes sont
insérées. Les fiches existantes ne sont jamais écrasées.

### Planning & tournées (`modules/planning.php`)
Pilotage terrain du calendrier de visites : **vue calendrier mensuelle** (grille visuelle,
clic sur une visite → affectation), **vue par technicien** et **vue par région/ville**
(regroupement pour construire des tournées géographiques cohérentes plutôt que de
zigzaguer entre villes). Affectation d'un technicien (`mp_assigner`) et bouton
« Générer les cycles suivants » (`mp_generer_tous`) qui reconduit d'un an le dernier
cycle de chaque contrat actif — idempotent (contrainte `UNIQUE(machine, date, type)`),
aucune visite en double même rejoué plusieurs fois.

### Bon d'intervention imprimable (`modules/intervention_print.php`)
Page A4 autonome (même patron que `facturation_print.php` dans crm-rybsen) : fiche
client + machine, diagnostic/résolution, pièces consommées avec total, cases signature
technicien/client. Accessible depuis le détail d'une intervention et automatiquement à
l'ouverture d'une visite marquée réalisée.

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

**Provisionné côté Hostinger** : sous-domaine `CTP.rybsen.fr` + base MySQL
`u293743867_Ctp` (même compte d'hébergement que `rybsen.fr`, comme
`tby.rybsen.fr` pour crm-labo-benyedder).

Le workflow réutilise **automatiquement** le compte SFTP déjà configuré pour ce
domaine (`PATISSERIE_SFTP_*`, mêmes paramètres que crm-labo-benyedder — chemin
distant fixe `domains/rybsen.fr/public_html/ctp`). Un compte SFTP dédié
(`CTP_SFTP_HOST/PORT/USER/PASSWORD`) prend la priorité s'il est un jour créé.

Secrets GitHub **dédiés à créer** (Settings → Secrets and variables → Actions) :

| Secret | Valeur |
|--------|--------|
| `CTP_DB_NAME` | `u293743867_Ctp` |
| `CTP_DB_PASSWORD` | mot de passe MySQL de `u293743867_Ctp` |
| `CTP_MIGRATION_TOKEN` | jeton aléatoire long (protège `run_migration.php`) |

> `install.sql` est déployé (nécessaire à `run_migration.php`) mais reste non
> téléchargeable publiquement (`.htaccess`). URL prod : `https://ctp.rybsen.fr`.
