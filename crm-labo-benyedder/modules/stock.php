<?php
require_once '../config.php';
requireRole(['admin','labo']);
$pageTitle = 'Stock & matières premières';
$activePage = 'stock';
require_once '../includes/header.php';
?>
<div class="section-card">
  <div class="tabs">
    <button class="tab-btn active" data-tab="tab-mp">Matières premières</button>
    <button class="tab-btn" data-tab="tab-pf">Produits finis</button>
    <button class="tab-btn" data-tab="tab-pv">Points de vente</button>
    <button class="tab-btn" data-tab="tab-pertes">Pertes &amp; invendus</button>
  </div>

  <!-- ── MATIÈRES PREMIÈRES ── -->
  <div class="tab-panel active" id="tab-mp">
    <div class="section-header"><div class="section-title">Stock matières premières</div></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Matière</th><th>Stock actuel</th><th>Seuil alerte</th><th>Actions</th></tr></thead>
        <tbody id="mp-body"><tr><td colspan="4" style="text-align:center;padding:30px;color:var(--text-muted)">Chargement...</td></tr></tbody>
      </table>
    </div>
    <div class="section-header" style="border-top:1px solid var(--border)"><div class="section-title" style="font-size:13px">Derniers mouvements</div></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Date</th><th>Matière</th><th>Type</th><th>Quantité</th><th>Origine</th><th>Notes</th></tr></thead>
        <tbody id="mp-mvt-body"></tbody>
      </table>
    </div>
  </div>

  <!-- ── PRODUITS FINIS ── -->
  <div class="tab-panel" id="tab-pf">
    <div class="section-header"><div class="section-title">Stock produits finis (labo)</div></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Produit</th><th>Stock actuel</th></tr></thead>
        <tbody id="pf-body"><tr><td colspan="2" style="text-align:center;padding:30px;color:var(--text-muted)">Chargement...</td></tr></tbody>
      </table>
    </div>
  </div>

  <!-- ── POINTS DE VENTE ── -->
  <div class="tab-panel" id="tab-pv">
    <div class="section-header">
      <div class="section-title">Stock vitrine par point de vente</div>
      <div class="section-actions"><button class="btn btn-primary" onclick="LABO.openModal('modal-vente')">💰 Vente passager</button></div>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Point de vente</th><th>Produit</th><th>Quantité</th></tr></thead>
        <tbody id="spv-body"><tr><td colspan="3" style="text-align:center;padding:30px;color:var(--text-muted)">Chargement...</td></tr></tbody>
      </table>
    </div>
  </div>

  <!-- ── PERTES ── -->
  <div class="tab-panel" id="tab-pertes">
    <div class="section-header">
      <div class="section-title">Pertes / invendus</div>
      <div class="section-actions"><button class="btn btn-primary" onclick="LABO.openModal('modal-perte')">+ Déclarer une perte</button></div>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Date</th><th>Produit</th><th>Quantité</th><th>Type</th><th>Source</th><th>Point de vente</th><th>Motif</th></tr></thead>
        <tbody id="perte-body"><tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-muted)">Chargement...</td></tr></tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal ajustement matière -->
<div class="modal-overlay" id="modal-mp-ajust">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Corriger le stock</div><button class="modal-close" onclick="LABO.closeModal('modal-mp-ajust')">✕</button></div>
    <div class="modal-body">
      <input type="hidden" id="adj-matiere-id">
      <div class="form-group"><label id="adj-matiere-label">Matière</label><input type="number" step="0.001" id="adj-nouveau-stock"></div>
      <div class="form-group" style="margin-top:12px"><label>Motif</label><input type="text" id="adj-notes" placeholder="Ex: Inventaire physique"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="LABO.closeModal('modal-mp-ajust')">Annuler</button>
      <button class="btn btn-primary" onclick="ajusterMp()">Enregistrer</button>
    </div>
  </div>
</div>

<!-- Modal vente passager -->
<div class="modal-overlay" id="modal-vente">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Vente passager</div><button class="modal-close" onclick="LABO.closeModal('modal-vente')">✕</button></div>
    <div class="modal-body">
      <div class="form-grid">
        <div class="form-group full"><label>Point de vente</label><select id="vente-pv"></select></div>
        <div class="form-group full"><label>Produit</label><select id="vente-produit" onchange="onVenteProduitChange()"></select></div>
        <div class="form-group"><label>Quantité</label><input type="number" step="1" id="vente-qte" value="1"></div>
        <div class="form-group"><label>Prix unitaire</label><input type="number" step="0.001" id="vente-prix"></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="LABO.closeModal('modal-vente')">Annuler</button>
      <button class="btn btn-primary" onclick="enregistrerVente()">Encaisser (comptant)</button>
    </div>
  </div>
