<?php
require_once '../config.php';
requireRole(['admin','technicien']);
$pageTitle = 'Calendrier préventif';
$activePage = 'maintenance';
require_once '../includes/header.php';
?>
<div class="kpi-grid">
  <div class="kpi-card red"><div class="kpi-label">En retard</div><div class="kpi-value" id="kpi-retard">—</div></div>
  <div class="kpi-card gold"><div class="kpi-label">Cette semaine (7 j)</div><div class="kpi-value" id="kpi-semaine">—</div></div>
  <div class="kpi-card teal"><div class="kpi-label">Ce mois (30 j)</div><div class="kpi-value" id="kpi-mois">—</div></div>
</div>

<div class="section-card">
  <div class="section-header">
    <div class="section-title">📅 Maintenances préventives à planifier</div>
    <div class="section-actions"><a href="/modules/contrats.php" class="btn btn-outline btn-sm">Gérer les contrats</a></div>
  </div>
  <div class="filters-bar">
    <input type="text" id="search" placeholder="🔍 Contrat, client, machine…">
    <select id="f-horizon" onchange="render()">
      <option value="">Toutes les échéances</option>
      <option value="0">En retard</option>
      <option value="7">Sous 7 jours</option>
      <option value="30" selected>Sous 30 jours</option>
      <option value="90">Sous 90 jours</option>
    </select>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Contrat</th><th>Client</th><th>Machine</th><th>Échéance</th><th>Reste</th><th>SLA</th><th>Actions</th></tr></thead>
      <tbody id="body"><tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-muted)">Chargement…</td></tr></tbody>
    </table>
  </div>
  <div style="padding:10px 14px;color:var(--text-muted);font-size:13px" id="count"></div>
</div>

<!-- Modal planification -->
<div class="modal-overlay" id="modal-plan">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Planifier la maintenance</div><button class="modal-close" onclick="CTP.closeModal('modal-plan')">✕</button></div>
    <div class="modal-body">
      <div class="alert-box info">Crée une intervention préventive planifiée et fait avancer l'échéance du contrat selon sa fréquence.</div>
      <input type="hidden" id="p-contrat">
      <div id="p-info" style="margin-bottom:12px"></div>
      <div class="form-group"><label>Date planifiée</label><input type="datetime-local" id="p-date"></div>
      <div class="form-group" style="margin-top:12px"><label>Technicien assigné</label><select id="p-tech"></select></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="CTP.closeModal('modal-plan')">Annuler</button>
      <button class="btn btn-primary" onclick="planifier()">Planifier l'intervention</button>
    </div>
  </div>
</div>

<script>
let all = [], techniciens = [];
async function load() {
  const opt = await CTP.api('int_options'); techniciens = opt.techniciens || [];
  all = await CTP.api('maint_list'); render();
}
function computeKpis() {
  let r=0,s=0,m=0;
  all.forEach(x => { const j = parseInt(x.jours_restants,10); if (j<0) r++; if (j>=0&&j<=7) s++; if (j>=0&&j<=30) m++; });
  document.getElementById('kpi-retard').textContent = r;
  document.getElementById('kpi-semaine').textContent = s;
  document.getElementById('kpi-mois').textContent = m;
}
function render() {
  const e = CTP.escape;
  computeKpis();
  const q = document.getElementById('search').value.toLowerCase();
  const h = document.getElementById('f-horizon').value;
  const rows = all.filter(x => {
    const j = parseInt(x.jours_restants, 10);
    if (h === '0' && j >= 0) return false;
    if (h !== '' && h !== '0' && j > parseInt(h,10)) return false;
    if (q && !(`${x.numero} ${x.raison_sociale} ${x.modele||''}`).toLowerCase().includes(q)) return false;
    return true;
  });
  const body = document.getElementById('body');
  if (!rows.length) { body.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-muted)">Aucune échéance</td></tr>'; }
  else body.innerHTML = rows.map(x => {
    const j = parseInt(x.jours_restants, 10);
    const cls = j < 0 ? 'badge-red' : (j <= 7 ? 'badge-gold' : 'badge-green');
    const txt = j < 0 ? `Retard ${-j} j` : (j === 0 ? "Aujourd'hui" : `${j} j`);
    return `<tr>
      <td><strong>${e(x.numero)}</strong></td>
      <td>${e(x.raison_sociale)}</td>
      <td>${x.modele ? e(x.modele)+' <span style="color:var(--text-muted)">'+e(x.n_serie)+'</span>' : '<span class="badge badge-navy">Parc complet</span>'}</td>
      <td>${CTP.formatDate(x.prochaine_maintenance)}</td>
      <td><span class="badge ${cls}">${txt}</span></td>
      <td>${x.sla_heures ? x.sla_heures+' h' : '—'}</td>
      <td><button class="btn btn-teal btn-sm" onclick="openPlan(${x.contrat_id})">Planifier</button></td>
    </tr>`;
  }).join('');
  document.getElementById('count').textContent = `${rows.length} échéance(s)`;
}
function openPlan(contratId) {
  const x = all.find(a => String(a.contrat_id) === String(contratId));
  document.getElementById('p-contrat').value = contratId;
  document.getElementById('p-info').innerHTML = x
    ? `<span class="badge badge-navy">${CTP.escape(x.numero)}</span> <span class="badge badge-grey">${CTP.escape(x.raison_sociale)}</span>` : '';
  const d = x && x.prochaine_maintenance ? x.prochaine_maintenance + 'T09:00' : '';
  document.getElementById('p-date').value = d;
  document.getElementById('p-tech').innerHTML = '<option value="">— Non assigné —</option>' +
    techniciens.map(t => `<option value="${t.id}">${CTP.escape(t.nom)}</option>`).join('');
  CTP.openModal('modal-plan');
}
async function planifier() {
  const d = {
    contrat_id: document.getElementById('p-contrat').value,
    date_planifiee: document.getElementById('p-date').value ? document.getElementById('p-date').value.replace('T',' ')+':00' : '',
    technicien_id: document.getElementById('p-tech').value,
  };
  const r = await CTP.api('maint_planifier', d);
  if (r.error) return CTP.toast(r.error, 'error');
  CTP.toast('Intervention ' + r.numero + ' planifiée'); CTP.closeModal('modal-plan'); load();
}
load();
</script>
<?php require_once '../includes/footer.php'; ?>
