<?php
require_once '../config.php';
$pageTitle = 'Investisseurs & Fonds';
$activePage = 'investisseurs';
require_once '../includes/header.php';
?>

<div class="section-card">
  <div class="section-header">
    <div class="section-title">💰 Investisseurs, Fonds & Business Angels</div>
    <div class="section-actions">
      <button class="btn btn-primary" onclick="RYBSEN.openModal('modal-inv')">+ Ajouter</button>
    </div>
  </div>
  <div class="filters-bar">
    <input type="text" id="search-inv" placeholder="🔍 Rechercher un investisseur...">
    <select id="filter-type">
      <option value="">Tous les types</option>
      <option>VC</option><option>Business Angel</option><option>Accélérateur</option>
      <option>Institution</option><option>Fonds Impact</option>
    </select>
    <select id="filter-statut">
      <option value="">Tous les statuts</option>
      <option>Identifié</option><option>Contacté</option><option>Relancé</option>
      <option>Meeting planifié</option><option>Due Diligence</option><option>Décision</option>
      <option>Investi</option><option>Refusé</option>
    </select>
    <select id="filter-chaleur">
      <option value="">Toute chaleur</option>
      <option>🔥 Chaud</option><option>🟡 Tiède</option><option>⚪ Froid</option>
    </select>
  </div>
  <div class="table-wrap">
    <table id="tbl-inv">
      <thead>
        <tr>
          <th>Investisseur</th><th>Organisation</th><th>Type</th><th>Pays</th>
          <th>Ticket (€)</th><th>Chaleur</th><th>Statut</th>
          <th>Prochain contact</th><th>Actions</th>
        </tr>
      </thead>
      <tbody id="inv-body">
        <tr><td colspan="9" style="text-align:center;padding:30px;color:#999">Chargement...</td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- MODAL AJOUT/EDITION -->
<div class="modal-overlay" id="modal-inv">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="modal-inv-title">Ajouter un investisseur</div>
      <button class="modal-close" onclick="RYBSEN.closeModal('modal-inv')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="inv-id">
      <div class="form-grid">
        <div class="form-group"><label>Nom *</label><input type="text" id="inv-nom" placeholder="Anil Maguru"></div>
        <div class="form-group"><label>Organisation</label><input type="text" id="inv-org" placeholder="Satgana"></div>
        <div class="form-group"><label>Type</label>
          <select id="inv-type"><option>VC</option><option>Business Angel</option><option>Accélérateur</option><option>Institution</option><option>Fonds Impact</option><option>Autre</option></select>
        </div>
        <div class="form-group"><label>Pays</label><input type="text" id="inv-pays" placeholder="France"></div>
        <div class="form-group"><label>Email</label><input type="email" id="inv-email" placeholder="contact@fonds.com"></div>
        <div class="form-group"><label>LinkedIn</label><input type="url" id="inv-linkedin" placeholder="https://linkedin.com/in/..."></div>
        <div class="form-group"><label>Ticket min (€)</label><input type="number" id="inv-tmin" placeholder="100000"></div>
        <div class="form-group"><label>Ticket max (€)</label><input type="number" id="inv-tmax" placeholder="300000"></div>
        <div class="form-group"><label>Score chaleur</label>
          <select id="inv-chaleur"><option>🔥 Chaud</option><option>🟡 Tiède</option><option>⚪ Froid</option></select>
        </div>
        <div class="form-group"><label>Statut</label>
          <select id="inv-statut"><option>Identifié</option><option>Contacté</option><option>Relancé</option><option>Meeting planifié</option><option>Due Diligence</option><option>Décision</option><option>Investi</option><option>Refusé</option><option>En pause</option></select>
        </div>
        <div class="form-group"><label>Connexions communes</label><input type="number" id="inv-conn" placeholder="0" min="0"></div>
        <div class="form-group"><label>Source rencontre</label><input type="text" id="inv-source" placeholder="GITEX Africa 2026"></div>
        <div class="form-group"><label>Dernier contact</label><input type="date" id="inv-dlast"></div>
        <div class="form-group"><label>Prochain contact</label><input type="date" id="inv-dnext"></div>
        <div class="form-group full"><label>Notes</label><textarea id="inv-notes" placeholder="Observations, contexte, stratégie..."></textarea></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="RYBSEN.closeModal('modal-inv')">Annuler</button>
      <button class="btn btn-primary" onclick="saveInv()">Enregistrer</button>
    </div>
  </div>
</div>

<script>
let allInv = [];

const statColors = {
  'Identifié':'badge-grey','Contacté':'badge-navy','Relancé':'badge-gold',
  'Meeting planifié':'badge-teal','Due Diligence':'badge-navy','Décision':'badge-gold',
  'Investi':'badge-green','Refusé':'badge-red','En pause':'badge-grey'
};

async function loadInv() {
  allInv = await RYBSEN.api('inv_list');
  renderInv(allInv);
}

