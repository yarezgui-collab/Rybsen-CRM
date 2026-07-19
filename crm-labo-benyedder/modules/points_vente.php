<?php
require_once '../config.php';
requireRole(['admin','labo']);
$pageTitle = 'Points de vente';
$activePage = 'points_vente';
require_once '../includes/header.php';
?>
<div class="section-card">
  <div class="section-header">
    <div class="section-title">🏬 Points de vente</div>
    <div class="section-actions"><button class="btn btn-primary" onclick="openAddPv()">+ Point de vente</button></div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Nom</th><th>Adresse</th><th>Responsable</th><th>Téléphone</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody id="pv-body"><tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted)">Chargement...</td></tr></tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="modal-pv">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="modal-pv-title">Ajouter un point de vente</div>
      <button class="modal-close" onclick="LABO.closeModal('modal-pv')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="pv-id">
      <div class="form-grid">
        <div class="form-group full"><label>Nom *</label><input type="text" id="pv-nom" placeholder="Ex: Boutique El Menzah"></div>
        <div class="form-group full"><label>Adresse</label><input type="text" id="pv-adresse"></div>
        <div class="form-group"><label>Responsable</label><input type="text" id="pv-resp"></div>
        <div class="form-group"><label>Téléphone</label><input type="tel" id="pv-tel"></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="LABO.closeModal('modal-pv')">Annuler</button>
      <button class="btn btn-primary" onclick="savePv()">Enregistrer</button>
    </div>
  </div>
</div>

<script>
let allPv = [];
async function loadPv() {
  allPv = await LABO.api('pv_list');
  renderPv();
}
function renderPv() {
  const e = LABO.escape;
  document.getElementById('pv-body').innerHTML = allPv.length ? allPv.map(p => `
    <tr>
      <td><strong>${e(p.nom)}</strong></td>
      <td>${e(p.adresse) || '—'}</td>
      <td>${e(p.responsable) || '—'}</td>
      <td>${e(p.telephone) || '—'}</td>
      <td><span class="badge ${p.actif == 1 ? 'badge-green' : 'badge-grey'}">${p.actif == 1 ? 'Actif' : 'Inactif'}</span></td>
      <td>
        <button onclick="editPvById(${p.id})" class="btn btn-outline btn-sm">✏️</button>
        <button onclick="delPv(${p.id})" class="btn btn-danger btn-sm">🗑</button>
      </td>
    </tr>`).join('') : '<tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted)">Aucun point de vente</td></tr>';
}
function editPvById(id) { const p = allPv.find(x => x.id === id); if (p) editPv(p); }
function openAddPv() {
  document.getElementById('modal-pv-title').textContent = 'Ajouter un point de vente';
  document.getElementById('pv-id').value = '';
  ['pv-nom','pv-adresse','pv-resp','pv-tel'].forEach(id => document.getElementById(id).value = '');
  LABO.openModal('modal-pv');
}
function editPv(p) {
  document.getElementById('modal-pv-title').textContent = 'Modifier point de vente';
  document.getElementById('pv-id').value = p.id;
  document.getElementById('pv-nom').value = p.nom;
  document.getElementById('pv-adresse').value = p.adresse || '';
  document.getElementById('pv-resp').value = p.responsable || '';
  document.getElementById('pv-tel').value = p.telephone || '';
  LABO.openModal('modal-pv');
}
async function savePv() {
  const nom = document.getElementById('pv-nom').value.trim();
  if (!nom) { LABO.toast('Nom requis', 'error'); return; }
  const r = await LABO.api('pv_save', {
    id: document.getElementById('pv-id').value,
    nom,
    adresse: document.getElementById('pv-adresse').value,
    responsable: document.getElementById('pv-resp').value,
    telephone: document.getElementById('pv-tel').value,
    actif: 1
  });
  if (r.ok) { LABO.closeModal('modal-pv'); LABO.toast('Enregistré ✓'); loadPv(); }
  else LABO.toast(r.error || 'Erreur', 'error');
}
async function delPv(id) {
  if (!LABO.confirmDelete()) return;
  const r = await LABO.api('pv_delete', { id });
  if (r.ok) { LABO.toast('Supprimé'); loadPv(); } else LABO.toast(r.error || 'Erreur', 'error');
}
loadPv();
</script>
<?php require_once '../includes/footer.php'; ?>
