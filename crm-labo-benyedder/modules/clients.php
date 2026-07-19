<?php
require_once '../config.php';
requireRole(['admin','labo']);
$pageTitle = 'Clients à terme';
$activePage = 'clients';
require_once '../includes/header.php';
?>
<div class="section-card">
  <div class="section-header">
    <div class="section-title">🏪 Clients à terme</div>
    <div class="section-actions"><button class="btn btn-primary" onclick="openAdd()">+ Client</button></div>
  </div>
  <div class="filters-bar">
    <input type="text" id="search-cli" placeholder="🔍 Rechercher...">
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Nom</th><th>Contact</th><th>Téléphone</th><th>Email</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody id="cli-body"><tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted)">Chargement...</td></tr></tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="modal-cli">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="modal-cli-title">Ajouter un client</div>
      <button class="modal-close" onclick="LABO.closeModal('modal-cli')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="cli-id">
      <div class="form-grid">
        <div class="form-group full"><label>Nom *</label><input type="text" id="cli-nom" placeholder="Nom du client"></div>
        <div class="form-group"><label>Contact</label><input type="text" id="cli-contact"></div>
        <div class="form-group"><label>Téléphone</label><input type="tel" id="cli-tel"></div>
        <div class="form-group"><label>Email</label><input type="email" id="cli-email"></div>
        <div class="form-group full"><label>Adresse</label><input type="text" id="cli-adresse"></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="LABO.closeModal('modal-cli')">Annuler</button>
      <button class="btn btn-primary" onclick="saveCli()">Enregistrer</button>
    </div>
  </div>
</div>

<script>
let allCli = [];

async function loadCli() {
  allCli = await LABO.api('cli_list');
  applyFiltersCli();
}

function applyFiltersCli() {
  const q = document.getElementById('search-cli').value.toLowerCase();
  renderCli(allCli.filter(c => !q || (c.nom + (c.contact_nom||'') + (c.email||'')).toLowerCase().includes(q)));
}

function renderCli(data) {
  const e = LABO.escape;
  const body = document.getElementById('cli-body');
  if (!data.length) {
    body.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted)">Aucun client</td></tr>';
    return;
  }
  body.innerHTML = data.map(c => `
    <tr>
      <td><strong>${e(c.nom)}</strong></td>
      <td>${e(c.contact_nom) || '—'}</td>
      <td>${e(c.telephone) || '—'}</td>
      <td>${c.email ? `<a href="mailto:${e(c.email)}" style="color:var(--teal)">${e(c.email)}</a>` : '—'}</td>
      <td><span class="badge ${c.actif == 1 ? 'badge-green' : 'badge-grey'}">${c.actif == 1 ? 'Actif' : 'Inactif'}</span></td>
      <td>
        <button onclick="editCliById(${c.id})" class="btn btn-outline btn-sm">✏️</button>
        <button onclick="delCli(${c.id})" class="btn btn-danger btn-sm">🗑</button>
      </td>
    </tr>`).join('');
}

function editCliById(id) {
  const c = allCli.find(x => x.id === id);
  if (c) editCli(c);
}

function openAdd() {
  document.getElementById('modal-cli-title').textContent = 'Ajouter un client';
  document.getElementById('cli-id').value = '';
  ['cli-nom','cli-contact','cli-tel','cli-email','cli-adresse'].forEach(id => document.getElementById(id).value = '');
  LABO.openModal('modal-cli');
}

function editCli(c) {
  document.getElementById('modal-cli-title').textContent = 'Modifier client';
  document.getElementById('cli-id').value = c.id;
  document.getElementById('cli-nom').value = c.nom;
  document.getElementById('cli-contact').value = c.contact_nom || '';
  document.getElementById('cli-tel').value = c.telephone || '';
  document.getElementById('cli-email').value = c.email || '';
  document.getElementById('cli-adresse').value = c.adresse || '';
  LABO.openModal('modal-cli');
}

async function saveCli() {
  const nom = document.getElementById('cli-nom').value.trim();
  if (!nom) { LABO.toast('Nom requis', 'error'); return; }
  const r = await LABO.api('cli_save', {
    id: document.getElementById('cli-id').value,
    nom,
    contact_nom: document.getElementById('cli-contact').value,
    telephone: document.getElementById('cli-tel').value,
    email: document.getElementById('cli-email').value,
    adresse: document.getElementById('cli-adresse').value,
    actif: 1
  });
  if (r.ok) { LABO.closeModal('modal-cli'); LABO.toast('Enregistré ✓'); loadCli(); }
  else LABO.toast(r.error || 'Erreur', 'error');
}

async function delCli(id) {
  if (!LABO.confirmDelete()) return;
  const r = await LABO.api('cli_delete', { id });
  if (r.ok) { LABO.toast('Supprimé'); loadCli(); }
  else LABO.toast(r.error || 'Erreur', 'error');
}

document.getElementById('search-cli').addEventListener('input', applyFiltersCli);
loadCli();
</script>
<?php require_once '../includes/footer.php'; ?>
