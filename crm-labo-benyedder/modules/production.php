<?php
require_once '../config.php';
requireRole(['admin','labo','production']);
$user = currentUser();
$estProduction = $user['role'] === 'production';
$pageTitle = $estProduction ? 'Ma cuisine — production' : 'Ordres de fabrication';
$activePage = 'production';
require_once '../includes/header.php';
?>
<div class="section-card">
  <div class="section-header">
    <div class="section-title">⚙️ <?= $estProduction ? 'Ordres de ma cuisine' : 'Ordres de fabrication' ?></div>
    <?php if (!$estProduction): ?>
    <div class="section-actions"><button class="btn btn-primary" onclick="genererOrdre()">+ Générer depuis les commandes confirmées</button></div>
    <?php endif; ?>
  </div>
  <?php if (!$estProduction): ?>
  <div class="alert-box info">Agrège les commandes <strong>Confirmée</strong> et crée <strong>un ordre par cuisine</strong> (selon la catégorie des produits). Chaque ordre part directement en production.</div>
  <div class="filters-bar">
    <select id="f-cuisine" onchange="applyFilters()"><option value="">Toutes les cuisines</option></select>
    <select id="f-statut" onchange="applyFilters()">
      <option value="">Tous les statuts</option>
      <option value="en_cours">En cours</option>
      <option value="termine">Terminé</option>
    </select>
    <select id="f-categorie" onchange="applyFilters()"><option value="">Toutes les catégories</option></select>
    <select id="f-client" onchange="applyFilters()"><option value="">Tous les clients</option></select>
  </div>
  <?php endif; ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>#</th><th>Date</th><th>Cuisine</th><th>Lignes</th><th>Qté totale</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody id="of-body"><tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-muted)">Chargement...</td></tr></tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="modal-detail">
  <div class="modal" style="max-width:700px">
    <div class="modal-header">
      <div class="modal-title" id="detail-title">Ordre de fabrication</div>
      <button class="modal-close" onclick="LABO.closeModal('modal-detail')">✕</button>
    </div>
    <div class="modal-body" id="detail-body"></div>
    <div class="modal-footer" id="detail-footer"></div>
  </div>
</div>

<script>
const estProduction = <?= $estProduction ? 'true' : 'false' ?>;
let allCuisines = [];
const statutLabels = { planifie: 'Planifié', en_cours: 'En cours', termine: 'Terminé' };
const statutBadge = { planifie: 'badge-grey', en_cours: 'badge-gold', termine: 'badge-green' };

async function loadRefs() {
  if (estProduction) return;
  allCuisines = await LABO.api('cuisine_list');
  const selC = document.getElementById('f-cuisine');
  selC.innerHTML = '<option value="">Toutes les cuisines</option>' + allCuisines.map(c => `<option value="${c.id}">${LABO.escape(c.nom)}</option>`).join('');
  const cats = await LABO.api('cat_list');
  document.getElementById('f-categorie').innerHTML = '<option value="">Toutes les catégories</option>' + cats.map(c => `<option value="${LABO.escape(c.nom)}">${LABO.escape(c.nom)}</option>`).join('');
  const clients = await LABO.api('cli_list');
  const fr = await LABO.api('fr_list');
  const opts = [...clients.map(c => ({id:c.id,nom:c.nom})), ...fr.map(f => ({id:f.id,nom:f.nom+' (franchise)'}))];
  document.getElementById('f-client').innerHTML = '<option value="">Tous les clients</option>' + opts.map(o => `<option value="${o.id}">${LABO.escape(o.nom)}</option>`).join('');
}
function filterParams() {
  if (estProduction) return {};
  return {
    cuisine_id: document.getElementById('f-cuisine').value || null,
    statut: document.getElementById('f-statut').value || null,
    categorie: document.getElementById('f-categorie').value || null,
    client_id: document.getElementById('f-client').value || null
  };
}
async function applyFilters() { renderOf(await LABO.api('of_list', filterParams())); }
async function loadOf() { renderOf(await LABO.api('of_list')); }

function renderOf(rows) {
  if (rows && rows.error) { document.getElementById('of-body').innerHTML = `<tr><td colspan="7" style="text-align:center;padding:30px;color:var(--red)">${LABO.escape(rows.error)}</td></tr>`; return; }
  document.getElementById('of-body').innerHTML = (rows && rows.length) ? rows.map(o => `
    <tr style="cursor:pointer" onclick="openDetail(${o.id})">
      <td>#${o.id}</td>
      <td>${LABO.formatDate(o.date_ordre)}</td>
      <td>${o.cuisine_nom ? '<span class="badge badge-navy">'+LABO.escape(o.cuisine_nom)+'</span>' : '<span class="badge badge-grey">Laboratoire central</span>'}</td>
      <td class="num">${o.nb_lignes}</td>
      <td class="num">${parseFloat(o.qte_totale).toFixed(0)}</td>
      <td><span class="badge ${statutBadge[o.statut]}">${statutLabels[o.statut]}</span></td>
      <td><button class="btn btn-outline btn-sm" onclick="event.stopPropagation(); openDetail(${o.id})">Détail</button></td>
    </tr>`).join('') : '<tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-muted)">Aucun ordre de fabrication</td></tr>';
}

