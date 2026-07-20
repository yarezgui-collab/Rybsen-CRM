<?php
require_once '../config.php';
requireRole(['point_vente']);
$pageTitle = 'Mon stock vitrine';
$activePage = 'mon_stock';
require_once '../includes/header.php';
?>
<div class="section-card">
  <div class="section-header"><div class="section-title">📊 Stock vitrine</div></div>
  <div class="alert-box info">Le stock se met à jour automatiquement lors de vos ventes et à chaque réception d'une livraison du labo.</div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Produit</th><th>Quantité disponible</th></tr></thead>
      <tbody id="stock-body"><tr><td colspan="2" style="text-align:center;padding:30px;color:var(--text-muted)">Chargement...</td></tr></tbody>
    </table>
  </div>
</div>

<script>
async function loadStock() {
  const rows = await LABO.api('mon_stock_pv_list');
  const e = LABO.escape;
  document.getElementById('stock-body').innerHTML = rows.length ? rows.map(s => `
    <tr><td>${e(s.produit_nom)}</td><td class="num" style="${parseFloat(s.quantite) <= 0 ? 'color:var(--red);font-weight:700' : ''}">${parseFloat(s.quantite).toFixed(0)} ${e(s.unite)}</td></tr>`).join('')
    : '<tr><td colspan="2" style="text-align:center;padding:30px;color:var(--text-muted)">Aucun stock enregistré pour le moment</td></tr>';
}
loadStock();
</script>
<?php require_once '../includes/footer.php'; ?>
