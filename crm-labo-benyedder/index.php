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
    <p class="empty-placeholder-main">Le flux complet est opérationnel : Commandes → Production → Stock → Livraisons → Facturation.</p>
    <p class="empty-placeholder-desc">Créez une commande, confirmez-la, générez un ordre de fabrication depuis Production, clôturez-le, dispatchez-la depuis Livraisons, puis facturez-la — chaque étape met à jour le stock et les statistiques automatiquement.</p>
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
<?php elseif ($user['role'] === 'point_vente'): ?>
<div class="kpi-grid" id="dash-kpis">
  <div class="kpi-card teal"><div class="kpi-label">Ventes aujourd'hui</div><div class="kpi-value" id="kpi-ventes-jour">—</div></div>
  <div class="kpi-card navy"><div class="kpi-label">Montant encaissé aujourd'hui</div><div class="kpi-value" id="kpi-montant-jour">—</div></div>
  <div class="kpi-card red"><div class="kpi-label">Produits épuisés en vitrine</div><div class="kpi-value" id="kpi-epuises">—</div></div>
</div>
<div class="section-card">
  <div class="section-header"><div class="section-title">🥐 Bienvenue, <?= htmlspecialchars($user['nom']) ?></div></div>
  <div class="empty-placeholder">
    <p class="empty-placeholder-main">Encaissez vos ventes depuis <strong>Vente passager</strong>, suivez votre stock vitrine, et commandez votre réapprovisionnement au labo.</p>
  </div>
</div>
<script>
(async function () {
  const s = await LABO.api('mes_dashboard_stats');
  if (s.error) return;
  document.getElementById('kpi-ventes-jour').textContent = s.ventes_jour ?? '—';
  document.getElementById('kpi-montant-jour').textContent = LABO.formatCurrency(s.montant_jour ?? 0);
  document.getElementById('kpi-epuises').textContent = s.produits_epuises ?? '—';
})();
</script>
<?php elseif (in_array($user['role'], ['franchise','client_terme'], true)): ?>
<div class="kpi-grid" id="dash-kpis">
  <div class="kpi-card navy"><div class="kpi-label">Commandes en cours</div><div class="kpi-value" id="kpi-commandes">—</div></div>
  <div class="kpi-card <?= '' ?>" id="kpi-encours-card"><div class="kpi-label">Encours</div><div class="kpi-value" id="kpi-encours">—</div></div>
  <div class="kpi-card gold"><div class="kpi-label">Déclarations en attente</div><div class="kpi-value" id="kpi-declarations">—</div></div>
</div>
<div class="section-card">
  <div class="section-header"><div class="section-title">🥐 Bienvenue, <?= htmlspecialchars($user['nom']) ?></div></div>
  <div class="empty-placeholder">
    <p class="empty-placeholder-main">Passez une nouvelle commande depuis <strong>Mes commandes</strong>, et suivez vos factures et votre encours depuis <strong>Mes factures</strong>.</p>
  </div>
</div>
<script>
(async function () {
  const s = await LABO.api('mes_dashboard_stats');
  if (s.error) return;
  document.getElementById('kpi-commandes').textContent = s.commandes_en_cours ?? '—';
  document.getElementById('kpi-encours').textContent = LABO.formatCurrency(s.encours ?? 0);
  if ((s.encours ?? 0) > 0) document.getElementById('kpi-encours-card').classList.add('red');
  document.getElementById('kpi-declarations').textContent = s.declarations_en_attente ?? '—';
})();
</script>
<?php else: ?>
<div class="section-card">
  <div class="section-header"><div class="section-title">🥐 Bienvenue, <?= htmlspecialchars($user['nom']) ?></div></div>
  <div class="empty-placeholder">
    <p class="empty-placeholder-main">Consultez les <strong>Ordres de fabrication</strong> qui vous sont assignés.</p>
  </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
