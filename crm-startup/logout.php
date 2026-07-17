<?php
require_once 'config.php';

if (isLoggedIn()) {
    auditLog('logout', 'user', (int)$_SESSION['fm_user_id']);
}

// Détruire complètement la session (données + cookie)
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

header('Location: index.php');
exit;
