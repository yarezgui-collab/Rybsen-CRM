<?php
require_once '../config.php';
requireRole(['admin','labo']);
$pageTitle = 'Stock — temps réel';
$activePage = 'stock_central';
require_once '../includes/header.php';
?>
<div class="kpi-grid" id="stock-kpis">
  <div class="kpi-card red"><div class="kpi-label">Matières sous seuil</div><div class="kpi-value" id="k-mat">—</div></div>
  <div class="kpi-card gold"><div class="kpi-label">Ruptures points de vente</div><div class="kpi-value" id="k-rupt">—</div></div>
  <div class="kpi-card teal"><div class="kpi-label">Valeur stock produits finis</div><div class="kpi-value" id="k-val" style="font-size:18px">—</div></div>
  <div class="kpi-card"><div class="kpi-label">Invendus (aujourd'hui)</div><div class="kpi-value" id="k-inv">—</div></div>
  <div class="kpi-card navy"><div class="kpi-label">Pertes (aujourd'hui)</div><div class="kpi-value" id="k-pertes">—</div></div>
</div>

<div class="section-card">
  <div class="tabs">
    <button class="tab-btn active" data-tab="tab-labo">Labo central (produits finis)</button>
    <button class="tab-btn" data-tab="tab-pv">Points de vente</button>
    <button class="tab-btn" data-tab="tab-clients">Clients à terme</button>
    <button class="tab-btn" data-tab="tab-inv">Inventaire rapide</button>
  </div>

  <div class="tab-panel active" id="tab-labo">
    <div class="section-header"><div class="section-title">Stock produits finis — Laboratoire central</div></div>
    <div class="table-wrap">
      <table><thead><tr><th>Produit</th><th>Catégorie</th><th>Stock actuel</th><th>Seuil</th><th>État</th></tr></thead>
      <tbody id="labo-body"><tr><td colspan="5" style="text-align:center;padding:30px;color:var(--text-muted)">Chargement...</td></tr></tbody></table>
    </div>
  </div>

  <div class="tab-panel" id="tab-pv">
    <div class="section-header"><div class="section-title">Stock vitrine par point de vente</div></div>
    <div class="table-wrap">
      <table><thead><tr><th>Point de vente</th><th>Produit</th><th>Quantité</th></tr></thead>
      <tbody id="pv-body"><tr><td colspan="3" style="text-align:center;padding:30px;color:var(--text-muted)">Chargement...</td></tr></tbody></table>
    </div>
  </div>

  <div class="tab-panel" id="tab-clients">
    <div class="section-header"><div class="section-title">Stock détenu par les clients à terme / franchises</div></div>
    <div class="alert-box info">Cumul livré à chaque client, ajustable par inventaire.</div>
    <div class="table-wrap">
      <table><thead><tr><th>Client</th><th>Type</th><th>Produit</th><th>Quantité</th></tr></thead>
      <tbody id="clients-body"><tr><td colspan="4" style="text-align:center;padding:30px;color:var(--text-muted)">Chargement...</td></tr></tbody></table>
    </div>
  </div>

  <!-- INVENTAIRE -->
  <div class="tab-panel" id="tab-inv">
    <div class="section-header"><div class="section-title">Inventaire physique rapide</div></div>
    <div class="alert-box info">Saisissez le comptage physique. L'écart (physique − théorique) ajuste automatiquement le stock de l'entité.</div>
    <div class="filters-bar">
      <select id="inv-perimetre" onchange="onInvPerim()">
        <option value="point_vente">Point de vente</option>
        <option value="client">Client à terme / franchise</option>
        <option value="labo">Labo central (produits finis)</option>
      </select>
      <select id="inv-pv" style="min-width:220px"></select>
      <select id="inv-client" style="min-width:220px;display:none"></select>
      <button class="btn btn-outline" onclick="chargerTheorique()">Charger le stock théorique</button>
    </div>
    <div id="inv-zone" style="display:none;margin-top:14px">
      <div class="table-wrap">
        <table><thead><tr><th>Produit</th><th>Théorique</th><th>Comptage physique</th><th>Écart</th></tr></thead>
        <tbody id="inv-body"></tbody></table>
      </div>
      <div style="text-align:right;margin-top:14px">
        <button class="btn btn-primary" onclick="enregistrerInventaire()">Valider l'inventaire &amp; ajuster le stock</button>
      </div>
    </div>
    <div class="section-header" style="border-top:1px solid var(--border);margin-top:20px"><div class="section-title" style="font-size:13px">Historique des inventaires</div></div>
    <div class="table-wrap">
      <table><thead><tr><th>Date</th><th>Périmètre</th><th>Entité</th><th>Lignes</th><th>Écart total</th><th>Par</th></tr></thead>
      <tbody id="inv-hist"></tbody></table>
    </div>
  </div>
</div>

<script>
let pointsVente = [], clientsList = [], theoLignes = [];
async function loadDash() {
  const s = await LABO.api('stock_dashboard');
  document.getElementById('k-mat').textContent = s.matieres_sous_seuil ?? '—';
  document.getElementById('k-rupt').textContent = s.pv_ruptures ?? '—';
  document.getElementById('k-val').textContent = LABO.formatCurrency(s.valeur_stock_produits ?? 0);
  document.getElementById('k-inv').textContent = parseFloat(s.invendus_jour ?? 0).toFixed(0);
  document.getElementById('k-pertes').textContent = parseFloat(s.pertes_jour ?? 0).toFixed(0);
}
async function loadLabo() {
  const rows = await LABO.api('stock_produits_list');
  const e = LABO.escape;
  document.getElementById('labo-body').innerHTML = rows.length ? rows.map(p => {
    const seuil = p.seuil_mode === 'pourcentage'
      ? (parseFloat(p.seuil_pourcentage).toFixed(0) + '% de ' + parseFloat(p.stock_reference).toFixed(0))
      : (parseFloat(p.seuil_quantite) > 0 ? parseFloat(p.seuil_quantite).toFixed(0) : '—');
    const bas = p.sous_seuil == 1;
    return `<tr>
      <td><strong>${e(p.nom)}</strong></td>
      <td><span class="badge badge-grey">${e(p.categorie)}</span></td>
      <td class="num" style="${bas?'color:var(--red);font-weight:700':''}">${parseFloat(p.stock_actuel).toFixed(0)} ${e(p.unite)}</td>
      <td class="num">${seuil}</td>
      <td>${bas ? '<span class="badge badge-red">Sous seuil</span>' : '<span class="badge badge-green">OK</span>'}</td>
    </tr>`;
  }).join('') : '<tr><td colspan="5" style="text-align:center;padding:30px;color:var(--text-muted)">Aucun produit</td></tr>';
}
async function loadPv() {
  const rows = await LABO.api('spv_list');
  const e = LABO.escape;
  document.getElementById('pv-body').innerHTML = rows.length ? rows.map(x => `
    <tr><td>${e(x.point_vente_nom)}</td><td>${e(x.produit_nom)}</td>
    <td class="num" style="${parseFloat(x.quantite)<=0?'color:var(--red);font-weight:700':''}">${parseFloat(x.quantite).toFixed(0)} ${e(x.unite)}</td></tr>`).join('')
    : '<tr><td colspan="3" style="text-align:center;padding:30px;color:var(--text-muted)">Aucun stock enregistré</td></tr>';
}
async function loadClients() {
  const rows = await LABO.api('stock_clients_list');
  const e = LABO.escape;
  document.getElementById('clients-body').innerHTML = rows.length ? rows.map(x => `
    <tr><td>${e(x.client_nom)}</td><td><span class="badge badge-grey">${x.type_client==='franchise'?'Franchise':'Client à terme'}</span></td>
    <td>${e(x.produit_nom)}</td><td class="num">${parseFloat(x.quantite).toFixed(0)} ${e(x.unite)}</td></tr>`).join('')
    : '<tr><td colspan="4" style="text-align:center;padding:30px;color:var(--text-muted)">Aucun stock client</td></tr>';
}
async function loadRefsInv() {
  pointsVente = await LABO.api('pv_list');
  const cli = await LABO.api('cli_list');
  const fr = await LABO.api('fr_list');
  clientsList = [...cli.map(c=>({id:c.id,nom:c.nom})), ...fr.map(f=>({id:f.id,nom:f.nom+' (franchise)'}))];
  document.getElementById('inv-pv').innerHTML = pointsVente.map(p=>`<option value="${p.id}">${LABO.escape(p.nom)}</option>`).join('');
  document.getElementById('inv-client').innerHTML = clientsList.map(c=>`<option value="${c.id}">${LABO.escape(c.nom)}</option>`).join('');
}
function onInvPerim() {
  const p = document.getElementById('inv-perimetre').value;
  document.getElementById('inv-pv').style.display = p==='point_vente' ? '' : 'none';
  document.getElementById('inv-client').style.display = p==='client' ? '' : 'none';
  document.getElementById('inv-zone').style.display = 'none';
}
async function chargerTheorique() {
  const perim = document.getElementById('inv-perimetre').value;
  const params = { perimetre: perim };
  if (perim==='point_vente') params.point_vente_id = document.getElementById('inv-pv').value;
  if (perim==='client') params.client_id = document.getElementById('inv-client').value;
  theoLignes = await LABO.api('inventaire_theorique', params);
  if (theoLignes.error) { LABO.toast(theoLignes.error,'error'); return; }
  const e = LABO.escape;
  document.getElementById('inv-body').innerHTML = theoLignes.length ? theoLignes.map((l,i) => `
    <tr>
      <td>${e(l.produit_nom)} <span style="color:var(--text-muted);font-size:12px">${e(l.unite)}</span></td>
      <td class="num" data-theo="${l.theorique}">${parseFloat(l.theorique).toFixed(0)}</td>
      <td><input type="number" step="0.001" class="inv-phys" data-pid="${l.produit_id}" data-idx="${i}" value="${parseFloat(l.theorique).toFixed(0)}" style="width:110px" oninput="recalcEcart(this)"></td>
      <td class="num inv-ecart" data-idx="${i}">0</td>
    </tr>`).join('') : '<tr><td colspan="4" style="text-align:center;padding:20px;color:var(--text-muted)">Aucun produit en stock pour cette entité</td></tr>';
  document.getElementById('inv-zone').style.display = '';
}
function recalcEcart(inp) {
  const tr = inp.closest('tr');
  const theo = parseFloat(tr.querySelector('[data-theo]').dataset.theo);
  const phys = parseFloat(inp.value) || 0;
  const cell = tr.querySelector('.inv-ecart');
  const ecart = phys - theo;
  cell.textContent = (ecart>0?'+':'') + ecart.toFixed(0);
  cell.style.color = ecart===0 ? '' : (ecart<0 ? 'var(--red)' : 'var(--teal,green)');
}
async function enregistrerInventaire() {
  const perim = document.getElementById('inv-perimetre').value;
  const lignes = [...document.querySelectorAll('.inv-phys')].map(inp => {
    const tr = inp.closest('tr');
    return { produit_id: inp.dataset.pid, quantite_theorique: tr.querySelector('[data-theo]').dataset.theo, quantite_physique: inp.value || 0 };
  });
  if (!lignes.length) { LABO.toast('Rien à enregistrer','error'); return; }
  const params = { perimetre: perim, lignes };
  if (perim==='point_vente') params.point_vente_id = document.getElementById('inv-pv').value;
  if (perim==='client') params.client_id = document.getElementById('inv-client').value;
  const r = await LABO.api('inventaire_save', params);
  if (r.ok) { LABO.toast('Inventaire enregistré, stock ajusté ✓'); document.getElementById('inv-zone').style.display='none'; loadHist(); loadPv(); loadClients(); loadLabo(); loadDash(); }
  else LABO.toast(r.error || 'Erreur','error');
}
async function loadHist() {
  const rows = await LABO.api('inventaire_list');
  const e = LABO.escape;
  const perimLabels = { labo:'Labo central', point_vente:'Point de vente', client:'Client' };
  document.getElementById('inv-hist').innerHTML = (rows && rows.length) ? rows.map(i => `
    <tr><td>${LABO.formatDate(i.date_inventaire)}</td><td>${perimLabels[i.perimetre]||e(i.perimetre)}</td>
    <td>${e(i.point_vente_nom || i.client_nom || 'Labo central')}</td><td class="num">${i.nb_lignes}</td>
    <td class="num">${parseFloat(i.ecart_total).toFixed(0)}</td><td>${e(i.realise_par_nom)||'—'}</td></tr>`).join('')
    : '<tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text-muted)">Aucun inventaire</td></tr>';
}

(async function(){
  await loadDash(); await loadLabo(); await loadPv(); await loadClients();
  await loadRefsInv(); onInvPerim(); await loadHist();
})();
</script>
<?php require_once '../includes/footer.php'; ?>
