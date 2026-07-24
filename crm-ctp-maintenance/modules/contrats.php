<?php
require_once '../config.php';
requireRole(['admin','technicien']);
$user = currentUser();
$estAdmin = $user['role'] === 'admin';
$pageTitle = 'Contrats de maintenance';
$activePage = 'contrats';
require_once '../includes/header.php';
?>
<div class="section-card">
  <div class="section-header">
    <div class="section-title">📄 Contrats de maintenance</div>
    <div class="section-actions"><button class="btn btn-primary" onclick="openAdd()">+ Nouveau contrat</button></div>
  </div>
  <div class="filters-bar">
    <input type="text" id="search" placeholder="🔍 N°, client, machine…">
    <select id="f-statut" onchange="render()">
      <option value="">Tous</option>
      <option value="actif" selected>Actifs</option>
      <option value="suspendu">Suspendus</option>
      <option value="expire">Expirés</option>
    </select>
    <select id="f-type" onchange="render()">
      <option value="">Tous types</option>
      <option value="preventif">Préventif</option>
      <option value="full_service">Full service</option>
      <option value="garantie">Garantie</option>
      <option value="a_la_demande">À la demande</option>
    </select>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>N°</th><th>Client</th><th>Portée</th><th>Type</th><th>Fréquence</th><th>Prochaine</th><th>Montant/an</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody id="body"><tr><td colspan="9" style="text-align:center;padding:30px;color:var(--text-muted)">Chargement…</td></tr></tbody>
    </table>
  </div>
  <div style="padding:10px 14px;color:var(--text-muted);font-size:13px" id="count"></div>
</div>

<div class="modal-overlay" id="modal-ctr">
  <div class="modal" style="max-width:720px">
    <div class="modal-header"><div class="modal-title" id="modal-title">Contrat</div><button class="modal-close" onclick="CTP.closeModal('modal-ctr')">✕</button></div>
    <div class="modal-body">
      <input type="hidden" id="k-id">
      <div class="form-grid">
        <div class="form-group full"><label>Client *</label><select id="k-client" onchange="loadMachines()"></select></div>
        <div class="form-group full"><label>Machine (vide = tout le parc du client)</label><select id="k-machine"><option value="">Tout le parc</option></select></div>
        <div class="form-group"><label>Type</label><select id="k-type">
          <option value="preventif">Préventif</option><option value="full_service">Full service</option>
          <option value="garantie">Garantie</option><option value="a_la_demande">À la demande</option></select></div>
        <div class="form-group"><label>Statut</label><select id="k-statut">
          <option value="actif">Actif</option><option value="suspendu">Suspendu</option><option value="expire">Expiré</option></select></div>
        <div class="form-group"><label>Date de début *</label><input type="date" id="k-debut"></div>
        <div class="form-group"><label>Date de fin</label><input type="date" id="k-fin"></div>
        <div class="form-group"><label>Fréquence préventive (jours)</label><input type="number" id="k-freq" min="0" placeholder="ex : 90"></div>
        <div class="form-group"><label>Prochaine maintenance</label><input type="date" id="k-proch"></div>
        <div class="form-group"><label>Montant annuel (TND)</label><input type="number" step="0.001" id="k-montant" value="0"></div>
        <div class="form-group"><label>SLA intervention (heures)</label><input type="number" id="k-sla" min="0" placeholder="ex : 24"></div>
        <div class="form-group full"><label>Notes</label><textarea id="k-notes"></textarea></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="CTP.closeModal('modal-ctr')">Annuler</button>
      <button class="btn btn-primary" onclick="save()">Enregistrer</button>
    </div>
  </div>
</div>

<script>
const estAdmin = <?= $estAdmin ? 'true':'false' ?>;
let all = [], clients = [], allMachines = [];
const typeLabel = { preventif:'Préventif', full_service:'Full service', garantie:'Garantie', a_la_demande:'À la demande' };
const statutBadge = s => ({actif:'badge-green', suspendu:'badge-gold', expire:'badge-grey'}[s] || 'badge-grey');

