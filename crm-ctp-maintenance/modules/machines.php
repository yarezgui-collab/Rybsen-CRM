<?php
require_once '../config.php';
requireRole(['admin','technicien','magasinier']);
$user = currentUser();
$peutEditer = in_array($user['role'], ['admin','technicien'], true);
$estAdmin = $user['role'] === 'admin';
$pageTitle = 'Parc machines CTP';
$activePage = 'machines';
require_once '../includes/header.php';
?>
<div class="section-card">
  <div class="section-header">
    <div class="section-title">🖨️ Parc machines CTP</div>
    <?php if ($peutEditer): ?><div class="section-actions"><button class="btn btn-primary" onclick="openAdd()">+ Nouvelle machine</button></div><?php endif; ?>
  </div>
  <div class="filters-bar">
    <input type="text" id="search" placeholder="🔍 Modèle, n° série, client, localisation…">
    <select id="f-statut" onchange="render()">
      <option value="">Tous les statuts</option>
      <option value="en_service">En service</option>
      <option value="maintenance">En maintenance</option>
      <option value="en_panne">En panne</option>
      <option value="hors_service">Hors service</option>
      <option value="retire">Retiré</option>
    </select>
    <select id="f-techno" onchange="render()">
      <option value="">Toutes technologies</option>
      <option value="thermique">Thermique</option>
      <option value="violet">Violet</option>
      <option value="uv">UV</option>
      <option value="flexo">Flexo</option>
      <option value="autre">Autre</option>
    </select>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Client</th><th>Modèle</th><th>N° série</th><th>Techno</th><th>Compteur</th><th>Garantie</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody id="body"><tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-muted)">Chargement…</td></tr></tbody>
    </table>
  </div>
  <div style="padding:10px 14px;color:var(--text-muted);font-size:13px" id="count"></div>
</div>

<!-- Modal édition -->
<div class="modal-overlay" id="modal-machine">
  <div class="modal" style="max-width:720px">
    <div class="modal-header"><div class="modal-title" id="modal-title">Fiche machine</div><button class="modal-close" onclick="CTP.closeModal('modal-machine')">✕</button></div>
    <div class="modal-body">
      <input type="hidden" id="m-id">
      <div class="form-grid">
        <div class="form-group full"><label>Client *</label><select id="m-client"></select></div>
        <div class="form-group"><label>Modèle *</label><input type="text" id="m-modele" placeholder="Trendsetter, Magnus, Achieve…"></div>
        <div class="form-group"><label>Gamme</label><input type="text" id="m-gamme"></div>
        <div class="form-group"><label>N° série *</label><input type="text" id="m-serie"></div>
        <div class="form-group"><label>Technologie</label><select id="m-techno">
          <option value="thermique">Thermique</option><option value="violet">Violet</option>
          <option value="uv">UV</option><option value="flexo">Flexo</option><option value="autre">Autre</option></select></div>
        <div class="form-group"><label>Format</label><input type="text" id="m-format" placeholder="VLF, 8-up, 4-up…"></div>
        <div class="form-group"><label>Compteur plaques</label><input type="number" id="m-compteur" value="0" min="0"></div>
        <div class="form-group"><label>Date d'installation</label><input type="date" id="m-install"></div>
        <div class="form-group"><label>Fin de garantie</label><input type="date" id="m-garantie"></div>
        <div class="form-group"><label>Localisation</label><input type="text" id="m-localisation" placeholder="Atelier / site"></div>
        <div class="form-group"><label>Statut</label><select id="m-statut">
          <option value="en_service">En service</option><option value="maintenance">En maintenance</option>
          <option value="en_panne">En panne</option><option value="hors_service">Hors service</option>
          <option value="retire">Retiré</option></select></div>
        <div class="form-group full"><label>Notes</label><textarea id="m-notes"></textarea></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="CTP.closeModal('modal-machine')">Annuler</button>
      <button class="btn btn-primary" onclick="save()">Enregistrer</button>
    </div>
  </div>
