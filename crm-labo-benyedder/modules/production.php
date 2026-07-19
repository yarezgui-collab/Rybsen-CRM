<?php
require_once '../config.php';
requireRole(['admin','labo','production']);
$pageTitle = 'Ordres de fabrication';
$activePage = 'production';
$icon = '⚙️';
$description = 'Agrégation des commandes par produit, génération des ordres de fabrication et suivi jusqu\'à réception avec numéro de lot.';
require_once '../includes/placeholder.php';