async function genererOrdre() {
  const r = await LABO.api('of_generate');
  if (r.ok) { LABO.toast(r.nb_cuisines + ' ordre(s) généré(s) ✓ (un par cuisine)'); loadOf(); }
  else LABO.toast(r.error || 'Erreur', 'error');
}

async function openDetail(id) {
  const o = await LABO.api('of_get', { id });
  if (o.error) { LABO.toast(o.error, 'error'); return; }
  const e = LABO.escape;
  document.getElementById('detail-title').textContent = 'Ordre de fabrication #' + o.id;
  const cuisineOptions = allCuisines.map(c => `<option value="${c.id}" ${c.id==o.cuisine_id?'selected':''}>${e(c.nom)}</option>`).join('');
  document.getElementById('detail-body').innerHTML = `
    <div class="form-grid" style="margin-bottom:16px">
      <div class="form-group"><label>Statut</label><div><span class="badge ${statutBadge[o.statut]}">${statutLabels[o.statut]}</span></div></div>
      <div class="form-group"><label>Date</label><div>${LABO.formatDate(o.date_ordre)}</div></div>
      <div class="form-group"><label>Cuisine</label><div>${o.cuisine_nom ? '<span class="badge badge-navy">'+e(o.cuisine_nom)+'</span>' : '<span class="badge badge-grey">Laboratoire central</span>'}</div></div>
      ${(!estProduction && o.statut!=='termine' && allCuisines.length) ? `<div class="form-group"><label>Réaffecter (surcharge)</label>
        <select id="reassign-cuisine" onchange="reassignerCuisine(${o.id})"><option value="">— Laboratoire central —</option>${cuisineOptions}</select></div>` : ''}
    </div>
    <div class="table-wrap">
      <table><thead><tr><th>Produit</th><th>Catégorie</th><th>Quantité</th><th>Unité</th></tr></thead>
      <tbody>${o.lignes.map(l => `<tr><td>${e(l.produit_nom)}</td><td>${e(l.categorie||'')}</td><td class="num">${l.quantite_totale}</td><td>${e(l.unite)}</td></tr>`).join('')}</tbody></table>
    </div>
    <div style="margin-top:16px"><strong>Commandes incluses :</strong> ${o.commandes.map(c => '#' + c.id).join(', ') || '—'}</div>
    ${o.lots.length ? `<div style="margin-top:16px"><strong>Lots produits :</strong>
      <div class="table-wrap"><table><thead><tr><th>Lot</th><th>Produit</th><th>Quantité</th><th>Fabriqué le</th><th>DLC</th></tr></thead>
      <tbody>${o.lots.map(l => `<tr><td>${e(l.numero_lot)}</td><td>${o.lignes.find(x=>x.produit_id==l.produit_id)?.produit_nom||''}</td><td class="num">${l.quantite_produite}</td><td>${LABO.formatDate(l.date_fabrication)}</td><td>${LABO.formatDate(l.date_peremption)}</td></tr>`).join('')}</tbody></table></div>
    </div>` : ''}
  `;
  let footer = '';
  if (o.statut !== 'termine') footer += `<button class="btn btn-primary" onclick="cloturer(${o.id})">Clôturer &amp; produire (crée les lots, décrémente les matières)</button>`;
  document.getElementById('detail-footer').innerHTML = footer;
  LABO.openModal('modal-detail');
}

async function reassignerCuisine(id) {
  const cuisineId = document.getElementById('reassign-cuisine').value;
  const r = await LABO.api('of_set_cuisine', { id, cuisine_id: cuisineId });
  if (r.ok) { LABO.toast('Ordre réaffecté ✓'); openDetail(id); loadOf(); }
  else LABO.toast(r.error || 'Erreur', 'error');
}
async function cloturer(id) {
  if (!confirm('Clôturer cet ordre ? Cela crée les lots de production et décrémente automatiquement le stock de matières premières selon les recettes. Action irréversible.')) return;
  const r = await LABO.api('of_terminer', { id, dlc_jours: 3 });
  if (r.ok) { LABO.toast('Ordre clôturé ✓ Stock mis à jour.'); LABO.closeModal('modal-detail'); loadOf(); }
  else LABO.toast(r.error || 'Erreur', 'error');
}

(async function(){ await loadRefs(); await loadOf(); })();
</script>
<?php require_once '../includes/footer.php'; ?>
