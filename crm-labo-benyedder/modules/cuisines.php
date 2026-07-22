<?php
require_once '../config.php';
requireRole(['admin','labo']);
$pageTitle = 'Gestion de production';
$activePage = 'cuisines';
require_once '../includes/header.php';
?>
<div class="section-card">
  <div class="tabs">
    <button class="tab-btn active" data-tab="tab-cuisines">Cuisines de production</button>
    <button class="tab-btn" data-tab="tab-categories">Affectation des catégories</button>
  </div>

  <!-- CUISINES -->
  <div class="tab-panel active" id="tab-cuisines">
    <div class="section-header">
      <div class="section-title">Ateliers / cuisines de production</div>
      <div class="section-actions"><button class="btn btn-primary" onclick="openAddCuisine()">+ Cuisine</button></div>
    </div>
    <div class="alert-box info">Chaque cuisine (viennoiserie, libanais, glacier…) reçoit automatiquement les ordres de fabrication des catégories qui lui sont assignées. Créez un compte utilisateur « production » rattaché à une cuisine depuis <strong>Utilisateurs</strong>.</div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Nom</th><th>Description</th><th>Catégories</th><th>Comptes</th><th>Statut</th><th>Actions</th></tr></thead>
        <tbody id="cuisine-body"><tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted)">Chargement...</td></tr></tbody>
      </table>
    </div>
  </div>

  <!-- CATEGORIES -->
  <div class="tab-panel" id="tab-categories">
    <div class="section-header">
      <div class="section-title">Assignation des catégories aux cuisines</div>
      <div class="section-actions"><button class="btn btn-primary" onclick="openAddCat()">+ Catégorie</button></div>
    </div>
    <div class="alert-box info">La cuisine d'une catégorie détermine vers quel atelier partent les produits de cette catégorie lors de la génération des ordres de fabrication.</div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Catégorie</th><th>Cuisine assignée</th><th>Produits</th><th>Actions</th></tr></thead>
        <tbody id="cat-body"><tr><td colspan="4" style="text-align:center;padding:30px;color:var(--text-muted)">Chargement...</td></tr></tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal cuisine -->
<div class="modal-overlay" id="modal-cuisine">
  <div class="modal">
    <div class="modal-header"><div class="modal-title" id="modal-cuisine-title">Cuisine</div><button class="modal-close" onclick="LABO.closeModal('modal-cuisine')">✕</button></div>
    <div class="modal-body">
      <input type="hidden" id="cuisine-id">
      <div class="form-group"><label>Nom *</label><input type="text" id="cuisine-nom" placeholder="Ex: Viennoiserie"></div>
      <div class="form-group" style="margin-top:12px"><label>Description</label><input type="text" id="cuisine-desc"></div>
      <div class="form-group" style="margin-top:12px"><label>Actif</label>
        <select id="cuisine-actif"><option value="1">Oui</option><option value="0">Non</option></select>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="LABO.closeModal('modal-cuisine')">Annuler</button>
      <button class="btn btn-primary" onclick="saveCuisine()">Enregistrer</button>
    </div>
  </div>
</div>

<!-- Modal catégorie -->
<div class="modal-overlay" id="modal-cat">
  <div class="modal">
    <div class="modal-header"><div class="modal-title" id="modal-cat-title">Catégorie</div><button class="modal-close" onclick="LABO.closeModal('modal-cat')">✕</button></div>
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
let allCuisines = [];
async function loadCuisines() {
  allCuisines = await LABO.api('cuisine_list');
  const e = LABO.escape;
  document.getElementById('cuisine-body').innerHTML = allCuisines.length ? allCuisines.map(c => `
    <tr>
      <td><strong>${e(c.nom)}</strong></td>
      <td>${e(c.description) || '—'}</td>
      <td class="num">${c.nb_categories}</td>
      <td class="num">${c.nb_comptes}</td>
      <td><span class="badge ${c.actif==1?'badge-green':'badge-grey'}">${c.actif==1?'Actif':'Inactif'}</span></td>
      <td>
        <button class="btn btn-outline btn-sm" onclick='editCuisine(${JSON.stringify(c)})'>✏️</button>
        <button class="btn btn-danger btn-sm" onclick="delCuisine(${c.id})">🗑</button>
      </td>
    </tr>`).join('') : '<tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted)">Aucune cuisine</td></tr>';
  // remplit le select des catégories
  document.getElementById('cat-cuisine').innerHTML = '<option value="">— Laboratoire central (non assignée) —</option>' +
    allCuisines.map(c => `<option value="${c.id}">${e(c.nom)}</option>`).join('');
}
function openAddCuisine() {
  document.getElementById('modal-cuisine-title').textContent = 'Ajouter une cuisine';
  document.getElementById('cuisine-id').value = '';
  document.getElementById('cuisine-nom').value = '';
  document.getElementById('cuisine-desc').value = '';
  document.getElementById('cuisine-actif').value = '1';
  LABO.openModal('modal-cuisine');
}
function editCuisine(c) {
  document.getElementById('modal-cuisine-title').textContent = 'Modifier la cuisine';
  document.getElementById('cuisine-id').value = c.id;
  document.getElementById('cuisine-nom').value = c.nom;
  document.getElementById('cuisine-desc').value = c.description || '';
  document.getElementById('cuisine-actif').value = c.actif;
  LABO.openModal('modal-cuisine');
}
async function saveCuisine() {
  const nom = document.getElementById('cuisine-nom').value.trim();
  if (!nom) { LABO.toast('Nom requis', 'error'); return; }
  const r = await LABO.api('cuisine_save', {
    id: document.getElementById('cuisine-id').value, nom,
    description: document.getElementById('cuisine-desc').value,
    actif: document.getElementById('cuisine-actif').value
  });
  if (r.ok) { LABO.closeModal('modal-cuisine'); LABO.toast('Enregistré ✓'); loadCuisines(); loadCats(); }
  else LABO.toast(r.error || 'Erreur', 'error');
}
async function delCuisine(id) {
  if (!confirm('Supprimer cette cuisine ? Les catégories et comptes rattachés seront simplement désassignés.')) return;
  const r = await LABO.api('cuisine_delete', { id });
  if (r.ok) { LABO.toast('Supprimé'); loadCuisines(); loadCats(); } else LABO.toast(r.error || 'Erreur', 'error');
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
    </tr>`).join('') : '<tr><td colspan="4" style="text-align:center;padding:30px;color:var(--text-muted)">Aucune catégorie</td></tr>';
}
function openAddCat() {
  document.getElementById('modal-cat-title').textContent = 'Ajouter une catégorie';
  document.getElementById('cat-id').value = '';
  document.getElementById('cat-nom').value = '';
  document.getElementById('cat-cuisine').value = '';
  LABO.openModal('modal-cat');
}
function editCat(c) {
  document.getElementById('modal-cat-title').textContent = 'Modifier la catégorie';
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
  if (r.ok) { LABO.closeModal('modal-cat'); LABO.toast('Enregistré ✓'); loadCats(); loadCuisines(); }
  else LABO.toast(r.error || 'Erreur', 'error');
}
async function delCat(id) {
  if (!confirm('Supprimer cette catégorie ?')) return;
  const r = await LABO.api('cat_delete', { id });
  if (r.ok) { LABO.toast('Supprimé'); loadCats(); } else LABO.toast(r.error || 'Erreur', 'error');
}

(async function(){ await loadCuisines(); await loadCats(); })();
</script>
<?php require_once '../includes/footer.php'; ?>
