<?php
require_once '../config.php';
requireRole(['admin','labo']);
$pageTitle = 'Famille par production';
$activePage = 'catalogue_comptes';
require_once '../includes/header.php';
?>
<div class="section-card">
  <div class="section-header">
    <div class="section-title">🗂️ Famille par production</div>
    <div class="section-actions"><button class="btn btn-primary" onclick="openAddCat()">+ Famille</button></div>
  </div>
  <div class="alert-box info">Chaque famille (catégorie) de produits est fabriquée dans une cuisine de production. Choisissez ici quelle cuisine fabrique quelle famille — c'est ce lien qui répartit automatiquement les ordres de fabrication.</div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Famille</th><th>Cuisine de production</th><th>Produits</th><th>Actions</th></tr></thead>
      <tbody id="cat-body"><tr><td colspan="4" style="text-align:center;padding:30px;color:var(--text-muted)">Chargement...</td></tr></tbody>
    </table>
  </div>
</div>

<!-- Modal famille -->
<div class="modal-overlay" id="modal-cat">
  <div class="modal">
    <div class="modal-header"><div class="modal-title" id="modal-cat-title">Famille</div><button class="modal-close" onclick="LABO.closeModal('modal-cat')">✕</button></div>
    <div class="modal-body">
      <input type="hidden" id="cat-id">
      <div class="form-group"><label>Nom *</label><input type="text" id="cat-nom"></div>
      <div class="form-group" style="margin-top:12px"><label>Cuisine assignée</label>
        <select id="cat-cuisine"><option value="">— Laboratoire central (non assignée) —</option></select>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="LABO.closeModal('modal-cat')">Annuler</button>
      <button class="btn btn-primary" onclick="saveCat()">Enregistrer</button>
    </div>
  </div>
</div>

<script>
async function loadCuisinesSelect() {
  const cuisines = await LABO.api('cuisine_list');
  const e = LABO.escape;
  document.getElementById('cat-cuisine').innerHTML = '<option value="">— Laboratoire central (non assignée) —</option>' +
    cuisines.map(c => `<option value="${c.id}">${e(c.nom)}</option>`).join('');
}
async function loadCats() {
  const cats = await LABO.api('cat_list');
  const e = LABO.escape;
  document.getElementById('cat-body').innerHTML = cats.length ? cats.map(c => `
    <tr>
      <td><strong>${e(c.nom)}</strong></td>
      <td>${c.cuisine_nom ? '<span class="badge badge-navy">'+e(c.cuisine_nom)+'</span>' : '<span class="badge badge-grey">Laboratoire central</span>'}</td>
      <td class="num">${c.nb_produits}</td>
      <td>
        <button class="btn btn-outline btn-sm" onclick='editCat(${JSON.stringify(c)})'>✏️</button>
        <button class="btn btn-danger btn-sm" onclick="delCat(${c.id})">🗑</button>
      </td>
    </tr>`).join('') : '<tr><td colspan="4" style="text-align:center;padding:30px;color:var(--text-muted)">Aucune famille</td></tr>';
}
function openAddCat() {
  document.getElementById('modal-cat-title').textContent = 'Ajouter une famille';
  document.getElementById('cat-id').value = '';
  document.getElementById('cat-nom').value = '';
  document.getElementById('cat-cuisine').value = '';
  LABO.openModal('modal-cat');
}
function editCat(c) {
  document.getElementById('modal-cat-title').textContent = 'Modifier la famille';
  document.getElementById('cat-id').value = c.id;
  document.getElementById('cat-nom').value = c.nom;
  document.getElementById('cat-cuisine').value = c.cuisine_id || '';
  LABO.openModal('modal-cat');
}
async function saveCat() {
  const nom = document.getElementById('cat-nom').value.trim();
  if (!nom) { LABO.toast('Nom requis', 'error'); return; }
  const r = await LABO.api('cat_save', {
    id: document.getElementById('cat-id').value, nom,
    cuisine_id: document.getElementById('cat-cuisine').value
  });
  if (r.ok) { LABO.closeModal('modal-cat'); LABO.toast('Enregistré ✓'); loadCats(); }
  else LABO.toast(r.error || 'Erreur', 'error');
}
async function delCat(id) {
  if (!LABO.confirmDelete()) return;
  const r = await LABO.api('cat_delete', { id });
  if (r.ok) { LABO.toast('Supprimé'); loadCats(); } else LABO.toast(r.error || 'Erreur', 'error');
}

(async function(){ await loadCuisinesSelect(); await loadCats(); })();
</script>
<?php require_once '../includes/footer.php'; ?>
