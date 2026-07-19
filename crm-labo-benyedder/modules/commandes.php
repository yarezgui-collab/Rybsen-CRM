<?php
require_once '../config.php';
requireRole(['admin','labo']);
$pageTitle = 'Commandes';
$activePage = 'commandes';
require_once '../includes/header.php';
?>
<div class="section-card">
  <div class="section-header">
    <div class="section-title">📦 Commandes</div>
    <div class="section-actions"><button class="btn btn-primary" onclick="openAddCmd()">+ Commande</button></div>
  </div>
  <div class="filters-bar">
    <input type="text" id="search-cmd" placeholder="🔍 Rechercher...">
    <select id="filter-canal">
      <option value="">Tous les canaux</option>
      <option value="terme">Clients à terme</option>
      <option value="franchise">Franchises</option>
      <option value="point_vente">Points de vente</option>
    </select>
    <select id="filter-statut">
      <option value="">Tous les statuts</option>
      <option value="brouillon">Brouillon</option>
      <option value="confirmee">Confirmée</option>
      <option value="en_production">En production</option>
      <option value="livree">Livrée</option>
      <option value="facturee">Facturée</option>
      <option value="annulee">Annulée</option>
    </select>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>#</th><th>Canal</th><th>Destination</th><th>Type</th><th>Date commande</th><th>Livraison prévue</th><th>Montant</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody id="cmd-body"><tr><td colspan="9" style="text-align:center;padding:30px;color:var(--text-muted)">Chargement...</td></tr></tbody>
    </table>
  </div>
</div>

<!-- Modal Détail -->
<div class="modal-overlay" id="modal-detail">
  <div class="modal" style="max-width:700px">
    <div class="modal-header">
      <div class="modal-title" id="detail-title">Commande</div>
      <button class="modal-close" onclick="LABO.closeModal('modal-detail')">✕</button>
    </div>
    <div class="modal-body" id="detail-body"></div>
  </div>
</div>

<!-- Modal Ajout/Édition -->
<div class="modal-overlay" id="modal-cmd">
  <div class="modal" style="max-width:700px">
    <div class="modal-header">
      <div class="modal-title" id="modal-cmd-title">Nouvelle commande</div>
      <button class="modal-close" onclick="LABO.closeModal('modal-cmd')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="cmd-id">
      <div class="form-grid">
        <div class="form-group"><label>Canal *</label>
          <select id="cmd-canal" onchange="onCanalChange()">
            <option value="terme">Client à terme</option>
            <option value="franchise">Franchise</option>
            <option value="point_vente">Point de vente</option>
          </select>
        </div>
        <div class="form-group"><label>Type</label>
          <select id="cmd-type">
            <option value="ponctuelle">Ponctuelle</option>
            <option value="reguliere">Régulière</option>
            <option value="evenementielle">Événementielle</option>
          </select>
        </div>
        <div class="form-group" id="wrap-client"><label>Client à terme *</label><select id="cmd-client"></select></div>
        <div class="form-group" id="wrap-franchise" style="display:none"><label>Franchise *</label><select id="cmd-franchise"></select></div>
        <div class="form-group" id="wrap-pv" style="display:none"><label>Point de vente *</label><select id="cmd-pv"></select></div>
        <div class="form-group"><label>Date de commande</label><input type="date" id="cmd-date"></div>
        <div class="form-group"><label>Livraison prévue</label><input type="date" id="cmd-date-liv"></div>
        <div class="form-group full"><label>Notes</label><input type="text" id="cmd-notes"></div>
      </div>

      <div style="margin-top:20px">
        <div class="section-header" style="padding:0 0 10px;border:none">
          <div class="section-title" style="font-size:13px">Lignes de commande</div>
          <div class="section-actions"><button class="btn btn-outline btn-sm" onclick="addLigne()">+ Ligne</button></div>
        </div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Produit</th><th>Quantité</th><th>Prix unitaire</th><th>Total</th><th></th></tr></thead>
            <tbody id="lignes-body"></tbody>
          </table>
        </div>
        <div style="text-align:right;margin-top:10px;font-weight:700" id="cmd-total"></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="LABO.closeModal('modal-cmd')">Annuler</button>
      <button class="btn btn-primary" onclick="saveCmd()">Enregistrer</button>
    </div>
  </div>
