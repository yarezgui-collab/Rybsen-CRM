<?php
/**
 * config.php — Copier ce fichier en `config.php` et remplir les valeurs.
 * NE PAS versionner config.php (contient des credentials).
 * Généré automatiquement par install.php, ou rempli manuellement.
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'votre_base_de_donnees');
define('DB_USER', 'votre_utilisateur_mysql');
define('DB_PASS', 'votre_mot_de_passe_mysql');

define('APP_TIMEZONE', 'Africa/Tunis');
define('APP_DEVISE',   'DT');
define('SESSION_LIFETIME', 43200); // 12h

// Twilio WhatsApp — https://console.twilio.com
define('TWILIO_ACCOUNT_SID', 'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
define('TWILIO_AUTH_TOKEN',  'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
define('TWILIO_WHATSAPP_FROM', 'whatsapp:+14155238886'); // sandbox ou numéro approuvé

// Clé secrète pour les tokens QR (32+ chars aléatoires)
define('QR_SECRET', 'remplacer_par_une_chaine_aleatoire_de_64_caracteres');

date_default_timezone_set(APP_TIMEZONE);
