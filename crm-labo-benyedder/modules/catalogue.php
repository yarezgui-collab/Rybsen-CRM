<?php
require_once '../config.php';
requireRole(['admin','labo']);
$pageTitle = 'Produits & Recettes';
$activePage = 'catalogue';
require_once '../includes/header.php';
?>
<div class="section-card">
  <div class="tabs">
    <button class="tab-btn active" data-tab="tab-produits">Produits</button>
    <button class="tab-btn" data-tab="tab-matieres">Matières premières</button>
    <button class="tab-btn" data-tab="tab-recettes">Recettes (BOM)</button>
  </div>

  <!-- ── PRODUITS ── -->
  <div class="tab-panel active" id="tab-produits">
    <div class="section-header">
      <div class="section-title">Catalogue produits</div>
      <div class="section-actions"><button class="btn btn-primary" onclick="openAddProd()">+ Produit</button></div>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Nom</th><th>Catégorie</th><th>Prix de vente</th><th>Unité</th><th>Statut</th><th>Actions</th></tr></thead>
        <tbody id="prod-body"><tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted)">Chargement...</td></tr></tbody>
      </table>
    </div>
  </div>

  <!-- ── MATIÈRES PREMIÈRES ── -->
  <div class="tab-panel" id="tab-matieres">
    <div class="section-header">
      <div class="section-title">Matières premières</div>
      <div class="section-actions"><button class="btn btn-primary" onclick="openAddMp()">+ Matière</button></div>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Nom</th><th>Stock actuel</th><th>Seuil alerte</th><th>Prix unitaire</th><th>Fournisseur</th><th>Actions</th></tr></thead>
        <tbody id="mp-body"><tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted)">Chargement...</td></tr></tbody>
      </table>
    </div>
  </div>

  <!-- ── RECETTES ── -->
  <div class="tab-panel" id="tab-recettes">
    <div class="section-header">
      <div class="section-title">Nomenclature (recette) par produit</div>
    </div>
    <div class="filters-bar">
      <select id="recette-produit" onchange="loadRecette()" style="min-width:260px">
        <option value="">— Choisir un produit —</option>
      </select>
    </div>
    <div id="recette-content" style="padding:20px;display:none">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Matière première</th><th>Quantité nécessaire</th><th>Unité</th><th>Actions</th></tr></thead>
          <tbody id="recette-body"></tbody>
        </table>
      </div>
      <div class="form-grid" style="margin-top:16px;max-width:520px">
        <div class="form-group"><label>Matière première</label>
          <select id="recette-matiere"></select>
        </div>
        <div class="form-group"><label>Quantité nécessaire</label>
          <input type="number" step="0.0001" id="recette-qte" placeholder="0.0500">
        </div>
        <div class="form-group full">
          <button class="btn btn-primary" onclick="saveRecette()">Ajouter / mettre à jour la ligne</button>
        </div>
      </div>
      <div class="alert-box info" id="recette-cout" style="margin-top:16px"></div>
    </div>
  </div>
</div>

<!-- Modal Produit -->
<div class="modal-overlay" id="modal-prod">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="modal-prod-title">Ajouter un produit</div>
      <button class="modal-close" onclick="LABO.closeModal('modal-prod')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="prod-id">
      <div class="form-grid">
        <div class="form-group full"><label>Nom *</label><input type="text" id="prod-nom"></div>
        <div class="form-group"><label>Catégorie</label>
          <select id="prod-categorie">
            <option>Viennoiserie</option>
            <option>Pâtisserie traditionnelle</option>
            <option>Salé</option>
            <option>Chocolat</option>
            <option>Traiteur / Événementiel</option>
            <option>Autre</option>
          </select>
        </div>
        <div class="form-group"><label>Unité</label><input type="text" id="prod-unite" value="pièce"></div>
        <div class="form-group"><label>Prix de vente (DT)</label><input type="number" step="0.001" id="prod-prix"></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="LABO.closeModal('modal-prod')">Annuler</button>
      <button class="btn btn-primary" onclick="saveProd()">Enregistrer</button>
    </div>
  </div>
</div>

<!-- Modal Matière première -->
<div class="modal-overlay" id="modal-mp">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="modal-mp-title">Ajouter une matière première</div>
      <button class="modal-close" onclick="LABO.closeModal('modal-mp')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="mp-id">
      <div class="form-grid">
        <div class="form-group full"><label>Nom *</label><input type="text" id="mp-nom"></div>
        <div class="form-group"><label>Unité</label><input type="text" id="mp-unite" value="kg"></div>
        <div class="form-group"><label>Stock actuel</label><input type="number" step="0.001" id="mp-stock"></div>
        <div class="form-group"><label>Seuil d'alerte</label><input type="number" step="0.001" id="mp-seuil"></div>
        <div class="form-group"><label>Prix unitaire (DT)</label><input type="number" step="0.001" id="mp-prix"></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="LABO.closeModal('modal-mp')">Annuler</button>
      <button class="btn btn-primary" onclick="saveMp()">Enregistrer</button>
    </div>
  </div>
