<?php
require_once '../config.php';
requireRole(['admin','technicien']);
$pageTitle = 'Calendrier préventif';
$activePage = 'maintenance';
require_once '../includes/header.php';
?>
<div class="kpi-grid">
  <div class="kpi-card red"><div class="kpi-label">Visites en retard</div><div class="kpi-value" id="kpi-retard">—</div></div>
  <div class="kpi-card gold"><div class="kpi-label">Cette semaine (7 j)</div><div class="kpi-value" id="kpi-semaine">—</div></div>
  <div class="kpi-card teal"><div class="kpi-label">Ce mois (30 j)</div><div class="kpi-value" id="kpi-mois">—</div></div>
  <div class="kpi-card navy"><div class="kpi-label">Total planifiées</div><div class="kpi-value" id="kpi-total">—</div></div>
</div>

<div class="section-card">
  <div class="section-header">
    <div class="section-title">📅 Visites préventives planifiées</div>
    <div class="section-actions">
      <button class="btn btn-primary" onclick="openAdd()">+ Planifier une visite</button>
      <a href="/modules/contrats.php" class="btn btn-outline btn-sm">Contrats</a>
    </div>
  </div>
  <div class="filters-bar">
    <input type="text" id="search" placeholder="🔍 Client, machine, contrat…">
    <select id="f-horizon" onchange="render()">
      <option value="">Toutes les échéances</option>
      <option value="0">En retard</option>
      <option value="7">Sous 7 jours</option>
      <option value="30" selected>Sous 30 jours</option>
      <option value="90">Sous 90 jours</option>
    </select>
    <select id="f-type" onchange="render()">
      <option value="">Préventives + prévisionnelles</option>
      <option value="preventive">Préventives (PM)</option>
      <option value="previsionnelle">Prévisionnelles</option>
    </select>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Échéance</th><th>Reste</th><th>Client</th><th>Machine</th><th>Type</th><th>Contrat</th><th>Actions</th></tr></thead>
      <tbody id="body"><tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-muted)">Chargement…</td></tr></tbody>
    </table>
  </div>
  <div style="padding:10px 14px;color:var(--text-muted);font-size:13px" id="count"></div>
</div>

<!-- Modal : marquer réalisée -->
<div class="modal-overlay" id="modal-real">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Marquer la visite réalisée</div><button class="modal-close" onclick="CTP.closeModal('modal-real')">✕</button></div>
    <div class="modal-body">
      <div class="alert-box info">Crée une intervention préventive clôturée, rattachée à cette visite et à son contrat.</div>
      <input type="hidden" id="rl-id">
      <div id="rl-info" style="margin-bottom:12px"></div>
      <div class="form-grid">
        <div class="form-group"><label>Date réalisée</label><input type="date" id="rl-date"></div>
        <div class="form-group"><label>Technicien</label><select id="rl-tech"></select></div>
        <div class="form-group"><label>Temps passé (h)</label><input type="number" step="0.25" id="rl-temps" min="0"></div>
        <div class="form-group full"><label>Compte-rendu</label><textarea id="rl-reso" placeholder="Opérations réalisées, observations…"></textarea></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="CTP.closeModal('modal-real')">Annuler</button>
      <button class="btn btn-teal" onclick="marquerRealisee()">Valider la réalisation</button>
    </div>
  </div>
</div>

<!-- Modal : reporter -->
<div class="modal-overlay" id="modal-report">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Reporter la visite</div><button class="modal-close" onclick="CTP.closeModal('modal-report')">✕</button></div>
    <div class="modal-body">
      <input type="hidden" id="rp-id">
      <div id="rp-info" style="margin-bottom:12px"></div>
      <div class="form-group"><label>Nouvelle date prévue</label><input type="date" id="rp-date"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="CTP.closeModal('modal-report')">Annuler</button>
      <button class="btn btn-primary" onclick="reporter()">Reporter</button>
    </div>
  </div>
</div>

