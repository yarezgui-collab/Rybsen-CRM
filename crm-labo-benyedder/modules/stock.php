<?php
require_once '../config.php';
requireRole(['admin','labo','point_vente']);
$user = currentUser();
$pageTitle = $user['role'] === 'point_vente' ? 'Mon stock' : 'Stock & matières premières';
$activePage = 'stock';
$icon = '📊';
$description = 'Mouvements de stock matières premières et produits finis, décrémentation automatique via la recette, alertes de seuil, gestion des pertes/invendus.';
require_once '../includes/placeholder.php';