</div>

<script>
let allProd = [], allMp = [];

async function loadProd() {
  allProd = await LABO.api('prod_list');
  renderProd();
  fillRecetteProduitSelect();
}
function renderProd() {
  const e = LABO.escape;
  document.getElementById('prod-body').innerHTML = allProd.length ? allProd.map(p => `
    <tr>
      <td><strong>${e(p.nom)}</strong></td>
      <td><span class="badge badge-grey">${e(p.categorie)}</span></td>
      <td class="num">${LABO.formatCurrency(p.prix_vente)}</td>
      <td>${e(p.unite)}</td>
      <td><span class="badge ${p.actif == 1 ? 'badge-green' : 'badge-grey'}">${p.actif == 1 ? 'Actif' : 'Inactif'}</span></td>
      <td>
        <button onclick="editProdById(${p.id})" class="btn btn-outline btn-sm">✏️</button>
        <button onclick="delProd(${p.id})" class="btn btn-danger btn-sm">🗑</button>
      </td>
    </tr>`).join('') : '<tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted)">Aucun produit</td></tr>';
}
function editProdById(id) { const p = allProd.find(x => x.id === id); if (p) editProd(p); }
function openAddProd() {
  document.getElementById('modal-prod-title').textContent = 'Ajouter un produit';
  document.getElementById('prod-id').value = '';
  document.getElementById('prod-nom').value = '';
  document.getElementById('prod-categorie').value = 'Viennoiserie';
  document.getElementById('prod-unite').value = 'pièce';
  document.getElementById('prod-prix').value = '';
  LABO.openModal('modal-prod');
}
function editProd(p) {
  document.getElementById('modal-prod-title').textContent = 'Modifier produit';
  document.getElementById('prod-id').value = p.id;
  document.getElementById('prod-nom').value = p.nom;
  document.getElementById('prod-categorie').value = p.categorie;
  document.getElementById('prod-unite').value = p.unite;
  document.getElementById('prod-prix').value = p.prix_vente;
  LABO.openModal('modal-prod');
}
async function saveProd() {
  const nom = document.getElementById('prod-nom').value.trim();
  if (!nom) { LABO.toast('Nom requis', 'error'); return; }
  const r = await LABO.api('prod_save', {
    id: document.getElementById('prod-id').value,
    nom,
    categorie: document.getElementById('prod-categorie').value,
    unite: document.getElementById('prod-unite').value,
    prix_vente: document.getElementById('prod-prix').value || 0,
    actif: 1
  });
  if (r.ok) { LABO.closeModal('modal-prod'); LABO.toast('Enregistré ✓'); loadProd(); }
  else LABO.toast(r.error || 'Erreur', 'error');
}
async function delProd(id) {
  if (!LABO.confirmDelete()) return;
  const r = await LABO.api('prod_delete', { id });
  if (r.ok) { LABO.toast('Supprimé'); loadProd(); } else LABO.toast(r.error || 'Erreur', 'error');
}

async function loadMp() {
  allMp = await LABO.api('mp_list');
  renderMp();
  fillRecetteMatiereSelect();
}
function renderMp() {
  const e = LABO.escape;
  document.getElementById('mp-body').innerHTML = allMp.length ? allMp.map(m => `
    <tr>
      <td><strong>${e(m.nom)}</strong></td>
      <td class="num">${parseFloat(m.stock_actuel).toFixed(3)} ${e(m.unite)}</td>
      <td class="num" style="${parseFloat(m.stock_actuel) <= parseFloat(m.seuil_alerte) ? 'color:var(--red);font-weight:700' : ''}">${parseFloat(m.seuil_alerte).toFixed(3)}</td>
      <td class="num">${LABO.formatCurrency(m.prix_unitaire)}</td>
      <td>${e(m.fournisseur_nom) || '—'}</td>
      <td>
        <button onclick="editMpById(${m.id})" class="btn btn-outline btn-sm">✏️</button>
        <button onclick="delMp(${m.id})" class="btn btn-danger btn-sm">🗑</button>
      </td>
    </tr>`).join('') : '<tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted)">Aucune matière première</td></tr>';
}
function editMpById(id) { const m = allMp.find(x => x.id === id); if (m) editMp(m); }
function openAddMp() {
  document.getElementById('modal-mp-title').textContent = 'Ajouter une matière première';
  document.getElementById('mp-id').value = '';
  document.getElementById('mp-nom').value = '';
  document.getElementById('mp-unite').value = 'kg';
  document.getElementById('mp-stock').value = '';
  document.getElementById('mp-seuil').value = '';
  document.getElementById('mp-prix').value = '';
  LABO.openModal('modal-mp');
}
function editMp(m) {
  document.getElementById('modal-mp-title').textContent = 'Modifier matière première';
  document.getElementById('mp-id').value = m.id;
  document.getElementById('mp-nom').value = m.nom;
  document.getElementById('mp-unite').value = m.unite;
  document.getElementById('mp-stock').value = m.stock_actuel;
  document.getElementById('mp-seuil').value = m.seuil_alerte;
  document.getElementById('mp-prix').value = m.prix_unitaire;
  LABO.openModal('modal-mp');
}
async function saveMp() {
  const nom = document.getElementById('mp-nom').value.trim();
  if (!nom) { LABO.toast('Nom requis', 'error'); return; }
  const r = await LABO.api('mp_save', {
    id: document.getElementById('mp-id').value,
    nom,
    unite: document.getElementById('mp-unite').value,
    stock_actuel: document.getElementById('mp-stock').value || 0,
    seuil_alerte: document.getElementById('mp-seuil').value || 0,
    prix_unitaire: document.getElementById('mp-prix').value || 0,
    actif: 1
  });
  if (r.ok) { LABO.closeModal('modal-mp'); LABO.toast('Enregistré ✓'); loadMp(); }
  else LABO.toast(r.error || 'Erreur', 'error');
}
async function delMp(id) {
  if (!LABO.confirmDelete()) return;
  const r = await LABO.api('mp_delete', { id });
  if (r.ok) { LABO.toast('Supprimé'); loadMp(); } else LABO.toast(r.error || 'Erreur', 'error');
}