</div>

<script>
let allCmd = [], allClientsTerme = [], allFranchises = [], allPointsVente = [], allProduits = [];
const canalLabels = { terme: 'Client à terme', franchise: 'Franchise', point_vente: 'Point de vente' };
const canalBadge = { terme: 'badge-navy', franchise: 'badge-gold', point_vente: 'badge-teal' };
const statutLabels = { brouillon: 'Brouillon', confirmee: 'Confirmée', en_production: 'En production', livree: 'Livrée', facturee: 'Facturée', annulee: 'Annulée' };
const statutBadge = { brouillon: 'badge-grey', confirmee: 'badge-navy', en_production: 'badge-gold', livree: 'badge-teal', facturee: 'badge-green', annulee: 'badge-red' };

async function loadRefs() {
  [allClientsTerme, allFranchises, allPointsVente, allProduits] = await Promise.all([
    LABO.api('cli_list'), LABO.api('fr_list'), LABO.api('pv_list'), LABO.api('prod_list')
  ]);
  document.getElementById('cmd-client').innerHTML = allClientsTerme.map(c => `<option value="${c.id}">${LABO.escape(c.nom)}</option>`).join('');
  document.getElementById('cmd-franchise').innerHTML = allFranchises.map(c => `<option value="${c.id}">${LABO.escape(c.nom)}</option>`).join('');
  document.getElementById('cmd-pv').innerHTML = allPointsVente.map(p => `<option value="${p.id}">${LABO.escape(p.nom)}</option>`).join('');
}

async function loadCmd() {
  allCmd = await LABO.api('cmd_list');
  applyFiltersCmd();
}
function applyFiltersCmd() {
  const q = document.getElementById('search-cmd').value.toLowerCase();
  const canal = document.getElementById('filter-canal').value;
  const statut = document.getElementById('filter-statut').value;
  renderCmd(allCmd.filter(c =>
    (!q || ((c.client_nom||'') + (c.point_vente_nom||'')).toLowerCase().includes(q)) &&
    (!canal || c.canal === canal) &&
    (!statut || c.statut === statut)
  ));
}
function renderCmd(data) {
  const e = LABO.escape;
  document.getElementById('cmd-body').innerHTML = data.length ? data.map(c => `
    <tr style="cursor:pointer" onclick="if(event.target.closest('button'))return; openDetail(${c.id})">
      <td>#${c.id}</td>
      <td><span class="badge ${canalBadge[c.canal]}">${canalLabels[c.canal]}</span></td>
      <td>${e(c.client_nom || c.point_vente_nom || '—')}</td>
      <td>${e(c.type)}</td>
      <td>${LABO.formatDate(c.date_commande)}</td>
      <td>${LABO.formatDate(c.date_livraison_prevue)}</td>
      <td class="num">${LABO.formatCurrency(c.montant_total)}</td>
      <td><span class="badge ${statutBadge[c.statut]}">${statutLabels[c.statut]}</span></td>
      <td>
        ${c.statut === 'brouillon' ? `<button onclick="editCmdById(${c.id})" class="btn btn-outline btn-sm">✏️</button>
        <button onclick="setStatut(${c.id},'confirmee')" class="btn btn-teal btn-sm">✓</button>
        <button onclick="delCmd(${c.id})" class="btn btn-danger btn-sm">🗑</button>` : ''}
      </td>
    </tr>`).join('') : '<tr><td colspan="9" style="text-align:center;padding:30px;color:var(--text-muted)">Aucune commande</td></tr>';
}

function onCanalChange() {
  const canal = document.getElementById('cmd-canal').value;
  document.getElementById('wrap-client').style.display = canal === 'terme' ? '' : 'none';
  document.getElementById('wrap-franchise').style.display = canal === 'franchise' ? '' : 'none';
  document.getElementById('wrap-pv').style.display = canal === 'point_vente' ? '' : 'none';
}

