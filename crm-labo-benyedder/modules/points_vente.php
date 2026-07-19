<?php
require_once '../config.php';
requireRole(['admin','labo']);
$pageTitle = 'Points de vente';
$activePage = 'points_vente';
$icon = '🏬';
$description = 'Gestion des points de vente : responsables, stock vitrine local, ventes passagers.';
require_once '../includes/placeholder.php';
