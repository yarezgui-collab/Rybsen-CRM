<?php
require_once '../config.php';
requireRole(['admin']);
$pageTitle = 'Statistiques';
$activePage = 'statistiques';
$icon = '📈';
$description = 'Consommation de matières premières, produits les plus vendus, marge par produit (vue v_marge_produits), performance par canal.';
require_once '../includes/placeholder.php';