</div>

<!-- Modal perte -->
<div class="modal-overlay" id="modal-perte">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Déclarer une perte / invendu</div><button class="modal-close" onclick="LABO.closeModal('modal-perte')">✕</button></div>
    <div class="modal-body">
      <div class="form-grid">
        <div class="form-group"><label>Source</label>
          <select id="perte-source">
            <option value="point_vente">Point de vente</option>
            <option value="livraison">Livraison</option>
            <option value="production">Production</option>
          </select>
        </div>
        <div class="form-group" id="wrap-perte-pv"><label>Point de vente</label><select id="perte-pv"></select></div>
        <div class="form-group full"><label>Type</label>
          <select id="perte-type">
            <option value="invendu">Invendu (conservé / stocké — n'impacte pas le stock)</option>
            <option value="casse">Casse (sortie de stock)</option>
            <option value="perime">Périmé (sortie de stock)</option>
          </select>
        </div>
        <div class="form-group full"><label>Produit</label><select id="perte-produit"></select></div>
        <div class="form-group"><label>Quantité</label><input type="number" step="0.001" id="perte-qte"></div>
        <div class="form-group"><label>Date</label><input type="date" id="perte-date"></div>
        <div class="form-group full"><label>Motif</label><input type="text" id="perte-motif" placeholder="Ex: fin de journée"></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="LABO.closeModal('modal-perte')">Annuler</button>
      <button class="btn btn-primary" onclick="declarerPerte()">Enregistrer</button>
    </div>
  </div>
</div>

<script>
let allMp = [], allProduits = [], allPointsVente = [];

async function loadRefs() {
  [allMp, allProduits, allPointsVente] = await Promise.all([LABO.api('mp_list'), LABO.api('prod_list'), LABO.api('pv_list')]);
  document.getElementById('vente-pv').innerHTML = allPointsVente.map(p => `<option value="${p.id}">${LABO.escape(p.nom)}</option>`).join('');
  document.getElementById('vente-produit').innerHTML = allProduits.map(p => `<option value="${p.id}" data-prix="${p.prix_vente}">${LABO.escape(p.nom)}</option>`).join('');
  document.getElementById('perte-pv').innerHTML = allPointsVente.map(p => `<option value="${p.id}">${LABO.escape(p.nom)}</option>`).join('');
  document.getElementById('perte-produit').innerHTML = allProduits.map(p => `<option value="${p.id}">${LABO.escape(p.nom)}</option>`).join('');
  onVenteProduitChange();
}
function onVenteProduitChange() {
  const sel = document.getElementById('vente-produit');
  document.getElementById('vente-prix').value = sel.selectedOptions[0]?.dataset.prix || 0;
}

async function loadMp() {
  const rows = await LABO.api('mp_list');
  const e = LABO.escape;
  document.getElementById('mp-body').innerHTML = rows.length ? rows.map(m => `
    <tr>
      <td>${e(m.nom)}</td>
      <td class="num" style="${parseFloat(m.stock_actuel) <= parseFloat(m.seuil_alerte) ? 'color:var(--red);font-weight:700' : ''}">${parseFloat(m.stock_actuel).toFixed(3)} ${e(m.unite)}</td>
      <td class="num">${parseFloat(m.seuil_alerte).toFixed(3)}</td>
      <td><button class="btn btn-outline btn-sm" onclick="openAjustMp(${m.id},'${e(m.nom)}',${m.stock_actuel})">Corriger</button></td>
    </tr>`).join('') : '<tr><td colspan="4" style="text-align:center;padding:30px;color:var(--text-muted)">Aucune matière</td></tr>';
}
function openAjustMp(id, nom, stockActuel) {
  document.getElementById('adj-matiere-id').value = id;
  document.getElementById('adj-matiere-label').textContent = 'Nouveau stock — ' + nom;
  document.getElementById('adj-nouveau-stock').value = stockActuel;
  document.getElementById('adj-notes').value = '';
  LABO.openModal('modal-mp-ajust');
}
async function ajusterMp() {
  const r = await LABO.api('mp_ajuster', {
    matiere_id: document.getElementById('adj-matiere-id').value,
    nouveau_stock: document.getElementById('adj-nouveau-stock').value,
    notes: document.getElementById('adj-notes').value
  });
  if (r.ok) { LABO.closeModal('modal-mp-ajust'); LABO.toast('Stock corrigé ✓'); loadMp(); loadMvtMp(); }
  else LABO.toast(r.error || 'Erreur', 'error');
}
async function loadMvtMp() {
  const rows = await LABO.api('mp_mouvements_list');
  const e = LABO.escape;
  const typeBadge = { entree: 'badge-green', sortie: 'badge-red', ajustement: 'badge-gold' };
  document.getElementById('mp-mvt-body').innerHTML = rows.length ? rows.slice(0,20).map(m => `
    <tr>
      <td>${LABO.formatDate(m.date_mouvement)}</td>
      <td>${e(m.matiere_nom)}</td>
      <td><span class="badge ${typeBadge[m.type_mouvement]}">${e(m.type_mouvement)}</span></td>
      <td class="num">${parseFloat(m.quantite).toFixed(3)} ${e(m.unite)}</td>
      <td>${e(m.origine)}</td>
      <td>${e(m.notes) || '—'}</td>
    </tr>`).join('') : '<tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text-muted)">Aucun mouvement</td></tr>';
}

async function loadPf() {
  const rows = await LABO.api('produit_stock_list');
  const e = LABO.escape;
  document.getElementById('pf-body').innerHTML = rows.length ? rows.map(p => `
    <tr><td>${e(p.nom)}</td><td class="num">${parseFloat(p.stock_actuel).toFixed(3)}</td></tr>`).join('') : '<tr><td colspan="2" style="text-align:center;padding:30px;color:var(--text-muted)">Aucun produit</td></tr>';
}

async function loadSpv() {
  const rows = await LABO.api('spv_list');
  const e = LABO.escape;
  document.getElementById('spv-body').innerHTML = rows.length ? rows.map(s => `
    <tr><td>${e(s.point_vente_nom)}</td><td>${e(s.produit_nom)}</td><td class="num">${parseFloat(s.quantite).toFixed(3)}</td></tr>`).join('') : '<tr><td colspan="3" style="text-align:center;padding:30px;color:var(--text-muted)">Aucun stock en point de vente pour le moment</td></tr>';
}

async function enregistrerVente() {
  const r = await LABO.api('vente_passager_save', {
    point_vente_id: document.getElementById('vente-pv').value,
    produit_id: document.getElementById('vente-produit').value,
    quantite: document.getElementById('vente-qte').value,
    prix_unitaire: document.getElementById('vente-prix').value
  });
  if (r.ok) { LABO.closeModal('modal-vente'); LABO.toast('Vente encaissée — facture ' + r.numero + ' (' + LABO.formatCurrency(r.montant_ttc) + ')'); loadSpv(); loadPf(); }
  else LABO.toast(r.error || 'Erreur', 'error');
}

function onSourceChange() {
  document.getElementById('wrap-perte-pv').style.display = document.getElementById('perte-source').value === 'point_vente' ? '' : 'none';
}
document.getElementById('perte-source').addEventListener('change', onSourceChange);

async function loadPertes() {
  const rows = await LABO.api('perte_list');
  const e = LABO.escape;
  document.getElementById('perte-body').innerHTML = rows.length ? rows.map(p => `
    <tr>
      <td>${LABO.formatDate(p.date_perte)}</td>
      <td>${e(p.produit_nom)}</td>
      <td class="num">${parseFloat(p.quantite).toFixed(3)}</td>
      <td><span class="badge ${p.type_perte==='invendu'?'badge-gold':'badge-red'}">${({invendu:'Invendu',casse:'Casse',perime:'Périmé'})[p.type_perte]||e(p.type_perte||'')}</span></td>
      <td>${e(p.source)}</td>
      <td>${e(p.point_vente_nom) || '—'}</td>
      <td>${e(p.motif) || '—'}</td>
    </tr>`).join('') : '<tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-muted)">Aucune perte déclarée</td></tr>';
}
async function declarerPerte() {
  const r = await LABO.api('perte_save', {
    source: document.getElementById('perte-source').value,
    point_vente_id: document.getElementById('perte-source').value === 'point_vente' ? document.getElementById('perte-pv').value : null,
    produit_id: document.getElementById('perte-produit').value,
    quantite: document.getElementById('perte-qte').value,
    type_perte: document.getElementById('perte-type').value,
    date_perte: document.getElementById('perte-date').value || new Date().toISOString().slice(0,10),
    motif: document.getElementById('perte-motif').value
  });
  if (r.ok) { LABO.closeModal('modal-perte'); LABO.toast('Perte enregistrée'); loadPertes(); loadPf(); loadSpv(); }
  else LABO.toast(r.error || 'Erreur', 'error');
}

(async function () {
  await loadRefs();
  onSourceChange();
  document.getElementById('perte-date').value = new Date().toISOString().slice(0,10);
  loadMp(); loadMvtMp(); loadPf(); loadSpv(); loadPertes();
})();
</script>
<?php require_once '../includes/footer.php'; ?>