</div>

<!-- Modal historique -->
<div class="modal-overlay" id="modal-histo">
  <div class="modal" style="max-width:720px">
    <div class="modal-header"><div class="modal-title" id="histo-title">Historique machine</div><button class="modal-close" onclick="CTP.closeModal('modal-histo')">✕</button></div>
    <div class="modal-body">
      <div id="histo-meta" style="margin-bottom:14px"></div>
      <div class="table-wrap"><table>
        <thead><tr><th>N°</th><th>Type</th><th>Statut</th><th>Début</th><th>Objet</th></tr></thead>
        <tbody id="histo-body"></tbody>
      </table></div>
    </div>
    <div class="modal-footer"><button class="btn btn-outline" onclick="CTP.closeModal('modal-histo')">Fermer</button></div>
  </div>
</div>

<script>
const peutEditer = <?= $peutEditer ? 'true':'false' ?>;
const estAdmin = <?= $estAdmin ? 'true':'false' ?>;
let all = [], clients = [];
const technoLabel = { thermique:'Thermique', violet:'Violet', uv:'UV', flexo:'Flexo', autre:'Autre' };
const statutBadge = s => ({en_service:'badge-green', maintenance:'badge-gold', en_panne:'badge-red', hors_service:'badge-grey', retire:'badge-grey'}[s] || 'badge-grey');
const statutLabel = s => ({en_service:'En service', maintenance:'Maintenance', en_panne:'En panne', hors_service:'Hors service', retire:'Retiré'}[s] || s);

