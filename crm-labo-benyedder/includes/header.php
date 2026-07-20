<?php requireLogin(); $user = currentUser(); $role = $user['role'] ?? 'client_terme';
$roleLabels = ['admin'=>'Administrateur','labo'=>'Laboratoire central','production'=>'Production','franchise'=>'Franchise','point_vente'=>'Point de vente','client_terme'=>'Client à terme'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ben Yedder CRM — <?= htmlspecialchars($pageTitle ?? 'Tableau de bord') ?></title>
<link rel="stylesheet" href="/assets/style.css">
<script src="/assets/app.js"></script>
</head>
<body>
<nav class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <span class="sidebar-logo">🥐</span>
    <span class="sidebar-name">BEN YEDDER</span>
  </div>

  <div class="nav-section-label">PILOTAGE</div>
  <a href="/index.php" class="nav-item <?= ($activePage??'')==='dashboard' ? 'active':'' ?>">
    <span class="nav-icon">⚡</span><span class="nav-text">Tableau de bord</span>
  </a>

  <?php if (in_array($role, ['admin','labo'], true)): ?>
  <div class="nav-section-label">CLIENTS & VENTE</div>
  <a href="/modules/clients.php" class="nav-item <?= ($activePage??'')==='clients' ? 'active':'' ?>">
    <span class="nav-icon">🏪</span><span class="nav-text">Clients à terme</span>
  </a>
  <a href="/modules/franchises.php" class="nav-item <?= ($activePage??'')==='franchises' ? 'active':'' ?>">
    <span class="nav-icon">🤝</span><span class="nav-text">Franchises</span>
  </a>
  <a href="/modules/points_vente.php" class="nav-item <?= ($activePage??'')==='points_vente' ? 'active':'' ?>">
    <span class="nav-icon">🏬</span><span class="nav-text">Points de vente</span>
  </a>
  <?php endif; ?>

  <?php if (in_array($role, ['admin','labo'], true)): ?>
  <a href="/modules/commandes.php" class="nav-item <?= ($activePage??'')==='commandes' ? 'active':'' ?>">
    <span class="nav-icon">📦</span><span class="nav-text">Commandes</span>
  </a>
  <?php elseif (in_array($role, ['franchise','client_terme'], true)): ?>
  <a href="/modules/mes_commandes.php" class="nav-item <?= ($activePage??'')==='mes_commandes' ? 'active':'' ?>">
    <span class="nav-icon">📦</span><span class="nav-text">Mes commandes</span>
  </a>
  <?php elseif ($role === 'point_vente'): ?>
  <a href="/modules/mes_commandes.php" class="nav-item <?= ($activePage??'')==='mes_commandes' ? 'active':'' ?>">
    <span class="nav-icon">📦</span><span class="nav-text">Réapprovisionnement</span>
  </a>
  <?php endif; ?>

  <?php if (in_array($role, ['admin','labo'], true)): ?>
  <div class="nav-section-label">CATALOGUE</div>
  <a href="/modules/catalogue.php" class="nav-item <?= ($activePage??'')==='catalogue' ? 'active':'' ?>">
    <span class="nav-icon">📖</span><span class="nav-text">Produits &amp; Recettes</span>
  </a>
  <a href="/modules/catalogue_comptes.php" class="nav-item <?= ($activePage??'')==='catalogue_comptes' ? 'active':'' ?>">
    <span class="nav-icon">📋</span><span class="nav-text">Catalogue par compte</span>
  </a>
  <?php elseif (in_array($role, ['franchise','client_terme','point_vente'], true)): ?>
  <div class="nav-section-label">CATALOGUE</div>
  <a href="/modules/mes_produits.php" class="nav-item <?= ($activePage??'')==='mes_produits' ? 'active':'' ?>">
    <span class="nav-icon">📖</span><span class="nav-text">Produits &amp; tarifs</span>
  </a>
  <?php endif; ?>

  <?php if (in_array($role, ['admin','labo','production'], true)): ?>
  <div class="nav-section-label">PRODUCTION</div>
  <a href="/modules/production.php" class="nav-item <?= ($activePage??'')==='production' ? 'active':'' ?>">
    <span class="nav-icon">⚙️</span><span class="nav-text"><?= $role==='production' ? 'Ma cuisine' : 'Ordres de fabrication' ?></span>
  </a>
  <?php if (in_array($role, ['admin','labo'], true)): ?>
  <a href="/modules/cuisines.php" class="nav-item <?= ($activePage??'')==='cuisines' ? 'active':'' ?>">
    <span class="nav-icon">🍳</span><span class="nav-text">Cuisines de production</span>
  </a>
  <?php endif; ?>
  <?php endif; ?>

  <?php if (in_array($role, ['admin','labo'], true)): ?>
  <div class="nav-section-label">STOCK</div>
  <a href="/modules/stock_central.php" class="nav-item <?= ($activePage??'')==='stock_central' ? 'active':'' ?>">
    <span class="nav-icon">📡</span><span class="nav-text">Stock temps réel</span>
  </a>
  <a href="/modules/stock.php" class="nav-item <?= ($activePage??'')==='stock' ? 'active':'' ?>">
    <span class="nav-icon">📊</span><span class="nav-text">Stock & matières</span>
  </a>
  <a href="/modules/livraisons.php" class="nav-item <?= ($activePage??'')==='livraisons' ? 'active':'' ?>">
    <span class="nav-icon">🚚</span><span class="nav-text">Livraisons / Dispatch</span>
  </a>
  <?php endif; ?>

  <?php if ($role === 'point_vente'): ?>
  <div class="nav-section-label">CAISSE</div>
  <a href="/modules/caisse.php" class="nav-item <?= ($activePage??'')==='caisse' ? 'active':'' ?>">
    <span class="nav-icon">💰</span><span class="nav-text">Vente passager</span>
  </a>
  <a href="/modules/mon_stock.php" class="nav-item <?= ($activePage??'')==='mon_stock' ? 'active':'' ?>">
    <span class="nav-icon">📊</span><span class="nav-text">Mon stock vitrine</span>
  </a>
  <?php endif; ?>

  <?php if (in_array($role, ['franchise','client_terme'], true)): ?>
  <div class="nav-section-label">STOCK</div>
  <a href="/modules/mon_stock_client.php" class="nav-item <?= ($activePage??'')==='mon_stock_client' ? 'active':'' ?>">
    <span class="nav-icon">📊</span><span class="nav-text">Mon stock</span>
  </a>
  <?php endif; ?>

  <div class="nav-section-label">FACTURATION</div>
  <?php if ($role === 'admin' || $role === 'labo'): ?>
  <a href="/modules/facturation.php" class="nav-item <?= ($activePage??'')==='facturation' ? 'active':'' ?>">
    <span class="nav-icon">🧾</span><span class="nav-text">Factures & paiements</span>
  </a>
  <?php else: ?>
  <a href="/modules/mes_factures.php" class="nav-item <?= ($activePage??'')==='mes_factures' ? 'active':'' ?>">
    <span class="nav-icon">🧾</span><span class="nav-text">Mes factures</span>
  </a>
  <?php endif; ?>

  <?php if ($role === 'admin'): ?>
  <div class="nav-section-label">ANALYSE</div>
  <a href="/modules/statistiques.php" class="nav-item <?= ($activePage??'')==='statistiques' ? 'active':'' ?>">
    <span class="nav-icon">📈</span><span class="nav-text">Statistiques</span>
  </a>
  <a href="/modules/evenements.php" class="nav-item <?= ($activePage??'')==='evenements' ? 'active':'' ?>">
    <span class="nav-icon">🎉</span><span class="nav-text">Événements spéciaux</span>
  </a>
  <div class="nav-section-label">ADMINISTRATION</div>
  <a href="/modules/utilisateurs.php" class="nav-item <?= ($activePage??'')==='utilisateurs' ? 'active':'' ?>">
    <span class="nav-icon">👥</span><span class="nav-text">Utilisateurs</span>
  </a>
  <a href="/modules/parametres.php" class="nav-item <?= ($activePage??'')==='parametres' ? 'active':'' ?>">
    <span class="nav-icon">🛠️</span><span class="nav-text">Paramètres & fonctionnalités</span>
  </a>
  <?php endif; ?>

  <div class="sidebar-footer">
    <div class="user-badge">
      <div class="user-avatar"><?= htmlspecialchars($user['avatar'] ?? 'BY') ?></div>
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
      <?php if (in_array($role, ['admin','labo'], true)):
        try {
          $db = getDB();
          $alertes = $db->query("SELECT COUNT(*) FROM v_stock_bas")->fetchColumn();
          if ($alertes > 0): ?>
      <a href="/modules/stock.php" class="alert-badge">🔴 <?= (int)$alertes ?> matière<?= $alertes > 1 ? 's' : '' ?> sous seuil</a>
      <?php endif; } catch (Exception $e) {} endif; ?>
    </div>
  </header>
  <main class="content">
