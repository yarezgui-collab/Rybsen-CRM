<?php
require_once '../config.php';
requireRole(['point_vente']);
$pageTitle = 'Vente passager';
$activePage = 'caisse';
require_once '../includes/header.php';
?>
<div class="section-card">
  <div class="section-header"><div class="section-title">💰 Encaisser une vente</div></div>
  <div class="modal-body" style="padding:24px">
    <div class="form-grid">
      <div class="form-group full"><label>Produit</label><select id="vente-produit" onchange="onProduitChange()"></select></div>
      <div class="form-group"><label>Quantité</label><input type="number" step="1" min="1" id="vente-qte" value="1" oninput="updateTotal()"></div>
      <div class="form-group"><label>Prix unitaire</label><div id="vente-prix" style="padding-top:10px;font-weight:600">—</div></div>
    </div>
    <div style="text-align:right;margin-top:16px;font-size:20px;font-weight:700" id="vente-total">Total : —</div>
    <div style="text-align:right;margin-top:16px">
      <button class="btn btn-primary" onclick="encaisser()">Encaisser (comptant)</button>
    </div>
  </div>
</div>

<div class="section-card">
  <div class="section-header"><div class="section-title">Historique des ventes</div></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Numéro</th><th>Date</th><th>Montant TTC</th><th>Statut</th></tr></thead>
      <tbody id="ventes-body"><tr><td colspan="4" style="text-align:center;padding:30px;color:var(--text-muted)">Chargement...</td></tr></tbody>
    </table>
  </div>
</div>

<script>
let allProduits = [];
async function loadProduits() {
  allProduits = await LABO.api('prod_list');
  document.getElementById('vente-produit').innerHTML = allProduits.map(p => `<option value="${p.id}" data-prix="${p.prix_vente}">${LABO.escape(p.nom)}</option>`).join('');
  onProduitChange();
}
function onProduitChange() { updateTotal(); }
function updateTotal() {
  const sel = document.getElementById('vente-produit');
  const prix = parseFloat(sel.selectedOptions[0]?.dataset.prix || 0);
  const qte = parseFloat(document.getElementById('vente-qte').value) || 0;
  document.getElementById('vente-prix').textContent = LABO.formatCurrency(prix);
  document.getElementById('vente-total').textContent = 'Total : ' + LABO.formatCurrency(prix * qte);
}
async function encaisser() {
  const produitId = document.getElementById('vente-produit').value;
  const qte = document.getElementById('vente-qte').value;
  if (!produitId || !qte || qte < 1) { LABO.toast('Quantité invalide', 'error'); return; }
  const r = await LABO.api('caisse_vente_save', { produit_id: produitId, quantite: qte });
  if (r.ok) {
    LABO.toast('Vente encaissée — ' + r.numero + ' (' + LABO.formatCurrency(r.montant_ttc) + ')');
    document.getElementById('vente-qte').value = 1;
    updateTotal();
    loadVentes();
  } else LABO.toast(r.error || 'Erreur', 'error');
}
async function loadVentes() {
  const rows = await LABO.api('mes_ventes_list');
  const e = LABO.escape;
  const statutLabels = { emise: 'Émise', payee: 'Payée' };
  const statutBadge = { emise: 'badge-navy', payee: 'badge-green' };
  document.getElementById('ventes-body').innerHTML = rows.length ? rows.map(f => `
    <tr><td><strong>${e(f.numero)}</strong></td><td>${LABO.formatDate(f.date_emission)}</td><td class="num">${LABO.formatCurrency(f.montant_ttc)}</td><td><span class="badge ${statutBadge[f.statut]||'badge-grey'}">${statutLabels[f.statut]||e(f.statut)}</span></td></tr>`).join('')
    : '<tr><td colspan="4" style="text-align:center;padding:30px;color:var(--text-muted)">Aucune vente pour le moment</td></tr>';
}

(async function () { await loadProduits(); await loadVentes(); })();
</script>
<?php require_once '../includes/footer.php'; ?>
