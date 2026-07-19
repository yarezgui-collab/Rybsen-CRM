<?php
require_once '../config.php';
requireRole(['admin','labo','production']);
$pageTitle = 'Ordres de fabrication';
$activePage = 'production';
require_once '../includes/header.php';
?>
<div class="section-card">
  <div class="section-header">
    <div class="section-title">⚙️ Ordres de fabrication</div>
    <div class="section-actions"><button class="btn btn-primary" onclick="genererOrdre()">+ Générer depuis les commandes confirmées</button></div>
  </div>
  <div class="alert-box info">Agrège toutes les commandes au statut <strong>Confirmée</strong> en un ordre de fabrication, groupé par produit et quantité totale.</div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>#</th><th>Date</th><th>Site</th><th>Produits</th><th>Commandes incluses</th><th>Statut</th><th>Actions</th></tr></thead>
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
let allOf = [];
const statutLabels = { planifie: 'Planifié', en_cours: 'En cours', termine: 'Terminé' };
const statutBadge = { planifie: 'badge-grey', en_cours: 'badge-gold', termine: 'badge-green' };

async function loadOf() {
  allOf = await LABO.api('of_list');
  renderOf();
}
function renderOf() {
  document.getElementById('of-body').innerHTML = allOf.length ? allOf.map(o => `
    <tr style="cursor:pointer" onclick="openDetail(${o.id})">
      <td>#${o.id}</td>
      <td>${LABO.formatDate(o.date_ordre)}</td>
      <td>${LABO.escape(o.site_production)}</td>
      <td><button class="btn btn-outline btn-sm" onclick="event.stopPropagation(); openDetail(${o.id})">Voir détail</button></td>
      <td>—</td>
      <td><span class="badge ${statutBadge[o.statut]}">${statutLabels[o.statut]}</span></td>
      <td></td>
    </tr>`).join('') : '<tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-muted)">Aucun ordre de fabrication</td></tr>';
}

async function genererOrdre() {
  const r = await LABO.api('of_generate');
  if (r.ok) { LABO.toast('Ordre #' + r.id + ' généré ✓'); loadOf(); }
  else LABO.toast(r.error || 'Erreur', 'error');
}

async function openDetail(id) {
  const o = await LABO.api('of_get', { id });
  if (o.error) { LABO.toast(o.error, 'error'); return; }
  const e = LABO.escape;
  document.getElementById('detail-title').textContent = 'Ordre de fabrication #' + o.id;
  document.getElementById('detail-body').innerHTML = `
    <div class="form-grid" style="margin-bottom:16px">
      <div class="form-group"><label>Statut</label><div><span class="badge ${statutBadge[o.statut]}">${statutLabels[o.statut]}</span></div></div>
      <div class="form-group"><label>Date</label><div>${LABO.formatDate(o.date_ordre)}</div></div>
    </div>
    <div class="table-wrap">
      <table><thead><tr><th>Produit</th><th>Quantité totale</th><th>Unité</th></tr></thead>
      <tbody>${o.lignes.map(l => `<tr><td>${e(l.produit_nom)}</td><td class="num">${l.quantite_totale}</td><td>${e(l.unite)}</td></tr>`).join('')}</tbody></table>
    </div>
    <div style="margin-top:16px"><strong>Commandes incluses :</strong> ${o.commandes.map(c => '#' + c.id).join(', ') || '—'}</div>
    ${o.lots.length ? `<div style="margin-top:16px"><strong>Lots produits :</strong>
      <div class="table-wrap"><table><thead><tr><th>Lot</th><th>Produit</th><th>Quantité</th><th>Fabriqué le</th><th>DLC</th></tr></thead>
      <tbody>${o.lots.map(l => `<tr><td>${e(l.numero_lot)}</td><td>${o.lignes.find(x=>x.produit_id==l.produit_id)?.produit_nom||''}</td><td class="num">${l.quantite_produite}</td><td>${LABO.formatDate(l.date_fabrication)}</td><td>${LABO.formatDate(l.date_peremption)}</td></tr>`).join('')}</tbody></table></div>
    </div>` : ''}
  `;
  let footer = '';
  if (o.statut === 'planifie') footer += `<button class="btn btn-outline" onclick="marquerEnCours(${o.id})">Marquer en cours</button>`;
  if (o.statut !== 'termine') footer += `<button class="btn btn-primary" onclick="cloturer(${o.id})">Clôturer &amp; produire (crée les lots, décrémente les matières)</button>`;
  document.getElementById('detail-footer').innerHTML = footer;
  LABO.openModal('modal-detail');
}

async function marquerEnCours(id) {
  const r = await LABO.api('of_marquer_en_cours', { id });
  if (r.ok) { LABO.toast('Ordre en cours'); loadOf(); openDetail(id); }
}
async function cloturer(id) {
  if (!confirm('Clôturer cet ordre ? Cela crée les lots de production et décrémente automatiquement le stock de matières premières selon les recettes. Action irréversible.')) return;
  const r = await LABO.api('of_terminer', { id, dlc_jours: 3 });
  if (r.ok) { LABO.toast('Ordre clôturé ✓ Stock mis à jour.'); LABO.closeModal('modal-detail'); loadOf(); }
  else LABO.toast(r.error || 'Erreur', 'error');
}

loadOf();
</script>
<?php require_once '../includes/footer.php'; ?>
