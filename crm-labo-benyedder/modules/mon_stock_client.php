<?php
require_once '../config.php';
requireRole(['franchise','client_terme']);
$pageTitle = 'Mon stock';
$activePage = 'mon_stock_client';
require_once '../includes/header.php';
?>
<div class="section-card">
  <div class="section-header"><div class="section-title">📊 Mon stock</div></div>
  <div class="alert-box info">Cumul des produits qui vous ont été livrés par le laboratoire. Cette quantité est ajustée lors des inventaires réalisés avec l'équipe Ben Yedder.</div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Produit</th><th>Quantité en stock</th></tr></thead>
      <tbody id="stock-body"><tr><td colspan="2" style="text-align:center;padding:30px;color:var(--text-muted)">Chargement...</td></tr></tbody>
    </table>
  </div>
</div>
<script>
async function loadStock() {
  const rows = await LABO.api('mon_stock_client_list');
  const e = LABO.escape;
  if (rows.error) { document.getElementById('stock-body').innerHTML = `<tr><td colspan="2" style="text-align:center;padding:30px;color:var(--red)">${e(rows.error)}</td></tr>`; return; }
  document.getElementById('stock-body').innerHTML = rows.length ? rows.map(s => `
    <tr><td>${e(s.produit_nom)}</td><td class="num">${parseFloat(s.quantite).toFixed(0)} ${e(s.unite)}</td></tr>`).join('')
    : '<tr><td colspan="2" style="text-align:center;padding:30px;color:var(--text-muted)">Aucun stock enregistré pour le moment</td></tr>';
}
loadStock();
</script>
<?php require_once '../includes/footer.php'; ?>
