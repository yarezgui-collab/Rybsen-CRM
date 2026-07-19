<?php
require_once '../config.php';
requireRole(['admin','labo']);
$pageTitle = 'Franchises';
$activePage = 'franchises';
$icon = '🤝';
$description = 'Gestion des franchisés, choix du mode de paiement (comptant / terme / libre choix) et suivi de leurs commandes.';
require_once '../includes/placeholder.php';