function addLigne(produitId = '', quantite = 1, prix = null) {
  const tr = document.createElement('tr');
  const options = allProduits.map(p => `<option value="${p.id}" data-prix="${p.prix_vente}">${LABO.escape(p.nom)}</option>`).join('');
  tr.innerHTML = `
    <td><select class="ligne-produit" onchange="onLigneProduitChange(this)">${options}</select></td>
    <td><input type="number" step="0.001" class="ligne-qte" value="${quantite}" style="width:90px" oninput="recalcTotal()"></td>
    <td><input type="number" step="0.001" class="ligne-prix" style="width:100px" oninput="recalcTotal()"></td>
    <td class="ligne-total num">—</td>
    <td><button class="btn btn-danger btn-sm" onclick="this.closest('tr').remove(); recalcTotal()">🗑</button></td>`;
  document.getElementById('lignes-body').appendChild(tr);
  const sel = tr.querySelector('.ligne-produit');
  if (produitId) sel.value = produitId;
  const prixInput = tr.querySelector('.ligne-prix');
  prixInput.value = prix !== null ? prix : (sel.selectedOptions[0]?.dataset.prix || 0);
  recalcTotal();
}
function onLigneProduitChange(sel) {
  const tr = sel.closest('tr');
  tr.querySelector('.ligne-prix').value = sel.selectedOptions[0]?.dataset.prix || 0;
  recalcTotal();
}
function recalcTotal() {
  let total = 0;
  document.querySelectorAll('#lignes-body tr').forEach(tr => {
    const qte = parseFloat(tr.querySelector('.ligne-qte').value) || 0;
    const prix = parseFloat(tr.querySelector('.ligne-prix').value) || 0;
    const lineTotal = qte * prix;
    tr.querySelector('.ligne-total').textContent = LABO.formatCurrency(lineTotal);
    total += lineTotal;
  });
  document.getElementById('cmd-total').textContent = 'Total : ' + LABO.formatCurrency(total);
}

function openAddCmd() {
  document.getElementById('modal-cmd-title').textContent = 'Nouvelle commande';
  document.getElementById('cmd-id').value = '';
  document.getElementById('cmd-canal').value = 'terme';
  document.getElementById('cmd-type').value = 'ponctuelle';
  document.getElementById('cmd-date').value = new Date().toISOString().slice(0,10);
  document.getElementById('cmd-date-liv').value = '';
  document.getElementById('cmd-notes').value = '';
  document.getElementById('lignes-body').innerHTML = '';
  onCanalChange();
  addLigne();
  LABO.openModal('modal-cmd');
}

async function editCmdById(id) {
  const cmd = await LABO.api('cmd_get', { id });
  if (cmd.error) { LABO.toast(cmd.error, 'error'); return; }
  document.getElementById('modal-cmd-title').textContent = 'Modifier commande #' + cmd.id;
  document.getElementById('cmd-id').value = cmd.id;
  document.getElementById('cmd-canal').value = cmd.canal;
  document.getElementById('cmd-type').value = cmd.type;
  document.getElementById('cmd-date').value = cmd.date_commande;
  document.getElementById('cmd-date-liv').value = cmd.date_livraison_prevue || '';
  document.getElementById('cmd-notes').value = cmd.notes || '';
  onCanalChange();
  if (cmd.canal === 'terme') document.getElementById('cmd-client').value = cmd.client_id;
  if (cmd.canal === 'franchise') document.getElementById('cmd-franchise').value = cmd.client_id;
  if (cmd.canal === 'point_vente') document.getElementById('cmd-pv').value = cmd.point_vente_id;
  document.getElementById('lignes-body').innerHTML = '';
  cmd.lignes.forEach(l => addLigne(l.produit_id, l.quantite, l.prix_unitaire));
  LABO.openModal('modal-cmd');
}

