<?php
require_once '../config.php';
requireRole(['admin']);
$pageTitle = 'Statistiques';
$activePage = 'statistiques';
require_once '../includes/header.php';
?>
<div class="dash-grid" style="margin-bottom:20px">
  <div class="section-card">
    <div class="section-header"><div class="section-title">💰 Marge estimée par produit</div></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Produit</th><th>Prix vente</th><th>Coût matière</th><th>Marge</th></tr></thead>
        <tbody id="marge-body"><tr><td colspan="4" style="text-align:center;padding:20px;color:var(--text-muted)">Chargement...</td></tr></tbody>
      </table>
    </div>
  </div>
  <div class="section-card">
    <div class="section-header"><div class="section-title">🔴 Matières sous le seuil d'alerte</div></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Matière</th><th>Stock</th><th>Seuil</th></tr></thead>
        <tbody id="stockbas-body"><tr><td colspan="3" style="text-align:center;padding:20px;color:var(--text-muted)">Chargement...</td></tr></tbody>
      </table>
    </div>
  </div>
</div>

<div class="dash-grid" style="margin-bottom:20px">
  <div class="section-card">
    <div class="section-header"><div class="section-title">📦 Produits les plus vendus</div></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Produit</th><th>Quantité vendue</th><th>Montant</th></tr></thead>
        <tbody id="ventes-body"><tr><td colspan="3" style="text-align:center;padding:20px;color:var(--text-muted)">Chargement...</td></tr></tbody>
      </table>
    </div>
  </div>
  <div class="section-card">
    <div class="section-header"><div class="section-title">🧺 Consommation matières premières (production)</div></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Matière</th><th>Consommé</th></tr></thead>
        <tbody id="conso-body"><tr><td colspan="2" style="text-align:center;padding:20px;color:var(--text-muted)">Chargement...</td></tr></tbody>
      </table>
    </div>
  </div>
</div>

<div class="section-card">
  <div class="section-header"><div class="section-title">🚦 Performance par canal</div></div>
  <div class="kpi-grid" id="canal-kpis"></div>
</div>

<div class="section-card">
  <div class="section-header"><div class="section-title">⏳ Encours clients à terme</div></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Client</th><th>Encours</th></tr></thead>
      <tbody id="encours-body"><tr><td colspan="2" style="text-align:center;padding:20px;color:var(--text-muted)">Chargement...</td></tr></tbody>
    </table>
  </div>
</div>

<script>
const canalLabels = { terme: 'Clients à terme', franchise: 'Franchises', point_vente: 'Points de vente' };

(async function () {
  const e = LABO.escape;

  const marge = await LABO.api('stats_marge');
  document.getElementById('marge-body').innerHTML = marge.length ? marge.map(m => `
    <tr><td>${e(m.nom)}</td><td class="num">${LABO.formatCurrency(m.prix_vente)}</td><td class="num">${LABO.formatCurrency(m.cout_matiere)}</td><td class="num" style="color:var(--ok)">${LABO.formatCurrency(m.marge_estimee)}</td></tr>`).join('')
    : '<tr><td colspan="4" style="text-align:center;padding:20px;color:var(--text-muted)">Aucune donnée</td></tr>';

  const stockBas = await LABO.api('stats_stock_bas');
  document.getElementById('stockbas-body').innerHTML = stockBas.length ? stockBas.map(m => `
    <tr><td>${e(m.nom)}</td><td class="num" style="color:var(--red);font-weight:700">${parseFloat(m.stock_actuel).toFixed(3)} ${e(m.unite)}</td><td class="num">${parseFloat(m.seuil_alerte).toFixed(3)}</td></tr>`).join('')
    : '<tr><td colspan="3" style="text-align:center;padding:20px;color:var(--ok)">Aucune alerte — tous les stocks sont au-dessus du seuil ✓</td></tr>';

  const ventes = await LABO.api('stats_produits_vendus');
  document.getElementById('ventes-body').innerHTML = ventes.length ? ventes.map(v => `
    <tr><td>${e(v.nom)}</td><td class="num">${parseFloat(v.quantite_totale).toFixed(0)}</td><td class="num">${LABO.formatCurrency(v.montant_total)}</td></tr>`).join('')
    : '<tr><td colspan="3" style="text-align:center;padding:20px;color:var(--text-muted)">Aucune vente</td></tr>';

  const conso = await LABO.api('stats_consommation');
  document.getElementById('conso-body').innerHTML = conso.length ? conso.map(c => `
    <tr><td>${e(c.nom)}</td><td class="num">${parseFloat(c.consomme).toFixed(3)} ${e(c.unite)}</td></tr>`).join('')
    : '<tr><td colspan="2" style="text-align:center;padding:20px;color:var(--text-muted)">Aucune consommation enregistrée</td></tr>';

  const canal = await LABO.api('stats_ventes_canal');
  const colors = { terme: 'navy', franchise: 'gold', point_vente: 'teal' };
  document.getElementById('canal-kpis').innerHTML = ['terme','franchise','point_vente'].map(c => {
    const row = canal.find(x => x.canal === c) || { nb_commandes: 0, montant_total: 0 };
    return `<div class="kpi-card ${colors[c]}"><div class="kpi-label">${canalLabels[c]}</div><div class="kpi-value" style="font-size:22px">${LABO.formatCurrency(row.montant_total)}</div><div class="kpi-sub">${row.nb_commandes} commande${row.nb_commandes > 1 ? 's' : ''}</div></div>`;
  }).join('');

  const encours = await LABO.api('stats_encours');
  document.getElementById('encours-body').innerHTML = encours.length ? encours.map(c => `
    <tr><td>${e(c.nom)}</td><td class="num" style="color:var(--red);font-weight:700">${LABO.formatCurrency(c.encours)}</td></tr>`).join('')
    : '<tr><td colspan="2" style="text-align:center;padding:20px;color:var(--ok)">Aucun encours en cours ✓</td></tr>';
})();
</script>
<?php require_once '../includes/footer.php'; ?>
