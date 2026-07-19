<?php
require_once 'config.php';
requireLogin();
$user = currentUser();
$pageTitle = 'Tableau de bord';
$activePage = 'dashboard';
require_once 'includes/header.php';
?>

<?php if (in_array($user['role'], ['admin','labo'], true)): ?>
<div class="kpi-grid" id="dash-kpis">
  <div class="kpi-card navy"><div class="kpi-label">Clients à terme</div><div class="kpi-value" id="kpi-clients">—</div></div>
  <div class="kpi-card teal"><div class="kpi-label">Franchises</div><div class="kpi-value" id="kpi-franchises">—</div></div>
  <div class="kpi-card gold"><div class="kpi-label">Points de vente</div><div class="kpi-value" id="kpi-pv">—</div></div>
  <div class="kpi-card"><div class="kpi-label">Commandes en cours</div><div class="kpi-value" id="kpi-commandes">—</div></div>
  <div class="kpi-card red"><div class="kpi-label">Matières sous seuil</div><div class="kpi-value" id="kpi-stock-bas">—</div></div>
  <div class="kpi-card"><div class="kpi-label">Factures impayées</div><div class="kpi-value" id="kpi-factures">—</div></div>
</div>

<div class="section-card">
  <div class="section-header"><div class="section-title">🥐 Bienvenue, <?= htmlspecialchars($user['nom']) ?></div></div>
  <div class="empty-placeholder">
    <p class="empty-placeholder-main">Le flux commandes → production → dispatch → facturation est en cours de construction.</p>
    <p class="empty-placeholder-desc">Le catalogue (produits, matières premières, recettes) et les clients à terme sont déjà opérationnels — commencez par là dans le menu.</p>
  </div>
</div>

<script>
(async function () {
  const s = await LABO.api('dashboard_stats');
  if (s.error) return;
  document.getElementById('kpi-clients').textContent = s.clients_actifs ?? '—';
  document.getElementById('kpi-franchises').textContent = s.franchises ?? '—';
  document.getElementById('kpi-pv').textContent = s.points_vente ?? '—';
  document.getElementById('kpi-commandes').textContent = s.commandes_en_cours ?? '—';
  document.getElementById('kpi-stock-bas').textContent = s.matieres_stock_bas ?? '—';
  document.getElementById('kpi-factures').textContent = s.factures_impayees ?? '—';
})();
</script>
<?php else: ?>
<div class="section-card">
  <div class="section-header"><div class="section-title">🥐 Bienvenue, <?= htmlspecialchars($user['nom']) ?></div></div>
  <div class="empty-placeholder">
    <p class="empty-placeholder-main">Votre espace <?= htmlspecialchars($user['role']) ?> est en cours de construction.</p>
    <p class="empty-placeholder-desc">Les commandes, factures et suivis propres à votre compte seront bientôt disponibles ici.</p>
  </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
