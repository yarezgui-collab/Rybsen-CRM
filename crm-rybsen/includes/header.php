<?php
require_once __DIR__ . '/security.php';
sendSecurityHeaders();
secureSessionStart();
requireLogin();
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RYBSEN CRM — <?= $pageTitle ?? 'Tableau de bord' ?></title>
<link rel="stylesheet" href="/assets/style.css">
<script src="/assets/app.js"></script>
</head>
<body>
<nav class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <span class="sidebar-logo">💧</span>
    <span class="sidebar-name">RYBSEN</span>
  </div>
  <div class="nav-section-label">PILOTAGE</div>
  <a href="/index.php" class="nav-item <?= ($activePage??'')==='dashboard' ? 'active':'' ?>">
    <span class="nav-icon">⚡</span><span class="nav-text">Tableau de bord</span>
  </a>
  <div class="nav-section-label">LEVÉE DE FONDS</div>
  <a href="/modules/investisseurs.php" class="nav-item <?= ($activePage??'')==='investisseurs' ? 'active':'' ?>">
    <span class="nav-icon">💰</span><span class="nav-text">Investisseurs & Fonds</span>
  </a>
  <a href="/modules/candidatures.php" class="nav-item <?= ($activePage??'')==='candidatures' ? 'active':'' ?>">
    <span class="nav-icon">📋</span><span class="nav-text">Candidatures</span>
  </a>
  <?php if (($user['role'] ?? '') === 'admin'): ?>
  <a href="/modules/dataroom.php" class="nav-item <?= ($activePage??'')==='dataroom' ? 'active':'' ?>">
    <span class="nav-icon">🔐</span><span class="nav-text">Data Room</span>
  </a>
  <?php endif; ?>
  <div class="nav-section-label">COMMERCIAL</div>
  <a href="/modules/clients.php" class="nav-item <?= ($activePage??'')==='clients' ? 'active':'' ?>">
    <span class="nav-icon">🏭</span><span class="nav-text">Clients & Prospects</span>
  </a>
  <a href="/modules/partenaires.php" class="nav-item <?= ($activePage??'')==='partenaires' ? 'active':'' ?>">
    <span class="nav-icon">🤝</span><span class="nav-text">Partenaires</span>
  </a>
  <a href="/modules/licensing.php" class="nav-item <?= ($activePage??'')==='licensing' ? 'active':'' ?>">
    <span class="nav-icon">🔩</span><span class="nav-text">Licensing Constructeurs</span>
  </a>
  <div class="nav-section-label">FACTURATION</div>
  <a href="/modules/linkedin.php" class="nav-item <?= ($activePage??'')==='linkedin' ? 'active':'' ?>">
    <span class="nav-icon">📅</span><span class="nav-text">Calendrier LinkedIn</span>
  </a>
  <a href="/modules/facturation.php" class="nav-item <?= ($activePage??'')==='facturation' ? 'active':'' ?>">
    <span class="nav-icon">🧾</span><span class="nav-text">Facturation</span>
  </a>
  <div class="nav-section-label">PRODUCTION</div>
  <a href="/modules/fabrication.php" class="nav-item <?= ($activePage??'')==='fabrication' ? 'active':'' ?>">
    <span class="nav-icon">⚙️</span><span class="nav-text">Fabrication AquaClean</span>
  </a>
  <div class="nav-section-label">OPÉRATIONS</div>
  <a href="/modules/taches.php" class="nav-item <?= ($activePage??'')==='taches' ? 'active':'' ?>">
    <span class="nav-icon">✅</span><span class="nav-text">Tâches & Alertes</span>
  </a>
  <a href="/modules/messages.php" class="nav-item <?= ($activePage??'')==='messages' ? 'active':'' ?>">
    <span class="nav-icon">📨</span><span class="nav-text">Messages</span>
  </a>
  <?php if (($user['role'] ?? '') === 'admin'): ?>
  <div class="nav-section-label">ADMINISTRATION</div>
  <a href="/modules/utilisateurs.php" class="nav-item <?= ($activePage??'')==='utilisateurs' ? 'active':'' ?>">
    <span class="nav-icon">👥</span><span class="nav-text">Utilisateurs</span>
  </a>
  <?php endif; ?>
  <div class="sidebar-footer">
    <div class="user-badge">
      <div class="user-avatar"><?= htmlspecialchars($user['avatar'] ?? 'YR') ?></div>
      <div class="user-info">
        <div class="user-name"><?= htmlspecialchars($user['nom'] ?? '') ?></div>
        <div class="user-role"><?= htmlspecialchars($user['role'] ?? '') ?></div>
      </div>
    </div>
    <a href="/logout.php" class="logout-btn">Déconnexion</a>
  </div>
</nav>
<div class="main-wrap">
  <header class="topbar">
    <button class="menu-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
    <div class="topbar-title"><?= htmlspecialchars($pageTitle ?? 'Tableau de bord') ?></div>
    <div class="topbar-right">
      <?php
        try {
          $db = getDB();
          $alertes = $db->query("SELECT COUNT(*) FROM taches WHERE statut != 'Terminé' AND priorite = '🔴 Urgent'")->fetchColumn();
          if ($alertes > 0): ?>
      <a href="/modules/taches.php" class="alert-badge">🔴 <?= $alertes ?> urgent<?= $alertes > 1 ? 's' : '' ?></a>
      <?php endif; } catch(Exception $e) {} ?>
    </div>
  </header>
  <main class="content">
