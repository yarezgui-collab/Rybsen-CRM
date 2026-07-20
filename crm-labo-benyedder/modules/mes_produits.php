<?php
require_once '../config.php';
requireRole(['franchise','client_terme','point_vente']);
$pageTitle = 'Produits & tarifs';
$activePage = 'mes_produits';
require_once '../includes/header.php';
?>
<div class="section-card">
  <div class="section-header">
    <div class="section-title">📖 Catalogue Ben Yedder</div>
  </div>
  <div class="filters-bar">
    <input type="text" id="search-prod" placeholder="🔍 Rechercher un produit...">
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Produit</th><th>Catégorie</th><th>Prix</th></tr></thead>
      <tbody id="prod-body"><tr><td colspan="3" style="text-align:center;padding:30px;color:var(--text-muted)">Chargement...</td></tr></tbody>
    </table>
  </div>
</div>

<script>
let allProduits = [];
async function loadProduits() {
  allProduits = await LABO.api('prod_list');
  applyFilters();
}
function applyFilters() {
  const q = document.getElementById('search-prod').value.toLowerCase();
  render(allProduits.filter(p => !q || p.nom.toLowerCase().includes(q) || p.categorie.toLowerCase().includes(q)));
}
function render(data) {
  const e = LABO.escape;
  document.getElementById('prod-body').innerHTML = data.length ? data.map(p => `
    <tr>
      <td><strong>${e(p.nom)}</strong></td>
      <td><span class="badge badge-grey">${e(p.categorie)}</span></td>
      <td class="num">${LABO.formatCurrency(p.prix_vente)} / ${e(p.unite)}</td>
    </tr>`).join('') : '<tr><td colspan="3" style="text-align:center;padding:30px;color:var(--text-muted)">Aucun produit</td></tr>';
}
document.getElementById('search-prod').addEventListener('input', applyFilters);
loadProduits();
</script>
<?php require_once '../includes/footer.php'; ?>