async function load() {
  const opt = await CTP.api('mac_options'); clients = opt.clients || [];
  allMachines = await CTP.api('mac_list');
  all = await CTP.api('ctr_list'); render();
}
function render() {
  const e = CTP.escape;
  const q = document.getElementById('search').value.toLowerCase();
  const fs = document.getElementById('f-statut').value, ft = document.getElementById('f-type').value;
  const rows = all.filter(c => {
    if (fs && c.statut !== fs) return false;
    if (ft && c.type !== ft) return false;
    if (q && !(`${c.numero} ${c.raison_sociale} ${c.modele||''}`).toLowerCase().includes(q)) return false;
    return true;
  });
  const body = document.getElementById('body');
  if (!rows.length) { body.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:30px;color:var(--text-muted)">Aucun contrat</td></tr>'; }
  else body.innerHTML = rows.map(c => {
    let ech = '—';
    if (c.prochaine_maintenance) {
      const j = parseInt(c.jours_restants, 10);
      const cls = j < 0 ? 'badge-red' : (j <= 7 ? 'badge-gold' : 'badge-green');
      ech = `${CTP.formatDate(c.prochaine_maintenance)} <span class="badge ${cls}">${j<0?('retard '+(-j)+'j'):(j+' j')}</span>`;
    }
    return `<tr>
      <td><strong>${e(c.numero)}</strong></td>
      <td>${e(c.raison_sociale)}</td>
      <td>${c.machine_id ? e(c.modele)+' <span style="color:var(--text-muted)">'+e(c.n_serie)+'</span>' : '<span class="badge badge-navy">Tout le parc</span>'}</td>
      <td>${typeLabel[c.type]||e(c.type)}</td>
      <td>${c.frequence_jours ? c.frequence_jours+' j' : '—'}</td>
      <td>${ech}</td>
      <td class="num">${CTP.formatCurrency(c.montant_annuel)}</td>
      <td><span class="badge ${statutBadge(c.statut)}">${e(c.statut)}</span></td>
      <td>
        <button class="btn btn-outline btn-sm" onclick="edit(${c.id})">Modifier</button>
        ${estAdmin ? `<button class="btn btn-danger btn-sm" onclick="del(${c.id})">Suppr.</button>`:''}
      </td></tr>`;
  }).join('');
  document.getElementById('count').textContent = `${rows.length} contrat(s)`;
}
function clientOptions(sel) { return clients.map(c => `<option value="${c.id}" ${c.id==sel?'selected':''}>${CTP.escape(c.raison_sociale)}</option>`).join(''); }
function loadMachines(sel) {
  const cid = document.getElementById('k-client').value;
  const ms = allMachines.filter(m => String(m.client_id) === String(cid));
  document.getElementById('k-machine').innerHTML = '<option value="">Tout le parc</option>' +
    ms.map(m => `<option value="${m.id}" ${m.id==sel?'selected':''}>${CTP.escape(m.modele)} (${CTP.escape(m.n_serie)})</option>`).join('');
}
function fill(c) {
  c = c || {};
  document.getElementById('k-id').value = c.id || '';
  document.getElementById('k-client').innerHTML = '<option value="">— Choisir —</option>' + clientOptions(c.client_id);
  loadMachines(c.machine_id);
  document.getElementById('k-type').value = c.type || 'preventif';
  document.getElementById('k-statut').value = c.statut || 'actif';
  document.getElementById('k-debut').value = c.date_debut || '';
  document.getElementById('k-fin').value = c.date_fin || '';
  document.getElementById('k-freq').value = c.frequence_jours || '';
  document.getElementById('k-proch').value = c.prochaine_maintenance || '';
  document.getElementById('k-montant').value = c.montant_annuel || 0;
  document.getElementById('k-sla').value = c.sla_heures || '';
  document.getElementById('k-notes').value = c.notes || '';
}
function openAdd() { document.getElementById('modal-title').textContent = 'Nouveau contrat'; fill(null); CTP.openModal('modal-ctr'); }
async function edit(id) {
  const c = await CTP.api('ctr_get', { id });
  if (c.error) return CTP.toast(c.error, 'error');
  document.getElementById('modal-title').textContent = 'Modifier ' + c.numero; fill(c); CTP.openModal('modal-ctr');
}
async function save() {
  const d = {
    id: document.getElementById('k-id').value,
    client_id: document.getElementById('k-client').value,
    machine_id: document.getElementById('k-machine').value,
    type: document.getElementById('k-type').value,
    statut: document.getElementById('k-statut').value,
    date_debut: document.getElementById('k-debut').value,
    date_fin: document.getElementById('k-fin').value,
    frequence_jours: document.getElementById('k-freq').value,
    prochaine_maintenance: document.getElementById('k-proch').value,
    montant_annuel: document.getElementById('k-montant').value,
    sla_heures: document.getElementById('k-sla').value,
    notes: document.getElementById('k-notes').value.trim(),
  };
  if (!d.client_id) return CTP.toast('Client requis', 'error');
  if (!d.date_debut) return CTP.toast('Date de début requise', 'error');
  const r = await CTP.api('ctr_save', d);
  if (r.error) return CTP.toast(r.error, 'error');
  CTP.toast('Contrat enregistré'); CTP.closeModal('modal-ctr'); load();
}
async function del(id) {
  if (!CTP.confirmDelete('Supprimer ce contrat ?')) return;
  const r = await CTP.api('ctr_delete', { id });
  if (r.error) return CTP.toast(r.error, 'error');
  CTP.toast('Contrat supprimé'); load();
}
load();
</script>
<?php require_once '../includes/footer.php'; ?>
