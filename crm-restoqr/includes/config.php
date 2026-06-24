<?php
/**
 * config.php — Généré automatiquement par install.php lors du premier déploiement.
 * NE PAS modifier manuellement. NE PAS versionner (contient des credentials).
 * Si ce fichier contient 'changeme_*', visitez /install.php pour lancer l'installation.
 */
define('DB_HOST', 'changeme_host');
define('DB_NAME', 'changeme_db');
define('DB_USER', 'changeme_user');
define('DB_PASS', 'changeme_password');

define('APP_TIMEZONE', 'Africa/Tunis');
define('APP_DEVISE',   'DT');
define('SESSION_LIFETIME', 43200); // 12h

// Twilio WhatsApp (ajouté automatiquement par install.php si clés fournies)
// define('TWILIO_ACCOUNT_SID', 'ACxxxxxxxx');
// define('TWILIO_AUTH_TOKEN',  'xxxxxxxx');
// define('TWILIO_WHATSAPP_FROM', 'whatsapp:+14155238886');

define('QR_SECRET', 'changeme_long_random_secret_string');

date_default_timezone_set(APP_TIMEZONE);
