<?php
require_once 'config.php';
if (isLoggedIn()) {
    auditLog('logout', 'user', (int)$_SESSION['fm_user_id']);
}
session_unset();
session_destroy();
header('Location: index.php');
exit;
