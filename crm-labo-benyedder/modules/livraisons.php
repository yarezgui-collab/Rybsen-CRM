<?php
require_once '../config.php';
requireRole(['admin','labo']);
$pageTitle = 'Livraisons / Dispatch';
$activePage = 'livraisons';
require_once '../includes/header.php';
?>
<div class="section-card">
  <div class="section-header"><div class="section-title">📋 Commandes prêtes à livrer</div></div>
  <div class="alert-box info">Commandes dont l'ordre de fabrication est terminé — prêtes à être dispatchées vers leur canal.</div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>#</th><th>Canal</th><th>Destination</th><th>Montant</th><th>Action</th></tr></thead>
      <tbody id="candidats-body"><tr><td colspan="5" style="text-align:center;padding:20px;color:var(--text-muted)">Chargement...</td></tr></tbody>
    </table>
  </div>
</div>

<div class="section-card">
  <div class="section-header"><div class="section-title">🚚 Livraisons</div></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>#</th><th>Canal</th><th>Destination</th><th>Date</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody id="liv-body"><tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted)">Chargement...</td></tr></tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="modal-detail">
  <div class="modal" style="max-width:700px">
    <div class="modal-header"><div class="modal-title" id="detail-title">Livraison</div><button class="modal-close" onclick="LABO.closeModal('modal-detail')">✕</button></div>
    <div class="modal-body" id="detail-body"></div>
    <div class="modal-footer" id="detail-footer"></div>
  </div>
</div>

<script>
const canalLabels = { terme: 'Client à terme', franchise: 'Franchise', point_vente: 'Point de vente' };
const canalBadge = { terme: 'badge-navy', franchise: 'badge-gold', point_vente: 'badge-teal' };
const statutLabels = { preparee: 'Préparée', en_route: 'En route', livree: 'Livrée' };
const statutBadge = { preparee: 'badge-grey', en_route: 'badge-gold', livree: 'badge-green' };
let allLiv = [];

async function loadCandidats() {
  const rows = await LABO.api('liv_candidats');
  const e = LABO.escape;
  document.getElementById('candidats-body').innerHTML = rows.length ? rows.map(c => `
    <tr>
      <td>#${c.id}</td>
      <td><span class="badge ${canalBadge[c.canal]}">${canalLabels[c.canal]}</span></td>
      <td>${e(c.client_nom || c.point_vente_nom || '—')}</td>
      <td class="num">${LABO.formatCurrency(c.montant_total)}</td>
      <td><button class="btn btn-primary btn-sm" onclick="creerLivraison(${c.id})">Dispatcher</button></td>
    </tr>`).join('') : '<tr><td colspan="5" style="text-align:center;padding:20px;color:var(--text-muted)">Aucune commande prête à livrer pour le moment</td></tr>';
}
async function creerLivraison(commandeId) {
  const r = await LABO.api('liv_creer', { commande_id: commandeId });
  if (r.ok) { LABO.toast('Livraison #' + r.id + ' créée ✓ Stock mis à jour.'); loadCandidats(); loadLiv(); }
  else LABO.toast(r.error || 'Erreur', 'error');
}

async function loadLiv() {
  allLiv = await LABO.api('liv_list');
  const e = LABO.escape;
  document.getElementById('liv-body').innerHTML = allLiv.length ? allLiv.map(l => `
    <tr style="cursor:pointer" onclick="openDetail(${l.id})">
      <td>#${l.id}</td>
      <td><span class="badge ${canalBadge[l.canal]}">${canalLabels[l.canal]}</span></td>
      <td>${e(l.client_nom || l.point_vente_nom || '—')}</td>
      <td>${LABO.formatDate(l.date_livraison)}</td>
      <td><span class="badge ${statutBadge[l.statut]}">${statutLabels[l.statut]}</span></td>
      <td></td>
    </tr>`).join('') : '<tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted)">Aucune livraison</td></tr>';
}

async function openDetail(id) {
  const l = await LABO.api('liv_get', { id });
  if (l.error) { LABO.toast(l.error, 'error'); return; }
  const e = LABO.escape;
  document.getElementById('detail-title').textContent = 'Livraison #' + l.id;
  document.getElementById('detail-body').innerHTML = `
    <div class="form-grid" style="margin-bottom:16px">
      <div class="form-group"><label>Canal</label><div><span class="badge ${canalBadge[l.canal]}">${canalLabels[l.canal]}</span></div></div>
      <div class="form-group"><label>Destination</label><div>${e(l.client_nom || l.point_vente_nom || '—')}</div></div>
      <div class="form-group"><label>Statut</label><div><span class="badge ${statutBadge[l.statut]}">${statutLabels[l.statut]}</span></div></div>
      <div class="form-group"><label>Date</label><div>${LABO.formatDate(l.date_livraison)}</div></div>
    </div>
    <div class="table-wrap">
      <table><thead><tr><th>Produit</th><th>Quantité</th><th>Lot</th></tr></thead>
      <tbody>${l.lignes.map(x => `<tr><td>${e(x.produit_nom)}</td><td class="num">${x.quantite}</td><td>${e(x.numero_lot) || '—'}</td></tr>`).join('')}</tbody></table>
    </div>
  `;
  let footer = '';
  if (l.statut === 'preparee') footer = `<button class="btn btn-outline" onclick="setStatutLiv(${l.id},'en_route')">Marquer en route</button>`;
  if (l.statut === 'en_route') footer = `<button class="btn btn-primary" onclick="setStatutLiv(${l.id},'livree')">Marquer livrée</button>`;
  document.getElementById('detail-footer').innerHTML = footer;
  LABO.openModal('modal-detail');
}
async function setStatutLiv(id, statut) {
  const r = await LABO.api('liv_set_statut', { id, statut });
  if (r.ok) { LABO.toast('Statut mis à jour ✓'); loadLiv(); openDetail(id); }
}

loadCandidats();
loadLiv();
</script>
<?php require_once '../includes/footer.php'; ?>