async function saveCmd() {
  const canal = document.getElementById('cmd-canal').value;
  const lignes = [...document.querySelectorAll('#lignes-body tr')].map(tr => ({
    produit_id: tr.querySelector('.ligne-produit').value,
    quantite: tr.querySelector('.ligne-qte').value,
    prix_unitaire: tr.querySelector('.ligne-prix').value
  }));
  if (!lignes.length) { LABO.toast('Ajoutez au moins une ligne', 'error'); return; }
  const payload = {
    id: document.getElementById('cmd-id').value,
    canal,
    type: document.getElementById('cmd-type').value,
    date_commande: document.getElementById('cmd-date').value,
    date_livraison_prevue: document.getElementById('cmd-date-liv').value || null,
    notes: document.getElementById('cmd-notes').value,
    lignes
  };
  if (canal === 'terme') payload.client_id = document.getElementById('cmd-client').value;
  if (canal === 'franchise') payload.client_id = document.getElementById('cmd-franchise').value;
  if (canal === 'point_vente') payload.point_vente_id = document.getElementById('cmd-pv').value;

  const r = await LABO.api('cmd_save', payload);
  if (r.ok) { LABO.closeModal('modal-cmd'); LABO.toast('Enregistré ✓'); loadCmd(); }
  else LABO.toast(r.error || 'Erreur', 'error');
}

async function openDetail(id) {
  const cmd = await LABO.api('cmd_get', { id });
  if (cmd.error) { LABO.toast(cmd.error, 'error'); return; }
  const e = LABO.escape;
  const total = cmd.lignes.reduce((s, l) => s + parseFloat(l.quantite) * parseFloat(l.prix_unitaire), 0);
  document.getElementById('detail-title').textContent = 'Commande #' + cmd.id;
  document.getElementById('detail-body').innerHTML = `
    <div class="form-grid" style="margin-bottom:16px">
      <div class="form-group"><label>Canal</label><div><span class="badge ${canalBadge[cmd.canal]}">${canalLabels[cmd.canal]}</span></div></div>
      <div class="form-group"><label>Destination</label><div>${e(cmd.destination) || '—'}</div></div>
      <div class="form-group"><label>Statut</label><div><span class="badge ${statutBadge[cmd.statut]}">${statutLabels[cmd.statut]}</span></div></div>
      <div class="form-group"><label>Type</label><div>${e(cmd.type)}</div></div>
      <div class="form-group"><label>Date commande</label><div>${LABO.formatDate(cmd.date_commande)}</div></div>
      <div class="form-group"><label>Livraison prévue</label><div>${LABO.formatDate(cmd.date_livraison_prevue)}</div></div>
    </div>
    <div class="table-wrap">
      <table><thead><tr><th>Produit</th><th>Quantité</th><th>Prix unitaire</th><th>Total</th></tr></thead>
      <tbody>${cmd.lignes.map(l => `<tr><td>${e(l.produit_nom)}</td><td class="num">${l.quantite}</td><td class="num">${LABO.formatCurrency(l.prix_unitaire)}</td><td class="num">${LABO.formatCurrency(l.quantite*l.prix_unitaire)}</td></tr>`).join('')}</tbody></table>
    </div>
    <div style="text-align:right;margin-top:10px;font-weight:700">Total : ${LABO.formatCurrency(total)}</div>
    ${cmd.notes ? `<div class="alert-box info" style="margin-top:16px">${e(cmd.notes)}</div>` : ''}
    ${cmd.statut === 'confirmee' ? `<div class="alert-box gold" style="margin-top:16px">Cette commande sera incluse dans le prochain ordre de fabrication généré depuis le module Production.</div>` : ''}
  `;
  LABO.openModal('modal-detail');
}

async function setStatut(id, statut) {
  const r = await LABO.api('cmd_set_statut', { id, statut });
  if (r.ok) { LABO.toast('Statut mis à jour ✓'); loadCmd(); } else LABO.toast(r.error || 'Erreur', 'error');
}
async function delCmd(id) {
  if (!LABO.confirmDelete()) return;
  const r = await LABO.api('cmd_delete', { id });
  if (r.ok) { LABO.toast('Supprimé'); loadCmd(); } else LABO.toast(r.error || 'Erreur', 'error');
}

document.getElementById('search-cmd').addEventListener('input', applyFiltersCmd);
document.getElementById('filter-canal').addEventListener('change', applyFiltersCmd);
document.getElementById('filter-statut').addEventListener('change', applyFiltersCmd);

(async function () {
  await loadRefs();
  await loadCmd();
})();
</script>
<?php require_once '../includes/footer.php'; ?>
