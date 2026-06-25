<?php
require_once '../config.php';
$pageTitle = 'Fabrication AquaClean';
$activePage = 'fabrication';
require_once '../includes/header.php';
?>
<div class="section-card">
  <div class="section-header">
    <div class="section-title">⚙️ Suivi Fabrication — Unités AquaClean</div>
    <div class="section-actions"><button class="btn btn-primary" onclick="openAdd()">+ Nouvelle unité</button></div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Machine ID</th><th>Client</th><th>Version</th><th>Pays</th><th>Statut fabrication</th><th>Composants reçus</th><th>Date installation</th><th>Actions</th></tr></thead>
      <tbody id="fab-body"><tr><td colspan="8" style="text-align:center;padding:30px;color:#999">Chargement...</td></tr></tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="modal-fab">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="modal-fab-title">Nouvelle unité AquaClean</div>
      <button class="modal-close" onclick="RYBSEN.closeModal('modal-fab')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="fab-id">
      <div class="form-grid">
        <div class="form-group"><label>Machine ID *</label><input type="text" id="fab-mid" placeholder="AQC-005"></div>
        <div class="form-group"><label>Version</label><select id="fab-version"><option>V1</option><option>V2</option></select></div>
        <div class="form-group"><label>Pays client</label><input type="text" id="fab-pays"></div>
        <div class="form-group"><label>Statut</label>
          <select id="fab-statut"><option>Conception</option><option>Approvisionnement</option><option>Composants commandés</option><option>Assemblage Nielsen</option><option>Câblage</option><option>QA / Tests</option><option>Prêt expédition</option><option>Expédié</option><option>Installé</option><option>SAV actif</option></select>
        </div>
        <div class="form-group"><label>Date lancement</label><input type="date" id="fab-dlancement"></div>
        <div class="form-group"><label>Date installation</label><input type="date" id="fab-dinstall"></div>
        <div class="form-group"><label>N° de série</label><input type="text" id="fab-serie"></div>
        <div class="form-group"></div>
        <div class="form-group" style="flex-direction:row;align-items:center;gap:8px"><input type="checkbox" id="fab-pompes"><label style="font-size:13px;text-transform:none">Pompes reçues</label></div>
        <div class="form-group" style="flex-direction:row;align-items:center;gap:8px"><input type="checkbox" id="fab-hydraulique"><label style="font-size:13px;text-transform:none">Hydraulique reçu</label></div>
        <div class="form-group" style="flex-direction:row;align-items:center;gap:8px"><input type="checkbox" id="fab-filtres"><label style="font-size:13px;text-transform:none">Filtres reçus</label></div>
        <div class="form-group" style="flex-direction:row;align-items:center;gap:8px"><input type="checkbox" id="fab-nielsen"><label style="font-size:13px;text-transform:none">Assemblage Nielsen OK</label></div>
        <div class="form-group full"><label>Blocages</label><textarea id="fab-blocages" placeholder="Composants manquants, retards..."></textarea></div>
        <div class="form-group full"><label>Notes</label><textarea id="fab-notes"></textarea></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="RYBSEN.closeModal('modal-fab')">Annuler</button>
      <button class="btn btn-primary" onclick="saveFab()">Enregistrer</button>
    </div>
  </div>
</div>

<script>
let allFab = [];
const statColors = {
  'Conception': 'badge-grey', 'Approvisionnement': 'badge-grey', 'Composants commandés': 'badge-navy',
  'Assemblage Nielsen': 'badge-gold', 'Câblage': 'badge-gold', 'QA / Tests': 'badge-teal',
  'Prêt expédition': 'badge-teal', 'Expédié': 'badge-navy', 'Installé': 'badge-green', 'SAV actif': 'badge-green'
};

async function loadFab() {
  allFab = await RYBSEN.api('fab_list');
  renderFab();
}

