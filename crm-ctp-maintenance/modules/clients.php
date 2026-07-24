<?php
require_once '../config.php';
requireRole(['admin','technicien']);
$user = currentUser();
$estAdmin = $user['role'] === 'admin';
$pageTitle = 'Clients';
$activePage = 'clients';
require_once '../includes/header.php';
?>
<div class="section-card">
  <div class="section-header">
    <div class="section-title">🏭 Clients (imprimeries)</div>
    <div class="section-actions"><button class="btn btn-primary" onclick="openAdd()">+ Nouveau client</button></div>
  </div>
  <div class="filters-bar">
    <input type="text" id="search" placeholder="🔍 Raison sociale, code, ville, contact…">
    <select id="f-actif" onchange="render()">
      <option value="1" selected>Actifs</option>
      <option value="0">Inactifs</option>
      <option value="">Tous</option>
    </select>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Code</th><th>Raison sociale</th><th>Ville</th><th>Contact</th><th>Machines</th><th>Interv. en cours</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody id="body"><tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-muted)">Chargement…</td></tr></tbody>
    </table>
  </div>
  <div style="padding:10px 14px;color:var(--text-muted);font-size:13px" id="count"></div>
</div>

<div class="modal-overlay" id="modal-client">
  <div class="modal" style="max-width:700px">
    <div class="modal-header"><div class="modal-title" id="modal-title">Fiche client</div><button class="modal-close" onclick="CTP.closeModal('modal-client')">✕</button></div>
    <div class="modal-body">
      <input type="hidden" id="c-id">
      <div class="form-grid">
        <div class="form-group"><label>Code client</label><input type="text" id="c-code" placeholder="Réf unique (facultatif)"></div>
        <div class="form-group"><label>Raison sociale *</label><input type="text" id="c-nom"></div>
        <div class="form-group"><label>Personne de contact</label><input type="text" id="c-contact"></div>
        <div class="form-group"><label>Secteur</label><input type="text" id="c-secteur" placeholder="Offset, packaging, presse…"></div>
        <div class="form-group"><label>Téléphone</label><input type="text" id="c-tel"></div>
        <div class="form-group"><label>Email</label><input type="email" id="c-email"></div>
        <div class="form-group"><label>Ville</label><input type="text" id="c-ville"></div>
        <div class="form-group"><label>Statut</label><select id="c-actif"><option value="1">Actif</option><option value="0">Inactif</option></select></div>
        <div class="form-group full"><label>Adresse</label><input type="text" id="c-adresse"></div>
        <div class="form-group full"><label>Notes</label><textarea id="c-notes"></textarea></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="CTP.closeModal('modal-client')">Annuler</button>
      <button class="btn btn-primary" onclick="save()">Enregistrer</button>
    </div>
  </div>
</div>

<script>
const estAdmin = <?= $estAdmin ? 'true':'false' ?>;
let all = [];
async function load() { all = await CTP.api('cli_list'); render(); }
function render() {
  const e = CTP.escape;
  const q = document.getElementById('search').value.toLowerCase();
  const fa = document.getElementById('f-actif').value;
  const rows = all.filter(c => {
    if (fa !== '' && String(c.actif) !== fa) return false;
    if (q && !(`${c.code_client||''} ${c.raison_sociale} ${c.ville||''} ${c.contact_nom||''}`).toLowerCase().includes(q)) return false;
    return true;
  });
  const body = document.getElementById('body');
  if (!rows.length) { body.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-muted)">Aucun client</td></tr>'; }
  else body.innerHTML = rows.map(c => `<tr>
    <td>${e(c.code_client) || '—'}</td>
    <td><strong>${e(c.raison_sociale)}</strong></td>
    <td>${e(c.ville) || '—'}</td>
    <td>${e(c.contact_nom) || '—'}<br><span style="color:var(--text-muted)">${e(c.telephone)||''}</span></td>
    <td class="num">${c.nb_machines}</td>
    <td class="num">${c.nb_interventions > 0 ? `<span class="badge badge-teal">${c.nb_interventions}</span>` : '0'}</td>
    <td>${c.actif == 1 ? '<span class="badge badge-green">Actif</span>' : '<span class="badge badge-grey">Inactif</span>'}</td>
    <td>
      <button class="btn btn-outline btn-sm" onclick="edit(${c.id})">Modifier</button>
      <button class="btn btn-outline btn-sm" onclick="toggleActif(${c.id})">${c.actif == 1 ? 'Désactiver' : 'Activer'}</button>
      ${estAdmin ? `<button class="btn btn-danger btn-sm" onclick="del(${c.id})">Suppr.</button>` : ''}
    </td></tr>`).join('');
  document.getElementById('count').textContent = `${rows.length} client(s)`;
}
function fill(c) {
  c = c || {};
  document.getElementById('c-id').value = c.id || '';
  document.getElementById('c-code').value = c.code_client || '';
  document.getElementById('c-nom').value = c.raison_sociale || '';
  document.getElementById('c-contact').value = c.contact_nom || '';
  document.getElementById('c-secteur').value = c.secteur || '';
  document.getElementById('c-tel').value = c.telephone || '';
  document.getElementById('c-email').value = c.email || '';
  document.getElementById('c-ville').value = c.ville || '';
  document.getElementById('c-actif').value = c.actif !== undefined ? c.actif : 1;
  document.getElementById('c-adresse').value = c.adresse || '';
  document.getElementById('c-notes').value = c.notes || '';
}
function openAdd() { document.getElementById('modal-title').textContent = 'Nouveau client'; fill(null); CTP.openModal('modal-client'); }
async function edit(id) {
  const c = await CTP.api('cli_get', { id });
  if (c.error) return CTP.toast(c.error, 'error');
  document.getElementById('modal-title').textContent = 'Modifier le client';
  fill(c); CTP.openModal('modal-client');
}
async function save() {
  const d = {
    id: document.getElementById('c-id').value,
    code_client: document.getElementById('c-code').value.trim(),
    raison_sociale: document.getElementById('c-nom').value.trim(),
    contact_nom: document.getElementById('c-contact').value.trim(),
    secteur: document.getElementById('c-secteur').value.trim(),
    telephone: document.getElementById('c-tel').value.trim(),
    email: document.getElementById('c-email').value.trim(),
    ville: document.getElementById('c-ville').value.trim(),
    actif: document.getElementById('c-actif').value,
    adresse: document.getElementById('c-adresse').value.trim(),
    notes: document.getElementById('c-notes').value.trim(),
  };
  if (!d.raison_sociale) return CTP.toast('Raison sociale requise', 'error');
  const r = await CTP.api('cli_save', d);
  if (r.error) return CTP.toast(r.error, 'error');
  CTP.toast('Client enregistré'); CTP.closeModal('modal-client'); load();
}
async function toggleActif(id) { const r = await CTP.api('cli_toggle_actif', { id }); if (r.error) return CTP.toast(r.error,'error'); load(); }
async function del(id) {
  if (!CTP.confirmDelete('Supprimer définitivement ce client ?')) return;
  const r = await CTP.api('cli_delete', { id });
  if (r.error) return CTP.toast(r.error, 'error');
  CTP.toast('Client supprimé'); load();
}
load();
</script>
<?php require_once '../includes/footer.php'; ?>
