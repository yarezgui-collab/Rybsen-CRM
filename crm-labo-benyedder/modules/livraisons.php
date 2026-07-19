<?php
require_once '../config.php';
requireRole(['admin','labo']);
$pageTitle = 'Livraisons / Dispatch';
$activePage = 'livraisons';
$icon = '🚚';
$description = 'Dispatch des produits réceptionnés du site de production vers les 3 canaux, avec traçabilité par lot.';
require_once '../includes/placeholder.php';
