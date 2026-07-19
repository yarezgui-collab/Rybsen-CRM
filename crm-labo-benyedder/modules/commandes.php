<?php
require_once '../config.php';
requireLogin();
$user = currentUser();
$pageTitle = in_array($user['role'], ['admin','labo'], true) ? 'Commandes' : 'Mes commandes';
$activePage = 'commandes';
$icon = '📦';
$description = 'Commandes régulières, ponctuelles et événementielles des 3 canaux (clients à terme, franchises, points de vente), avec agrégation par produit pour le labo.';
require_once '../includes/placeholder.php';
