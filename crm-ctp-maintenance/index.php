<?php
require_once 'config.php';
requireLogin();
$user = currentUser();
$pageTitle = 'Tableau de bord';
$activePage = 'dashboard';
require_once 'includes/header.php';
?>

<?php if (in_array($user['role'], ['admin','technicien','magasinier'], true)): ?>
<div class="kpi-grid" id="dash-kpis">
  <div class="kpi-card navy"><div class="kpi-label">Machines en service</div><div class="kpi-value" id="kpi-machines">—</div></div>
  <div class="kpi-card red"><div class="kpi-label">Machines en panne</div><div class="kpi-value" id="kpi-pannes">—</div></div>
  <div class="kpi-card teal"><div class="kpi-label">Interventions ouvertes</div><div class="kpi-value" id="kpi-interventions">—</div></div>
  <div class="kpi-card gold"><div class="kpi-label">Maintenances à échéance (30j)</div><div class="kpi-value" id="kpi-maint-due">—</div></div>
  <div class="kpi-card"><div class="kpi-label">Pièces sous seuil</div><div class="kpi-value" id="kpi-stock-bas">—</div></div>
  <div class="kpi-card"><div class="kpi-label">Commandes pièces en cours</div><div class="kpi-value" id="kpi-commandes">—</div></div>
</div>

<div class="dash-grid">
  <div class="section-card">
    <div class="section-header"><div class="section-title">🔧 Interventions à traiter</div>
      <div class="section-actions"><a href="/modules/interventions.php" class="btn btn-outline btn-sm">Tout voir</a></div></div>
    <div class="table-wrap"><table>
      <thead><tr><th>N°</th><th>Machine</th><th>Client</th><th>Priorité</th><th>Statut</th></tr></thead>
      <tbody id="tb-interventions"><tr><td colspan="5" style="text-align:center;padding:24px;color:var(--text-muted)">Chargement…</td></tr></tbody>
    </table></div>
  </div>
  <div class="section-card">
    <div class="section-header"><div class="section-title">📅 Prochaines maintenances préventives</div>
      <div class="section-actions"><a href="/modules/maintenance.php" class="btn btn-outline btn-sm">Calendrier</a></div></div>
    <div class="table-wrap"><table>
      <thead><tr><th>Échéance</th><th>Client</th><th>Machine</th><th>Type</th><th>Reste</th></tr></thead>
      <tbody id="tb-maint"><tr><td colspan="5" style="text-align:center;padding:24px;color:var(--text-muted)">Chargement…</td></tr></tbody>
    </table></div>
  </div>
</div>

<script>
(async function () {
  const s = await CTP.api('dashboard_stats');
  if (!s.error) {
    document.getElementById('kpi-machines').textContent = s.machines_en_service ?? '—';
    document.getElementById('kpi-pannes').textContent = s.machines_en_panne ?? '—';
    document.getElementById('kpi-interventions').textContent = s.interventions_ouvertes ?? '—';
    document.getElementById('kpi-maint-due').textContent = s.maintenances_dues ?? '—';
    document.getElementById('kpi-stock-bas').textContent = s.pieces_stock_bas ?? '—';
    document.getElementById('kpi-commandes').textContent = s.commandes_en_cours ?? '—';
  }
  const prioBadge = p => ({urgente:'badge-red',haute:'badge-gold',normale:'badge-navy',basse:'badge-grey'}[p] || 'badge-grey');
  const e = CTP.escape;

  const it = await CTP.api('dashboard_interventions');
  const tbi = document.getElementById('tb-interventions');
  if (Array.isArray(it) && it.length) {
    tbi.innerHTML = it.map(r => `<tr>
      <td><a href="/modules/interventions.php#${r.id}">${e(r.numero)}</a></td>
      <td>${e(r.modele)} <span style="color:var(--text-muted)">${e(r.n_serie)}</span></td>
      <td>${e(r.raison_sociale)}</td>
      <td><span class="badge ${prioBadge(r.priorite)}">${e(r.priorite)}</span></td>
      <td><span class="badge badge-teal">${e(r.statut.replace(/_/g,' '))}</span></td></tr>`).join('');
  } else { tbi.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:24px;color:var(--text-muted)">Aucune intervention ouverte 🎉</td></tr>'; }

  const md = await CTP.api('dashboard_maintenance');
  const tbm = document.getElementById('tb-maint');
  const typeLabel = t => t === 'preventive' ? 'Préventive' : 'Prévisionnelle';
  if (Array.isArray(md) && md.length) {
    tbm.innerHTML = md.map(r => {
      const j = parseInt(r.jours_restants, 10);
      const cls = j < 0 ? 'badge-red' : (j <= 7 ? 'badge-gold' : 'badge-green');
      const txt = j < 0 ? `En retard ${-j}j` : (j === 0 ? "Aujourd'hui" : `${j} j`);
      return `<tr><td>${CTP.formatDate(r.prochaine_maintenance)}</td>
        <td>${e(r.raison_sociale)}</td>
        <td>${e(r.modele)} <span style="color:var(--text-muted)">${e(r.n_serie)}</span></td>
        <td><span class="badge ${r.type==='preventive'?'badge-teal':'badge-gold'}">${typeLabel(r.type)}</span></td>
        <td><span class="badge ${cls}">${txt}</span></td></tr>`;
    }).join('');
  } else { tbm.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:24px;color:var(--text-muted)">Aucune échéance proche</td></tr>'; }
})();
</script>

<?php else: /* portail client */ ?>
<div class="kpi-grid" id="dash-kpis">
  <div class="kpi-card navy"><div class="kpi-label">Mes machines</div><div class="kpi-value" id="kpi-machines">—</div></div>
  <div class="kpi-card teal"><div class="kpi-label">Interventions en cours</div><div class="kpi-value" id="kpi-interventions">—</div></div>
  <div class="kpi-card red"><div class="kpi-label">Machines en panne</div><div class="kpi-value" id="kpi-pannes">—</div></div>
</div>
<div class="section-card">
  <div class="section-header"><div class="section-title">👋 Bienvenue, <?= htmlspecialchars($user['nom']) ?></div></div>
  <div class="empty-placeholder">
    <p class="empty-placeholder-main">Consultez l'état de <strong>votre parc CTP</strong> et le suivi de vos interventions.</p>
    <p class="empty-placeholder-desc">Depuis <strong>Mes interventions</strong>, vous pouvez signaler une panne : notre équipe SAV la prend en charge et vous suivez son avancement en temps réel.</p>
  </div>
</div>
<script>
(async function () {
  const s = await CTP.api('mes_dashboard_stats');
  if (s.error) return;
  document.getElementById('kpi-machines').textContent = s.mes_machines ?? '—';
  document.getElementById('kpi-interventions').textContent = s.mes_interventions_ouvertes ?? '—';
  document.getElementById('kpi-pannes').textContent = s.mes_machines_en_panne ?? '—';
})();
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
