<?php
require_once '../config.php';
requireRole(['admin']);
$pageTitle = 'Événements spéciaux';
$activePage = 'evenements';
$icon = '🎉';
$description = 'Calendrier saisonnier (Ramadan, Aïd) et commandes événementielles (mariages, traiteur) avec acompte.';
require_once '../includes/placeholder.php';
