<?php
require_once '../config.php';
requireRole(['admin','labo']);
$pageTitle = 'Catalogue par compte';
$activePage = 'catalogue_comptes';
require_once '../includes/header.php';
?>
<div class="section-card">
  <div class="section-header"><div class="section-title">📋 Catalogue autorisé par compte</div></div>
  <div class="alert-box info">Choisissez un compte (franchise, point de vente ou client à terme), puis les catégories et articles qu'il pourra commander. <strong>Si aucun article n'est coché, le compte voit le catalogue complet.</strong></div>
  <div class="filters-bar">
    <select id="sel-cible" onchange="loadAutorises()" style="min-width:320px">
      <option value="">— Choisir un compte —</option>
    </select>
    <button class="btn btn-outline btn-sm" onclick="toutCocher(true)">Tout cocher</button>
    <button class="btn btn-outline btn-sm" onclick="toutCocher(false)">Tout décocher (= catalogue complet)</button>
  </div>
  <div id="catalogue-zone" style="display:none">
    <div id="catalogue-list" style="padding:8px 4px"></div>
    <div style="text-align:right;margin-top:16px">
      <button class="btn btn-primary" onclick="enregistrer()">Enregistrer les autorisations</button>
    </div>
  </div>
</div>

<script>
let allProduits = [], cibles = [];
async function loadRefs() {
  cibles = await LABO.api('catalogue_cibles_list');
  allProduits = await LABO.api('prod_list');
  const e = LABO.escape;
  const label = c => {
    const t = c.cible_type === 'point_vente' ? 'Point de vente' : (c.sous_type === 'franchise' ? 'Franchise' : 'Client à terme');
    return `${t} — ${e(c.nom)}`;
  };
  document.getElementById('sel-cible').innerHTML = '<option value="">— Choisir un compte —</option>' +
    cibles.map((c,i) => `<option value="${i}">${label(c)}</option>`).join('');
}
async function loadAutorises() {
  const idx = document.getElementById('sel-cible').value;
  const zone = document.getElementById('catalogue-zone');
  if (idx === '') { zone.style.display = 'none'; return; }
  const cible = cibles[idx];
  const res = await LABO.api('catalogue_autorise_get', { cible_type: cible.cible_type, cible_id: cible.cible_id });
  const autorises = new Set((res.produit_ids || []).map(Number));
  const cochesParDefaut = res.tous; // si tous=true, rien de restreint -> on n'en coche aucun (= complet)
  renderCatalogue(autorises, cochesParDefaut);
  zone.style.display = '';
}
function renderCatalogue(autorises, tous) {
  const e = LABO.escape;
  // groupe par catégorie
  const groupes = {};
  allProduits.forEach(p => { (groupes[p.categorie] = groupes[p.categorie] || []).push(p); });
  let html = '';
  Object.keys(groupes).sort().forEach(cat => {
    html += `<div class="section-header" style="border:none;padding:10px 0 6px">
      <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:700;color:var(--wheat,#6B4A2F)">
        <input type="checkbox" class="cat-check" data-cat="${e(cat)}" onchange="toggleCat(this)"> ${e(cat)}
      </label></div>`;
    html += '<div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:8px">';
    groupes[cat].forEach(p => {
      const checked = (!tous && autorises.has(Number(p.id))) ? 'checked' : '';
      html += `<label style="display:flex;align-items:center;gap:6px;background:var(--cream,#FAF6EF);padding:8px 12px;border-radius:8px;cursor:pointer;min-width:180px">
        <input type="checkbox" class="prod-check" data-cat="${e(cat)}" value="${p.id}" ${checked}>
        <span>${e(p.nom)} <span style="color:var(--text-muted,#888);font-size:12px">${LABO.formatCurrency(p.prix_vente)}</span></span>
      </label>`;
    });
    html += '</div>';
  });
  document.getElementById('catalogue-list').innerHTML = html;
  syncCatChecks();
}
function toggleCat(cb) {
  document.querySelectorAll(`.prod-check[data-cat="${CSS.escape(cb.dataset.cat)}"]`).forEach(x => x.checked = cb.checked);
}
function syncCatChecks() {
  document.querySelectorAll('.cat-check').forEach(cc => {
    const prods = document.querySelectorAll(`.prod-check[data-cat="${CSS.escape(cc.dataset.cat)}"]`);
    cc.checked = prods.length > 0 && [...prods].every(x => x.checked);
  });
}
function toutCocher(v) {
  document.querySelectorAll('.prod-check, .cat-check').forEach(x => x.checked = v);
}
async function enregistrer() {
  const idx = document.getElementById('sel-cible').value;
  if (idx === '') return;
  const cible = cibles[idx];
  const ids = [...document.querySelectorAll('.prod-check:checked')].map(x => parseInt(x.value));
  const r = await LABO.api('catalogue_autorise_save', { cible_type: cible.cible_type, cible_id: cible.cible_id, produit_ids: ids });
  if (r.ok) LABO.toast(ids.length ? (ids.length + ' article(s) autorisé(s) ✓') : 'Catalogue complet rétabli ✓');
  else LABO.toast(r.error || 'Erreur', 'error');
}
document.addEventListener('change', e => { if (e.target.classList.contains('prod-check')) syncCatChecks(); });
loadRefs();
</script>
<?php require_once '../includes/footer.php'; ?>
