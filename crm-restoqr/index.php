<?php
/**
 * index.php — Point d'entrée racine.
 * Redirige vers install.php si jamais installé, sinon vers la page de connexion.
 */

$config_ok = file_exists(__DIR__ . '/includes/config.php')
    && strpos(file_get_contents(__DIR__ . '/includes/config.php'), 'changeme_db') === false;

if (!$config_ok) {
    header('Location: /install.php');
    exit;
}

header('Location: /public/login.html');
exit;
