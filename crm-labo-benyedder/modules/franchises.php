<?php
require_once '../config.php';
requireRole(['admin','labo']);
$pageTitle = 'Franchises';
$activePage = 'franchises';
require_once '../includes/header.php';
?>
<div class="section-card">
  <div class="section-header">
    <div class="section-title">🤝 Franchises</div>
    <div class="section-actions"><button class="btn btn-primary" onclick="openAddFr()">+ Franchise</button></div>
  </div>
  <div class="filters-bar">
    <input type="text" id="search-fr" placeholder="🔍 Rechercher...">
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Nom</th><th>Territoire</th><th>Mode de paiement</th><th>Contact</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody id="fr-body"><tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted)">Chargement...</td></tr></tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="modal-fr">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="modal-fr-title">Ajouter une franchise</div>
      <button class="modal-close" onclick="LABO.closeModal('modal-fr')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="fr-id">
      <div class="form-grid">
        <div class="form-group full"><label>Nom *</label><input type="text" id="fr-nom" placeholder="Nom de la franchise"></div>
        <div class="form-group"><label>Territoire</label><input type="text" id="fr-territoire" placeholder="Ex: Sfax"></div>
        <div class="form-group"><label>Mode de paiement</label>
          <select id="fr-mode">
            <option value="libre_choix">Libre choix (au cas par cas)</option>
            <option value="comptant">Comptant</option>
            <option value="terme">Terme</option>
          </select>
        </div>
        <div class="form-group"><label>Contact</label><input type="text" id="fr-contact"></div>
        <div class="form-group"><label>Téléphone</label><input type="tel" id="fr-tel"></div>
        <div class="form-group"><label>Email</label><input type="email" id="fr-email"></div>
        <div class="form-group full"><label>Adresse</label><input type="text" id="fr-adresse"></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="LABO.closeModal('modal-fr')">Annuler</button>
      <button class="btn btn-primary" onclick="saveFr()">Enregistrer</button>
    </div>
  </div>
</div>

<script>
let allFr = [];
const modeLabels = { comptant: 'Comptant', terme: 'Terme', libre_choix: 'Libre choix' };

async function loadFr() {
  allFr = await LABO.api('fr_list');
  applyFiltersFr();
}
function applyFiltersFr() {
  const q = document.getElementById('search-fr').value.toLowerCase();
  renderFr(allFr.filter(f => !q || (f.nom + (f.territoire||'')).toLowerCase().includes(q)));
}
function renderFr(data) {
  const e = LABO.escape;
  document.getElementById('fr-body').innerHTML = data.length ? data.map(f => `
    <tr>
      <td><strong>${e(f.nom)}</strong></td>
      <td>${e(f.territoire) || '—'}</td>
      <td><span class="badge badge-navy">${modeLabels[f.mode_paiement] || f.mode_paiement}</span></td>
      <td>${e(f.contact_nom) || '—'}${f.telephone ? '<br><small>' + e(f.telephone) + '</small>' : ''}</td>
      <td><span class="badge ${f.actif == 1 ? 'badge-green' : 'badge-grey'}">${f.actif == 1 ? 'Actif' : 'Inactif'}</span></td>
      <td>
        <button onclick="editFrById(${f.id})" class="btn btn-outline btn-sm">✏️</button>
        <button onclick="delFr(${f.id})" class="btn btn-danger btn-sm">🗑</button>
      </td>
    </tr>`).join('') : '<tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted)">Aucune franchise</td></tr>';
}
function editFrById(id) { const f = allFr.find(x => x.id === id); if (f) editFr(f); }
function openAddFr() {
  document.getElementById('modal-fr-title').textContent = 'Ajouter une franchise';
  document.getElementById('fr-id').value = '';
  ['fr-nom','fr-territoire','fr-contact','fr-tel','fr-email','fr-adresse'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('fr-mode').value = 'libre_choix';
  LABO.openModal('modal-fr');
}
function editFr(f) {
  document.getElementById('modal-fr-title').textContent = 'Modifier franchise';
  document.getElementById('fr-id').value = f.id;
  document.getElementById('fr-nom').value = f.nom;
  document.getElementById('fr-territoire').value = f.territoire || '';
  document.getElementById('fr-mode').value = f.mode_paiement;
  document.getElementById('fr-contact').value = f.contact_nom || '';
  document.getElementById('fr-tel').value = f.telephone || '';
  document.getElementById('fr-email').value = f.email || '';
  document.getElementById('fr-adresse').value = f.adresse || '';
  LABO.openModal('modal-fr');
}
async function saveFr() {
  const nom = document.getElementById('fr-nom').value.trim();
  if (!nom) { LABO.toast('Nom requis', 'error'); return; }
  const r = await LABO.api('fr_save', {
    id: document.getElementById('fr-id').value,
    nom,
    territoire: document.getElementById('fr-territoire').value,
    mode_paiement: document.getElementById('fr-mode').value,
    contact_nom: document.getElementById('fr-contact').value,
    telephone: document.getElementById('fr-tel').value,
    email: document.getElementById('fr-email').value,
    adresse: document.getElementById('fr-adresse').value,
    actif: 1
  });
  if (r.ok) { LABO.closeModal('modal-fr'); LABO.toast('Enregistré ✓'); loadFr(); }
  else LABO.toast(r.error || 'Erreur', 'error');
}
async function delFr(id) {
  if (!LABO.confirmDelete()) return;
  const r = await LABO.api('fr_delete', { id });
  if (r.ok) { LABO.toast('Supprimé'); loadFr(); } else LABO.toast(r.error || 'Erreur', 'error');
}
document.getElementById('search-fr').addEventListener('input', applyFiltersFr);
loadFr();
</script>
<?php require_once '../includes/footer.php'; ?>
