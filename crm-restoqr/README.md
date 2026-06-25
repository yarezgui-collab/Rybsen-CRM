# QR-Menu — Guide de déploiement bar.rybsen.fr

## Informations de déploiement

| Paramètre | Valeur |
|-----------|--------|
| Sous-domaine | `bar.rybsen.fr` |
| Utilisateur MySQL | `u293743867_Bar` |
| Hôte MySQL | `localhost` |

---

## Installation (5 minutes)

### Étape 1 — Uploader le ZIP dans hPanel
- hPanel → **File Manager** → `/public_html/bar.rybsen.fr`
- Upload `qr-menu-bar.zip` → clic droit → **Extraire** à la racine

### Étape 2 — Lancer l'installateur
Ouvrir dans le navigateur :
```
https://bar.rybsen.fr/install.php
```

### Étape 3 — Remplir le formulaire
| Champ | Valeur |
|-------|--------|
| Hôte MySQL | `localhost` |
| Nom de la base | `u293743867_Bar` *(à vérifier dans hPanel → MySQL Databases)* |
| Utilisateur MySQL | `u293743867_Bar` |
| Mot de passe MySQL | *(celui créé dans hPanel)* |
| Nom du restaurant | ton nom de bar/restaurant |
| Twilio SID | ton Account SID |
| Twilio Token | ton Auth Token |
| Numéro WhatsApp | `whatsapp:+1xxxxxxxxxx` |

### Étape 4 — Récupérer les URLs QR par table
La page de succès affiche les URLs à encoder.
Générer les QR sur [goqr.me](https://goqr.me) et imprimer.

---

## Accès aux interfaces

| Interface | URL |
|-----------|-----|
| Client (scan QR) | `https://bar.rybsen.fr/public/client/index.html?t={token}` |
| Login équipe | `https://bar.rybsen.fr/public/login.html` |
| Dashboard serveur | Redirection auto après login |
| Dashboard propriétaire | Redirection auto après login |

---

## Structure déployée

```
bar.rybsen.fr/
├── install.php          ← Supprimer après installation
├── index.php            ← Redirige auto vers login ou install
├── .htaccess            ← Sécurité Apache (indexes, headers)
├── api/
│   ├── login.php
│   ├── logout.php
│   ├── menu.php
│   ├── commande_create.php
│   ├── commandes_list.php
│   ├── commande_update_statut.php
│   └── stats.php
├── includes/
│   ├── config.php       ← Généré par install.php
│   ├── db.php
│   ├── auth.php
│   ├── helpers.php
│   └── whatsapp.php
├── public/
│   ├── login.html
│   ├── client/index.html
│   ├── serveur/index.html
│   └── proprietaire/index.html
└── sql/
    └── schema.sql
```

---

## Ajouter un serveur (phpMyAdmin)

```sql
SET @hash = '$2y$10$...'; -- php -r "echo password_hash('MotDePasse', PASSWORD_DEFAULT);"
INSERT INTO utilisateurs (restaurant_id, nom, email, mot_de_passe_hash, role, zone_id, whatsapp_number)
VALUES (1, 'Prénom Nom', 'email@bar.tn', @hash, 'serveur', 1, '+21620xxxxxx');
```

## Ajouter une table (phpMyAdmin)

```sql
SET @token = SUBSTRING(MD5(RAND()), 1, 32);
INSERT INTO tables_restaurant (restaurant_id, zone_id, numero, qr_token)
VALUES (1, 1, 12, @token);
-- Récupérer l'URL QR :
SELECT CONCAT('https://bar.rybsen.fr/public/client/index.html?t=', qr_token)
FROM tables_restaurant WHERE numero=12;
```

## Configurer Twilio après coup

Éditer `includes/config.php` via File Manager :
```php
define('TWILIO_ACCOUNT_SID', 'ACxxxxxxxxxxxxxxxx');
define('TWILIO_AUTH_TOKEN',  'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
define('TWILIO_WHATSAPP_FROM', 'whatsapp:+14155238886');
```

---

## Checklist sécurité post-déploiement

- [ ] `install.php` supprimé
- [ ] HTTPS actif sur `bar.rybsen.fr` (hPanel → SSL)
- [ ] Test accès bloqué : `https://bar.rybsen.fr/includes/config.php` → doit renvoyer 403
- [ ] Test accès bloqué : `https://bar.rybsen.fr/sql/schema.sql` → doit renvoyer 403
