<?php
require_once '../config.php';
requireRole(['client']);
$pageTitle = 'Mes machines';
$activePage = 'mes_machines';
require_once '../includes/header.php';
?>
<div class="section-card">
  <div class="section-header"><div class="section-title">🖨️ Mon parc CTP</div></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Modèle</th><th>N° série</th><th>Techno</th><th>Format</th><th>Compteur</th><th>Garantie</th><th>Statut</th></tr></thead>
      <tbody id="body"><tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-muted)">Chargement…</td></tr></tbody>
    </table>
  </div>
</div>
<script>
const technoLabel = { thermique:'Thermique', violet:'Violet', uv:'UV', flexo:'Flexo', autre:'Autre' };
const statutBadge = s => ({en_service:'badge-green', maintenance:'badge-gold', en_panne:'badge-red', hors_service:'badge-grey', retire:'badge-grey'}[s] || 'badge-grey');
const statutLabel = s => ({en_service:'En service', maintenance:'Maintenance', en_panne:'En panne', hors_service:'Hors service', retire:'Retiré'}[s] || s);
(async function () {
  const rows = await CTP.api('mes_machines');
  const e = CTP.escape;
  const body = document.getElementById('body');
  if (!Array.isArray(rows) || !rows.length) { body.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-muted)">Aucune machine enregistrée</td></tr>'; return; }
  body.innerHTML = rows.map(m => `<tr>
    <td><strong>${e(m.modele)}</strong>${m.gamme ? '<br><span style="color:var(--text-muted)">'+e(m.gamme)+'</span>':''}</td>
    <td>${e(m.n_serie)}</td>
    <td><span class="badge badge-navy">${technoLabel[m.technologie]||e(m.technologie)}</span></td>
    <td>${e(m.format) || '—'}</td>
    <td class="num">${CTP.formatNumber(m.compteur_plaques)}</td>
    <td>${CTP.formatDate(m.date_fin_garantie)}</td>
    <td><span class="badge ${statutBadge(m.statut)}">${statutLabel(m.statut)}</span></td>
  </tr>`).join('');
})();
</script>
<?php require_once '../includes/footer.php'; ?>
