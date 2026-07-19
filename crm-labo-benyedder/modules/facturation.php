<?php
require_once '../config.php';
requireLogin();
$user = currentUser();
$pageTitle = $user['role'] === 'admin' ? 'Factures & paiements' : 'Mes factures';
$activePage = 'facturation';
$icon = '🧾';
$description = 'Facturation comptant et à terme, suivi des paiements, encours par client.';
require_once '../includes/placeholder.php';