<!-- Modal : planifier une visite -->
<div class="modal-overlay" id="modal-add">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Planifier une visite</div><button class="modal-close" onclick="CTP.closeModal('modal-add')">✕</button></div>
    <div class="modal-body">
      <div class="form-group"><label>Machine *</label><select id="ad-machine"></select></div>
      <div class="form-grid" style="margin-top:12px">
        <div class="form-group"><label>Type</label><select id="ad-type">
          <option value="preventive">Préventive (PM contractuelle)</option>
          <option value="previsionnelle">Prévisionnelle (contrôle)</option></select></div>
        <div class="form-group"><label>Date prévue *</label><input type="date" id="ad-date"></div>
        <div class="form-group"><label>Rang (n° visite)</label><input type="number" id="ad-rang" min="1" placeholder="ex : 1"></div>
        <div class="form-group full"><label>Notes</label><input type="text" id="ad-notes"></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="CTP.closeModal('modal-add')">Annuler</button>
      <button class="btn btn-primary" onclick="ajouter()">Planifier</button>
    </div>
  </div>
</div>

<script>
let all = [], machines = [], techniciens = [];
const typeBadge = t => t === 'preventive' ? 'badge-teal' : 'badge-gold';
const typeLabel = t => t === 'preventive' ? 'Préventive' : 'Prévisionnelle';

