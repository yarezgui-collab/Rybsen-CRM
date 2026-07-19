<?php
require_once '../config.php';
requireRole(['admin']);
$pageTitle = 'Paramètres & fonctionnalités';
$activePage = 'parametres';
require_once '../includes/header.php';
?>
<div class="section-card">
  <div class="section-header"><div class="section-title">🛠️ Paramètres généraux</div></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Paramètre</th><th>Valeur</th><th>Actions</th></tr></thead>
      <tbody id="param-body"><tr><td colspan="3" style="text-align:center;padding:30px;color:var(--text-muted)">Chargement...</td></tr></tbody>
    </table>
  </div>
</div>

<script>
const paramLabels = {
  business: 'Nom de l\'établissement',
  currency: 'Devise',
  tva_defaut: 'TVA par défaut (%)',
  feature_stock_matieres: 'Fonctionnalité : Stock matières premières',
  feature_traceabilite_lots: 'Fonctionnalité : Traçabilité lots/DLC',
  feature_pertes: 'Fonctionnalité : Pertes & invendus',
  feature_evenementiel: 'Fonctionnalité : Commandes événementielles'
};
const isFeatureFlag = (cle) => cle.startsWith('feature_');

async function loadParams() {
  const rows = await LABO.api('param_list');
  const e = LABO.escape;
  document.getElementById('param-body').innerHTML = rows.map(p => `
    <tr>
      <td>${e(paramLabels[p.cle] || p.cle)}</td>
      <td>
        ${isFeatureFlag(p.cle)
          ? `<span class="badge ${p.valeur === '1' ? 'badge-green' : 'badge-grey'}">${p.valeur === '1' ? 'Activée' : 'Désactivée'}</span>`
          : e(p.valeur)}
      </td>
      <td>
        ${isFeatureFlag(p.cle)
          ? `<button class="btn btn-outline btn-sm" onclick="toggleFeature('${p.cle}','${p.valeur}')">${p.valeur === '1' ? 'Désactiver' : 'Activer'}</button>`
          : `<button class="btn btn-outline btn-sm" onclick="editParam('${p.cle}','${e(p.valeur)}')">✏️</button>`}
      </td>
    </tr>`).join('');
}
async function toggleFeature(cle, valeurActuelle) {
  const r = await LABO.api('param_save', { cle, valeur: valeurActuelle === '1' ? '0' : '1' });
  if (r.ok) { LABO.toast('Mis à jour ✓'); loadParams(); }
}
async function editParam(cle, valeurActuelle) {
  const nouvelle = prompt('Nouvelle valeur pour "' + (paramLabels[cle] || cle) + '"', valeurActuelle);
  if (nouvelle === null) return;
  const r = await LABO.api('param_save', { cle, valeur: nouvelle });
  if (r.ok) { LABO.toast('Mis à jour ✓'); loadParams(); }
}
loadParams();
</script>
<?php require_once '../includes/footer.php'; ?>
