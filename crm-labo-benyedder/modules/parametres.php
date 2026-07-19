<?php
require_once '../config.php';
requireRole(['admin']);
$pageTitle = 'Paramètres & fonctionnalités';
$activePage = 'parametres';
$icon = '🛠️';
$description = 'Nom de l\'établissement, devise, taux de TVA par défaut, et activation/désactivation des fonctionnalités optionnelles (table parametres).';
require_once '../includes/placeholder.php';
