# RYBSEN CRM — Plateforme de Pilotage

CRM interne de RYBSEN, startup spécialisée dans la purification d'eau pour l'industrie graphique (machine AquaClean).

## Modules

| Module | Description |
|--------|-------------|
| Tableau de bord | KPIs temps réel, relances, pipeline, candidatures |
| Investisseurs & Fonds | Suivi levée de fonds, score chaleur, relances |
| Candidatures | Programmes (subventions, prix, accélération) |
| Clients & Prospects | Pipeline commercial AquaClean |
| Partenaires | Distributeurs, OEM, sous-traitants |
| Licensing Constructeurs | Ciblage constructeurs presses offset |
| Calendrier LinkedIn | Planification contenu éditorial |
| Fabrication AquaClean | Suivi assemblage et expédition machines |
| Tâches & Alertes | Gestion des priorités + alertes brevets |
| Messages | Traçabilité communications |

## Stack technique

- **Backend** : PHP 8+ / PDO / MySQL (Hostinger)
- **Frontend** : HTML/CSS/JS vanilla (sans framework)
- **Charte** : Navy `#1A3A52` · Teal `#4A9B8F` · Gold `#E8A44C` · Cream `#FAFAF7`

## Installation

1. Créer la base MySQL sur Hostinger
2. Importer `install.sql` dans phpMyAdmin
3. Importer `ajout_linkedin.sql` (table calendrier LinkedIn)
4. Copier `config.example.php` → `config.php` et renseigner les credentials
5. Déployer les fichiers via FTP ou Git sur Hostinger

## Identifiants par défaut

Mot de passe initial de tous les comptes : `Rybsen2026!`

| Utilisateur | Email | Rôle |
|-------------|-------|------|
| Yassine Rezgui | yrezgui@rybsen.fr | admin |
| Nadia Benaissa | nadia@rybsen.fr | manager |
| Hela Darouez | hela@rybsen.fr | viewer |
| Adnane Rezgui | adnane@rybsen.fr | manager |

> **Changer les mots de passe dès la première connexion.**

---
*BE THE FLOW — RYBSEN © 2026*