async function load() {
  const opt = await CTP.api('mp_options'); machines = opt.machines || []; techniciens = opt.techniciens || [];
  all = await CTP.api('mp_list'); render();
}
function computeKpis() {
  let r=0,s=0,m=0;
  all.forEach(x => { const j = parseInt(x.jours_restants,10); if (j<0) r++; if (j>=0&&j<=7) s++; if (j>=0&&j<=30) m++; });
  document.getElementById('kpi-retard').textContent = r;
  document.getElementById('kpi-semaine').textContent = s;
  document.getElementById('kpi-mois').textContent = m;
  document.getElementById('kpi-total').textContent = all.length;
}
function render() {
  const e = CTP.escape;
  computeKpis();
  const q = document.getElementById('search').value.toLowerCase();
  const h = document.getElementById('f-horizon').value;
  const ft = document.getElementById('f-type').value;
  const rows = all.filter(x => {
    const j = parseInt(x.jours_restants, 10);
    if (ft && x.type !== ft) return false;
    if (h === '0' && j >= 0) return false;
    if (h !== '' && h !== '0' && j > parseInt(h,10)) return false;
    if (q && !(`${x.raison_sociale} ${x.modele||''} ${x.n_serie||''} ${x.contrat_numero||''}`).toLowerCase().includes(q)) return false;
    return true;
  });
  const body = document.getElementById('body');
  if (!rows.length) { body.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-muted)">Aucune visite</td></tr>'; }
  else body.innerHTML = rows.map(x => {
    const j = parseInt(x.jours_restants, 10);
    const cls = j < 0 ? 'badge-red' : (j <= 7 ? 'badge-gold' : 'badge-green');
    const txt = j < 0 ? `Retard ${-j} j` : (j === 0 ? "Aujourd'hui" : `${j} j`);
    return `<tr>
      <td><strong>${CTP.formatDate(x.date_prevue)}</strong></td>
      <td><span class="badge ${cls}">${txt}</span></td>
      <td>${e(x.raison_sociale)}</td>
      <td>${e(x.modele)}<br><span style="color:var(--text-muted)">${e(x.n_serie)}</span></td>
      <td><span class="badge ${typeBadge(x.type)}">${typeLabel(x.type)}${x.rang?(' '+x.rang):''}</span></td>
      <td>${e(x.contrat_numero) || '—'}</td>
      <td>
        <button class="btn btn-teal btn-sm" onclick="openReal(${x.id})">✓ Réalisée</button>
        <button class="btn btn-outline btn-sm" onclick="openReport(${x.id})">Reporter</button>
        <button class="btn btn-danger btn-sm" onclick="annuler(${x.id})">Annuler</button>
      </td></tr>`;
  }).join('');
  document.getElementById('count').textContent = `${rows.length} visite(s)`;
}
function find(id) { return all.find(x => String(x.id) === String(id)); }
function infoBadges(x) {
  const e = CTP.escape;
  return `<span class="badge badge-navy">${e(x.raison_sociale)}</span>
    <span class="badge badge-grey">${e(x.modele)} · ${e(x.n_serie)}</span>
    <span class="badge ${typeBadge(x.type)}">${typeLabel(x.type)}</span>
    <span class="badge badge-grey">Prévue : ${CTP.formatDate(x.date_prevue)}</span>`;
}
function techOptions(sel) {
  return '<option value="">— Non assigné —</option>' + techniciens.map(t => `<option value="${t.id}" ${t.id==sel?'selected':''}>${CTP.escape(t.nom)}</option>`).join('');
}
// Réalisée
function openReal(id) {
  const x = find(id); if (!x) return;
  document.getElementById('rl-id').value = id;
  document.getElementById('rl-info').innerHTML = infoBadges(x);
  document.getElementById('rl-date').value = new Date().toISOString().slice(0,10);
  document.getElementById('rl-tech').innerHTML = techOptions('');
  document.getElementById('rl-temps').value = '';
  document.getElementById('rl-reso').value = '';
  CTP.openModal('modal-real');
}
async function marquerRealisee() {
  const d = {
    id: document.getElementById('rl-id').value,
    date_realisee: document.getElementById('rl-date').value,
    technicien_id: document.getElementById('rl-tech').value,
    temps_passe_h: document.getElementById('rl-temps').value,
    resolution: document.getElementById('rl-reso').value.trim(),
  };
  const r = await CTP.api('mp_marquer_realisee', d);
  if (r.error) return CTP.toast(r.error, 'error');
  CTP.toast('Visite réalisée → intervention ' + r.numero); CTP.closeModal('modal-real'); load();
}
// Reporter
function openReport(id) {
  const x = find(id); if (!x) return;
  document.getElementById('rp-id').value = id;
  document.getElementById('rp-info').innerHTML = infoBadges(x);
  document.getElementById('rp-date').value = x.date_prevue;
  CTP.openModal('modal-report');
}
async function reporter() {
  const r = await CTP.api('mp_reporter', { id: document.getElementById('rp-id').value, date_prevue: document.getElementById('rp-date').value });
  if (r.error) return CTP.toast(r.error, 'error');
  CTP.toast('Visite reportée'); CTP.closeModal('modal-report'); load();
}
async function annuler(id) {
  if (!CTP.confirmDelete('Annuler cette visite planifiée ?')) return;
  const r = await CTP.api('mp_annuler', { id });
  if (r.error) return CTP.toast(r.error, 'error');
  CTP.toast('Visite annulée'); load();
}
// Ajouter
function openAdd() {
  document.getElementById('ad-machine').innerHTML = machines.map(m => `<option value="${m.id}">${CTP.escape(m.raison_sociale)} — ${CTP.escape(m.modele)} (${CTP.escape(m.n_serie)})</option>`).join('');
  document.getElementById('ad-type').value = 'preventive';
  document.getElementById('ad-date').value = '';
  document.getElementById('ad-rang').value = '';
  document.getElementById('ad-notes').value = '';
  CTP.openModal('modal-add');
}
async function ajouter() {
  const d = {
    machine_id: document.getElementById('ad-machine').value,
    type: document.getElementById('ad-type').value,
    date_prevue: document.getElementById('ad-date').value,
    rang: document.getElementById('ad-rang').value,
    notes: document.getElementById('ad-notes').value.trim(),
  };
  if (!d.machine_id) return CTP.toast('Machine requise', 'error');
  if (!d.date_prevue) return CTP.toast('Date requise', 'error');
  const r = await CTP.api('mp_add', d);
  if (r.error) return CTP.toast(r.error, 'error');
  CTP.toast('Visite planifiée'); CTP.closeModal('modal-add'); load();
}
load();
</script>
<?php require_once '../includes/footer.php'; ?>
