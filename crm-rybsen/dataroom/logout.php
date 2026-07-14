<?php
require_once __DIR__ . '/_dr.php';
$db = getDB();
if (!empty($_SESSION['dr_acces_id'])) {
    drLog($db, intval($_SESSION['dr_acces_id']), 'logout');
}
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
header('Location: /dataroom/');
exit;