async function load() {
  const opt = await CTP.api('mac_options'); clients = opt.clients || [];
  all = await CTP.api('mac_list'); render();
}
function render() {
  const e = CTP.escape;
  const q = document.getElementById('search').value.toLowerCase();
  const fs = document.getElementById('f-statut').value;
  const ft = document.getElementById('f-techno').value;
  const rows = all.filter(m => {
    if (fs && m.statut !== fs) return false;
    if (ft && m.technologie !== ft) return false;
    if (q && !(`${m.modele} ${m.n_serie} ${m.raison_sociale} ${m.localisation||''}`).toLowerCase().includes(q)) return false;
    return true;
  });
  const body = document.getElementById('body');
  if (!rows.length) { body.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-muted)">Aucune machine</td></tr>'; }
  else body.innerHTML = rows.map(m => `<tr>
    <td>${e(m.raison_sociale)}</td>
    <td><strong>${e(m.modele)}</strong>${m.gamme ? '<br><span style="color:var(--text-muted)">'+e(m.gamme)+'</span>':''}</td>
    <td>${e(m.n_serie)}</td>
    <td><span class="badge badge-navy">${technoLabel[m.technologie]||e(m.technologie)}</span></td>
    <td class="num">${CTP.formatNumber(m.compteur_plaques)}</td>
    <td>${CTP.formatDate(m.date_fin_garantie)}</td>
    <td><span class="badge ${statutBadge(m.statut)}">${statutLabel(m.statut)}</span>
        ${m.nb_interventions > 0 ? ` <span class="badge badge-teal">${m.nb_interventions} interv.</span>`:''}</td>
    <td>
      <button class="btn btn-outline btn-sm" onclick="histo(${m.id})">Historique</button>
      ${peutEditer ? `<button class="btn btn-outline btn-sm" onclick="edit(${m.id})">Modifier</button>`:''}
      ${estAdmin ? `<button class="btn btn-danger btn-sm" onclick="del(${m.id})">Suppr.</button>`:''}
    </td></tr>`).join('');
  document.getElementById('count').textContent = `${rows.length} machine(s)`;
}
function clientOptions(sel) {
  return clients.map(c => `<option value="${c.id}" ${c.id==sel?'selected':''}>${CTP.escape(c.raison_sociale)}</option>`).join('');
}
function fill(m) {
  m = m || {};
  document.getElementById('m-id').value = m.id || '';
  document.getElementById('m-client').innerHTML = '<option value="">— Choisir —</option>' + clientOptions(m.client_id);
  document.getElementById('m-modele').value = m.modele || '';
  document.getElementById('m-gamme').value = m.gamme || '';
  document.getElementById('m-serie').value = m.n_serie || '';
  document.getElementById('m-techno').value = m.technologie || 'thermique';
  document.getElementById('m-format').value = m.format || '';
  document.getElementById('m-compteur').value = m.compteur_plaques || 0;
  document.getElementById('m-install').value = m.date_installation || '';
  document.getElementById('m-garantie').value = m.date_fin_garantie || '';
  document.getElementById('m-localisation').value = m.localisation || '';
  document.getElementById('m-statut').value = m.statut || 'en_service';
  document.getElementById('m-notes').value = m.notes || '';
}
function openAdd() { document.getElementById('modal-title').textContent = 'Nouvelle machine'; fill(null); CTP.openModal('modal-machine'); }
async function edit(id) {
  const m = await CTP.api('mac_get', { id });
  if (m.error) return CTP.toast(m.error, 'error');
  document.getElementById('modal-title').textContent = 'Modifier la machine'; fill(m); CTP.openModal('modal-machine');
}
async function save() {
  const d = {
    id: document.getElementById('m-id').value,
    client_id: document.getElementById('m-client').value,
    modele: document.getElementById('m-modele').value.trim(),
    gamme: document.getElementById('m-gamme').value.trim(),
    n_serie: document.getElementById('m-serie').value.trim(),
    technologie: document.getElementById('m-techno').value,
    format: document.getElementById('m-format').value.trim(),
    compteur_plaques: document.getElementById('m-compteur').value,
    date_installation: document.getElementById('m-install').value,
    date_fin_garantie: document.getElementById('m-garantie').value,
    localisation: document.getElementById('m-localisation').value.trim(),
    statut: document.getElementById('m-statut').value,
    notes: document.getElementById('m-notes').value.trim(),
  };
  if (!d.client_id) return CTP.toast('Client requis', 'error');
  if (!d.modele) return CTP.toast('Modèle requis', 'error');
  if (!d.n_serie) return CTP.toast('N° série requis', 'error');
  const r = await CTP.api('mac_save', d);
  if (r.error) return CTP.toast(r.error, 'error');
  CTP.toast('Machine enregistrée'); CTP.closeModal('modal-machine'); load();
}
async function histo(id) {
  const m = await CTP.api('mac_get', { id });
  if (m.error) return CTP.toast(m.error, 'error');
  const e = CTP.escape;
  document.getElementById('histo-title').textContent = `${m.modele} — ${m.n_serie}`;
  document.getElementById('histo-meta').innerHTML =
    `<span class="badge badge-navy">${e(m.raison_sociale)}</span>
     <span class="badge ${statutBadge(m.statut)}">${statutLabel(m.statut)}</span>
     <span class="badge badge-grey">Compteur : ${CTP.formatNumber(m.compteur_plaques)} plaques</span>`;
  const h = m.historique || [];
  document.getElementById('histo-body').innerHTML = h.length ? h.map(i => `<tr>
    <td>${e(i.numero)}</td><td>${e(i.type)}</td><td>${e((i.statut||'').replace(/_/g,' '))}</td>
    <td>${CTP.formatDate(i.date_debut)}</td><td>${e(i.description)||'—'}</td></tr>`).join('')
    : '<tr><td colspan="5" style="text-align:center;padding:20px;color:var(--text-muted)">Aucune intervention</td></tr>';
  CTP.openModal('modal-histo');
}
async function del(id) {
  if (!CTP.confirmDelete('Supprimer cette machine ?')) return;
  const r = await CTP.api('mac_delete', { id });
  if (r.error) return CTP.toast(r.error, 'error');
  CTP.toast('Machine supprimée'); load();
}
load();
</script>
<?php require_once '../includes/footer.php'; ?>