// ── RECETTES ──
function fillRecetteProduitSelect() {
  const sel = document.getElementById('recette-produit');
  sel.innerHTML = '<option value="">— Choisir un produit —</option>' +
    allProd.map(p => `<option value="${p.id}">${LABO.escape(p.nom)}</option>`).join('');
}
function fillRecetteMatiereSelect() {
  document.getElementById('recette-matiere').innerHTML = allMp.map(m => `<option value="${m.id}">${LABO.escape(m.nom)} (${LABO.escape(m.unite)})</option>`).join('');
}

let currentRecette = [];
async function loadRecette() {
  const produitId = document.getElementById('recette-produit').value;
  const content = document.getElementById('recette-content');
  if (!produitId) { content.style.display = 'none'; return; }
  content.style.display = 'block';
  currentRecette = await LABO.api('recette_list', { produit_id: produitId });
  renderRecette();
}
function renderRecette() {
  const e = LABO.escape;
  document.getElementById('recette-body').innerHTML = currentRecette.length ? currentRecette.map(r => `
    <tr>
      <td>${e(r.matiere_nom)}</td>
      <td class="num">${parseFloat(r.quantite_necessaire)}</td>
      <td>${e(r.matiere_unite)}</td>
      <td><button onclick="delRecette(${r.id})" class="btn btn-danger btn-sm">🗑</button></td>
    </tr>`).join('') : '<tr><td colspan="4" style="text-align:center;padding:16px;color:var(--text-muted)">Aucune matière associée</td></tr>';

  const produitId = parseInt(document.getElementById('recette-produit').value, 10);
  const produit = allProd.find(p => p.id === produitId);
  const cout = currentRecette.reduce((sum, r) => {
    const m = allMp.find(x => x.id === r.matiere_id);
    return sum + (m ? parseFloat(r.quantite_necessaire) * parseFloat(m.prix_unitaire) : 0);
  }, 0);
  const marge = produit ? (parseFloat(produit.prix_vente) - cout) : null;
  document.getElementById('recette-cout').innerHTML = produit
    ? `Coût matière estimé : <strong>${LABO.formatCurrency(cout)}</strong> — Prix de vente : <strong>${LABO.formatCurrency(produit.prix_vente)}</strong> — Marge estimée : <strong>${LABO.formatCurrency(marge)}</strong>`
    : '';
}
async function saveRecette() {
  const produitId = document.getElementById('recette-produit').value;
  const matiereId = document.getElementById('recette-matiere').value;
  const qte = document.getElementById('recette-qte').value;
  if (!produitId || !matiereId || !qte) { LABO.toast('Tous les champs sont requis', 'error'); return; }
  const r = await LABO.api('recette_save', { produit_id: produitId, matiere_id: matiereId, quantite_necessaire: qte });
  if (r.ok) { LABO.toast('Ligne enregistrée ✓'); document.getElementById('recette-qte').value = ''; loadRecette(); }
  else LABO.toast(r.error || 'Erreur', 'error');
}
async function delRecette(id) {
  if (!LABO.confirmDelete()) return;
  const r = await LABO.api('recette_delete', { id });
  if (r.ok) { LABO.toast('Supprimé'); loadRecette(); } else LABO.toast(r.error || 'Erreur', 'error');
}

loadProd();
loadMp();
</script>
<?php require_once '../includes/footer.php'; ?>