function renderFab() {
  const e = RYBSEN.escape.bind(RYBSEN);
  const body = document.getElementById('fab-body');
  if (!allFab.length) {
    body.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:30px">Aucune unité</td></tr>';
    return;
  }
  body.innerHTML = allFab.map(f => {
    const comp = [f.pompes_recues, f.hydraulique_recu, f.filtres_recus, f.assemblage_nielsen_ok]
      .filter(x => parseInt(x) === 1).length;
    const b = v => parseInt(v) === 1;
    return `
      <tr>
        <td>
          <strong style="font-family:monospace;color:#1A3A52">${e(f.machine_id)}</strong>
          ${f.numero_serie ? `<br><small style="color:#999">S/N: ${e(f.numero_serie)}</small>` : ''}
        </td>
        <td>${e(f.client_nom) || '—'}</td>
        <td><span class="badge ${f.version === 'V2' ? 'badge-gold' : 'badge-teal'}">${e(f.version)}</span></td>
        <td>${e(f.pays) || '—'}</td>
        <td>
          <span class="badge ${statColors[f.statut] || 'badge-grey'}">${e(f.statut)}</span>
          ${f.blocages ? `<br><small style="color:#dc2626">⚠️ ${e(f.blocages.substring(0, 40))}</small>` : ''}
        </td>
        <td>
          <div style="display:flex;gap:4px;flex-wrap:wrap">
            <span class="badge ${b(f.pompes_recues) ? 'badge-green' : 'badge-grey'}" style="font-size:10px">Pompes</span>
            <span class="badge ${b(f.hydraulique_recu) ? 'badge-green' : 'badge-grey'}" style="font-size:10px">Hydraulique</span>
            <span class="badge ${b(f.filtres_recus) ? 'badge-green' : 'badge-grey'}" style="font-size:10px">Filtres</span>
            <span class="badge ${b(f.assemblage_nielsen_ok) ? 'badge-green' : 'badge-grey'}" style="font-size:10px">Nielsen</span>
          </div>
          <div style="margin-top:4px"><div class="progress-bar"><div class="progress-fill" style="width:${comp * 25}%"></div></div></div>
        </td>
        <td>${f.date_installation ? new Date(f.date_installation).toLocaleDateString('fr-FR') : '—'}</td>
        <td><button onclick="editFabById(${f.id})" class="btn btn-outline btn-sm">✏️</button></td>
      </tr>`;
  }).join('');
}

function editFabById(id) {
  const f = allFab.find(x => x.id === id);
  if (f) editFab(f);
}

function openAdd() {
  document.getElementById('modal-fab-title').textContent = 'Nouvelle unité';
  document.getElementById('fab-id').value = '';
  ['fab-mid','fab-pays','fab-dlancement','fab-dinstall','fab-serie','fab-blocages','fab-notes']
    .forEach(id => document.getElementById(id).value = '');
  ['fab-pompes','fab-hydraulique','fab-filtres','fab-nielsen']
    .forEach(id => document.getElementById(id).checked = false);
  RYBSEN.openModal('modal-fab');
}

function editFab(f) {
  document.getElementById('modal-fab-title').textContent = 'Modifier unité';
  document.getElementById('fab-id').value = f.id;
  document.getElementById('fab-mid').value = f.machine_id;
  document.getElementById('fab-version').value = f.version || 'V1';
  document.getElementById('fab-pays').value = f.pays || '';
  document.getElementById('fab-statut').value = f.statut;
  document.getElementById('fab-dlancement').value = f.date_lancement || '';
  document.getElementById('fab-dinstall').value = f.date_installation || '';
  document.getElementById('fab-serie').value = f.numero_serie || '';
  document.getElementById('fab-pompes').checked = parseInt(f.pompes_recues) === 1;
  document.getElementById('fab-hydraulique').checked = parseInt(f.hydraulique_recu) === 1;
  document.getElementById('fab-filtres').checked = parseInt(f.filtres_recus) === 1;
  document.getElementById('fab-nielsen').checked = parseInt(f.assemblage_nielsen_ok) === 1;
  document.getElementById('fab-blocages').value = f.blocages || '';
  document.getElementById('fab-notes').value = f.notes || '';
  RYBSEN.openModal('modal-fab');
}

async function saveFab() {
  const mid = document.getElementById('fab-mid').value.trim();
  if (!mid) { RYBSEN.toast('Machine ID requis', 'error'); return; }
  const r = await RYBSEN.api('fab_save', {
    id: document.getElementById('fab-id').value,
    machine_id: mid,
    version: document.getElementById('fab-version').value,
    pays: document.getElementById('fab-pays').value,
    statut: document.getElementById('fab-statut').value,
    pompes_recues: document.getElementById('fab-pompes').checked ? 1 : 0,
    hydraulique_recu: document.getElementById('fab-hydraulique').checked ? 1 : 0,
    filtres_recus: document.getElementById('fab-filtres').checked ? 1 : 0,
    assemblage_nielsen_ok: document.getElementById('fab-nielsen').checked ? 1 : 0,
    date_lancement: document.getElementById('fab-dlancement').value || null,
    date_installation: document.getElementById('fab-dinstall').value || null,
    numero_serie: document.getElementById('fab-serie').value,
    blocages: document.getElementById('fab-blocages').value,
    notes: document.getElementById('fab-notes').value
  });
  if (r.ok) { RYBSEN.closeModal('modal-fab'); RYBSEN.toast('Enregistré ✓'); loadFab(); }
  else RYBSEN.toast(r.error || 'Erreur', 'error');
}

loadFab();
</script>
<?php require_once '../includes/footer.php'; ?>
