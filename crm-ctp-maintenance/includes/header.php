<?php requireLogin(); $user = currentUser(); $role = $user['role'] ?? 'client';
$roleLabels = ['admin'=>'Administrateur','technicien'=>'Technicien SAV','magasinier'=>'Magasinier pièces','client'=>'Client'];
$interne = in_array($role, ['admin','technicien','magasinier'], true);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CTP Maintenance — <?= htmlspecialchars($pageTitle ?? 'Tableau de bord') ?></title>
<link rel="stylesheet" href="/assets/style.css">
<meta name="theme-color" content="#23282D">
<script src="/assets/app.js"></script>
</head>
<body>
<nav class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <span class="sidebar-logo">🖨️</span>
    <span class="sidebar-name">CTP MAINTENANCE</span>
  </div>

  <div class="nav-section-label">PILOTAGE</div>
  <a href="/index.php" class="nav-item <?= ($activePage??'')==='dashboard' ? 'active':'' ?>">
    <span class="nav-icon">⚡</span><span class="nav-text">Tableau de bord</span>
  </a>

  <?php if ($interne): ?>
  <div class="nav-section-label">PARC & CLIENTS</div>
  <?php if (in_array($role, ['admin','technicien'], true)): ?>
  <a href="/modules/clients.php" class="nav-item <?= ($activePage??'')==='clients' ? 'active':'' ?>">
    <span class="nav-icon">🏭</span><span class="nav-text">Clients</span>
  </a>
  <?php endif; ?>
  <a href="/modules/machines.php" class="nav-item <?= ($activePage??'')==='machines' ? 'active':'' ?>">
    <span class="nav-icon">🖨️</span><span class="nav-text">Parc machines CTP</span>
  </a>
  <?php if (in_array($role, ['admin','technicien'], true)): ?>
  <a href="/modules/contrats.php" class="nav-item <?= ($activePage??'')==='contrats' ? 'active':'' ?>">
    <span class="nav-icon">📄</span><span class="nav-text">Contrats</span>
  </a>
  <?php endif; ?>

  <div class="nav-section-label">MAINTENANCE & SAV</div>
  <?php if (in_array($role, ['admin','technicien'], true)): ?>
  <a href="/modules/maintenance.php" class="nav-item <?= ($activePage??'')==='maintenance' ? 'active':'' ?>">
    <span class="nav-icon">📅</span><span class="nav-text">Calendrier préventif</span>
  </a>
  <a href="/modules/planning.php" class="nav-item <?= ($activePage??'')==='planning' ? 'active':'' ?>">
    <span class="nav-icon">🗓️</span><span class="nav-text">Planning &amp; tournées</span>
  </a>
  <?php endif; ?>
  <a href="/modules/interventions.php" class="nav-item <?= ($activePage??'')==='interventions' ? 'active':'' ?>">
    <span class="nav-icon">🔧</span><span class="nav-text">Interventions</span>
  </a>

  <div class="nav-section-label">PIÈCES DÉTACHÉES</div>
  <a href="/modules/pieces.php" class="nav-item <?= ($activePage??'')==='pieces' ? 'active':'' ?>">
    <span class="nav-icon">⚙️</span><span class="nav-text">Catalogue & stock</span>
  </a>
  <?php if (in_array($role, ['admin','magasinier'], true)): ?>
  <a href="/modules/commandes.php" class="nav-item <?= ($activePage??'')==='commandes' ? 'active':'' ?>">
    <span class="nav-icon">📦</span><span class="nav-text">Commandes fournisseur</span>
  </a>
  <?php endif; ?>
  <?php endif; ?>

  <?php if ($role === 'client'): ?>
  <div class="nav-section-label">MON PARC</div>
  <a href="/modules/mes_machines.php" class="nav-item <?= ($activePage??'')==='mes_machines' ? 'active':'' ?>">
    <span class="nav-icon">🖨️</span><span class="nav-text">Mes machines</span>
  </a>
  <a href="/modules/mes_interventions.php" class="nav-item <?= ($activePage??'')==='mes_interventions' ? 'active':'' ?>">
    <span class="nav-icon">🔧</span><span class="nav-text">Mes interventions</span>
  </a>
  <?php endif; ?>

  <?php if ($role === 'admin'): ?>
  <div class="nav-section-label">ADMINISTRATION</div>
  <a href="/modules/utilisateurs.php" class="nav-item <?= ($activePage??'')==='utilisateurs' ? 'active':'' ?>">
    <span class="nav-icon">👥</span><span class="nav-text">Utilisateurs</span>
  </a>
  <?php endif; ?>

  <div class="sidebar-footer">
    <div class="user-badge">
      <div class="user-avatar"><?= htmlspecialchars($user['avatar'] ?? 'CT') ?></div>
      <div class="user-info">
        <div class="user-name"><?= htmlspecialchars($user['nom'] ?? '') ?></div>
        <div class="user-role"><?= htmlspecialchars($roleLabels[$role] ?? $role) ?></div>
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
      <?php if (in_array($role, ['admin','magasinier'], true)):
        try {
          $db = getDB();
          $alertes = (int)$db->query("SELECT COUNT(*) FROM v_pieces_stock_bas")->fetchColumn();
          if ($alertes > 0): ?>
      <a href="/modules/pieces.php" class="alert-badge">🔴 <?= $alertes ?> pièce<?= $alertes > 1 ? 's' : '' ?> sous seuil</a>
      <?php endif; } catch (Exception $e) {} endif; ?>
    </div>
  </header>
  <main class="content">