function renderInv(data) {
  const body = document.getElementById('inv-body');
  if (!data.length) {
    body.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:30px;color:#999">Aucun investisseur</td></tr>';
    return;
  }
  body.innerHTML = data.map(i => {
    const tMin = i.ticket_min ? new Intl.NumberFormat('fr-FR').format(i.ticket_min) : '';
    const tMax = i.ticket_max ? new Intl.NumberFormat('fr-FR').format(i.ticket_max) : '';
    const ticket = tMin && tMax ? tMin + ' – ' + tMax : (tMin || tMax || '—');
    const today = new Date().toISOString().split('T')[0];
    const isOverdue = i.date_prochain_contact && i.date_prochain_contact <= today && !['Investi','Refusé'].includes(i.statut);
    return `<tr style="${isOverdue?'background:#fff5f5':''}">
      <td><strong>${i.nom}</strong>${i.connexions_communes>0?`<br><small style="color:#4A9B8F">👥 ${i.connexions_communes} connexions communes</small>`:''}</td>
      <td>${i.organisation||'—'}</td>
      <td><span class="badge badge-grey">${i.type}</span></td>
      <td>${i.pays||'—'}</td>
      <td style="white-space:nowrap">${ticket}</td>
      <td><span class="badge ${i.score_chaleur==='🔥 Chaud'?'badge-red':i.score_chaleur==='🟡 Tiède'?'badge-gold':'badge-grey'}">${i.score_chaleur}</span></td>
      <td><span class="badge ${statColors[i.statut]||'badge-grey'}">${i.statut}</span></td>
      <td style="white-space:nowrap">${i.date_prochain_contact ? (isOverdue?'🔴 ':'') + new Date(i.date_prochain_contact).toLocaleDateString('fr-FR') : '—'}</td>
      <td>
        <button onclick='editInvById(${i.id})' class="btn btn-outline btn-sm">✏️</button>
        ${i.email?`<a href="mailto:${i.email}" class="btn btn-teal btn-sm">📧</a>`:''}
        <button onclick="delInv(${i.id})" class="btn btn-danger btn-sm">🗑</button>
      </td>
    </tr>`;
  }).join('');
}

function editInvById(id) {
  const i = allInv.find(x => x.id === id);
  if (i) editInv(i);
}

function editInv(i) {
  document.getElementById('modal-inv-title').textContent = 'Modifier investisseur';
  document.getElementById('inv-id').value = i.id;
  document.getElementById('inv-nom').value = i.nom;
  document.getElementById('inv-org').value = i.organisation||'';
  document.getElementById('inv-type').value = i.type;
  document.getElementById('inv-pays').value = i.pays||'';
  document.getElementById('inv-email').value = i.email||'';
  document.getElementById('inv-linkedin').value = i.linkedin||'';
  document.getElementById('inv-tmin').value = i.ticket_min||'';
  document.getElementById('inv-tmax').value = i.ticket_max||'';
  document.getElementById('inv-chaleur').value = i.score_chaleur;
  document.getElementById('inv-statut').value = i.statut;
  document.getElementById('inv-conn').value = i.connexions_communes||0;
  document.getElementById('inv-source').value = i.source_rencontre||'';
  document.getElementById('inv-dlast').value = i.date_dernier_contact||'';
  document.getElementById('inv-dnext').value = i.date_prochain_contact||'';
  document.getElementById('inv-notes').value = i.notes||'';
  RYBSEN.openModal('modal-inv');
}

async function saveInv() {
  const nom = document.getElementById('inv-nom').value.trim();
  if (!nom) { RYBSEN.toast('Le nom est requis', 'error'); return; }
  const data = {
    id: document.getElementById('inv-id').value,
    nom, organisation: document.getElementById('inv-org').value,
    type: document.getElementById('inv-type').value,
    pays: document.getElementById('inv-pays').value,
    email: document.getElementById('inv-email').value,
    linkedin: document.getElementById('inv-linkedin').value,
    ticket_min: document.getElementById('inv-tmin').value||0,
    ticket_max: document.getElementById('inv-tmax').value||0,
    score_chaleur: document.getElementById('inv-chaleur').value,
    statut: document.getElementById('inv-statut').value,
    connexions_communes: document.getElementById('inv-conn').value||0,
    source_rencontre: document.getElementById('inv-source').value,
    date_dernier_contact: document.getElementById('inv-dlast').value||null,
    date_prochain_contact: document.getElementById('inv-dnext').value||null,
    notes: document.getElementById('inv-notes').value
  };
  const r = await RYBSEN.api('inv_save', data);
  if (r.ok) { RYBSEN.closeModal('modal-inv'); RYBSEN.toast('Investisseur enregistré ✓'); loadInv(); resetModal(); }
  else RYBSEN.toast(r.error||'Erreur', 'error');
}

async function delInv(id) {
  if (!RYBSEN.confirmDelete('Supprimer cet investisseur ?')) return;
  const r = await RYBSEN.api('inv_delete', { id });
  if (r.ok) { RYBSEN.toast('Supprimé'); loadInv(); }
}

function resetModal() {
  document.getElementById('modal-inv-title').textContent = 'Ajouter un investisseur';
  document.getElementById('inv-id').value = '';
  ['inv-nom','inv-org','inv-pays','inv-email','inv-linkedin','inv-tmin','inv-tmax','inv-conn','inv-source','inv-dlast','inv-dnext','inv-notes'].forEach(id => document.getElementById(id).value = '');
}

// Filters
function applyFilters() {
  const q = document.getElementById('search-inv').value.toLowerCase();
  const type = document.getElementById('filter-type').value;
  const statut = document.getElementById('filter-statut').value;
  const chaleur = document.getElementById('filter-chaleur').value;
  renderInv(allInv.filter(i =>
    (!q || (i.nom+i.organisation+i.email+i.notes).toLowerCase().includes(q)) &&
    (!type || i.type === type) &&
    (!statut || i.statut === statut) &&
    (!chaleur || i.score_chaleur === chaleur)
  ));
}
['search-inv','filter-type','filter-statut','filter-chaleur'].forEach(id => document.getElementById(id).addEventListener('input', applyFilters));

loadInv();
</script>

<?php require_once '../includes/footer.php'; ?>
