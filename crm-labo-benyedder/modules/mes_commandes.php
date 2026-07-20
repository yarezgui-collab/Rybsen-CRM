<?php
require_once '../config.php';
requireRole(['franchise','client_terme','point_vente']);
$user = currentUser();
$pageTitle = $user['role'] === 'point_vente' ? 'Réapprovisionnement' : 'Mes commandes';
$activePage = 'mes_commandes';
require_once '../includes/header.php';
?>
<div class="section-card">
  <div class="section-header">
    <div class="section-title">📦 <?= htmlspecialchars($pageTitle) ?></div>
    <div class="section-actions"><button class="btn btn-primary" onclick="openAddCmd()">+ Nouvelle commande</button></div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>#</th><th>Date commande</th><th>Livraison prévue</th><th>Montant</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody id="cmd-body"><tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted)">Chargement...</td></tr></tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="modal-detail">
  <div class="modal" style="max-width:650px">
    <div class="modal-header"><div class="modal-title" id="detail-title">Commande</div><button class="modal-close" onclick="LABO.closeModal('modal-detail')">✕</button></div>
    <div class="modal-body" id="detail-body"></div>
  </div>
</div>

<div class="modal-overlay" id="modal-cmd">
  <div class="modal" style="max-width:650px">
    <div class="modal-header">
      <div class="modal-title" id="modal-cmd-title">Nouvelle commande</div>
      <button class="modal-close" onclick="LABO.closeModal('modal-cmd')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="cmd-id">
      <div class="form-grid">
        <input type="hidden" id="cmd-type" value="ponctuelle">
        <div class="form-group"><label>Date de commande</label><input type="date" id="cmd-date"></div>
        <div class="form-group"><label>Livraison souhaitée</label><input type="date" id="cmd-date-liv"></div>
        <div class="form-group full"><label>Notes</label><input type="text" id="cmd-notes"></div>
      </div>
      <div style="margin-top:20px">
        <div class="section-header" style="padding:0 0 10px;border:none">
          <div class="section-title" style="font-size:13px">Produits</div>
          <div class="section-actions"><button class="btn btn-outline btn-sm" onclick="addLigne()">+ Ligne</button></div>
        </div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Produit</th><th>Quantité</th><th>Prix</th><th>Total</th><th></th></tr></thead>
            <tbody id="lignes-body"></tbody>
          </table>
        </div>
        <div style="text-align:right;margin-top:10px;font-weight:700" id="cmd-total"></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="LABO.closeModal('modal-cmd')">Annuler</button>
      <button class="btn btn-primary" onclick="saveCmd()">Enregistrer en brouillon</button>
    </div>
  </div>
</div>

<script>
let allCmd = [], allProduits = [];
const statutLabels = { brouillon: 'Brouillon', confirmee: 'Confirmée', en_production: 'En production', livree: 'Livrée', facturee: 'Facturée', annulee: 'Annulée' };
const statutBadge = { brouillon: 'badge-grey', confirmee: 'badge-navy', en_production: 'badge-gold', livree: 'badge-teal', facturee: 'badge-green', annulee: 'badge-red' };

async function loadRefs() {
  allProduits = await LABO.api('prod_list');
}
async function loadCmd() {
  allCmd = await LABO.api('mes_cmd_list');
  renderCmd();
}
function renderCmd() {
  document.getElementById('cmd-body').innerHTML = allCmd.length ? allCmd.map(c => `
    <tr style="cursor:pointer" onclick="if(event.target.closest('button'))return; openDetail(${c.id})">
      <td>#${c.id}</td>
      <td>${LABO.formatDate(c.date_commande)}</td>
      <td>${LABO.formatDate(c.date_livraison_prevue)}</td>
      <td class="num">${LABO.formatCurrency(c.montant_total)}</td>
      <td><span class="badge ${statutBadge[c.statut]}">${statutLabels[c.statut]}</span></td>
      <td>
        ${c.statut === 'brouillon' ? `<button onclick="editCmdById(${c.id})" class="btn btn-outline btn-sm">✏️</button>
        <button onclick="setStatut(${c.id},'confirmee')" class="btn btn-teal btn-sm">Confirmer</button>
        <button onclick="setStatut(${c.id},'annulee')" class="btn btn-danger btn-sm">Annuler</button>` : ''}
      </td>
    </tr>`).join('') : '<tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted)">Aucune commande pour le moment</td></tr>';
}

function addLigne(produitId = '', quantite = 1) {
  const tr = document.createElement('tr');
  const options = allProduits.map(p => `<option value="${p.id}" data-prix="${p.prix_vente}">${LABO.escape(p.nom)}</option>`).join('');
  tr.innerHTML = `
    <td><select class="ligne-produit" onchange="recalcTotal()">${options}</select></td>
    <td><input type="number" step="1" min="1" class="ligne-qte" value="${quantite}" style="width:90px" oninput="recalcTotal()"></td>
    <td class="ligne-prix num">—</td>
    <td class="ligne-total num">—</td>
    <td><button class="btn btn-danger btn-sm" onclick="this.closest('tr').remove(); recalcTotal()">🗑</button></td>`;
  document.getElementById('lignes-body').appendChild(tr);
  if (produitId) tr.querySelector('.ligne-produit').value = produitId;
  recalcTotal();
}
function recalcTotal() {
  let total = 0;
  document.querySelectorAll('#lignes-body tr').forEach(tr => {
    const sel = tr.querySelector('.ligne-produit');
    const prix = parseFloat(sel.selectedOptions[0]?.dataset.prix || 0);
    const qte = parseFloat(tr.querySelector('.ligne-qte').value) || 0;
    const lineTotal = qte * prix;
    tr.querySelector('.ligne-prix').textContent = LABO.formatCurrency(prix);
    tr.querySelector('.ligne-total').textContent = LABO.formatCurrency(lineTotal);
    total += lineTotal;
  });
  document.getElementById('cmd-total').textContent = 'Total estimé : ' + LABO.formatCurrency(total);
}

function openAddCmd() {
  document.getElementById('modal-cmd-title').textContent = 'Nouvelle commande';
  document.getElementById('cmd-id').value = '';
  document.getElementById('cmd-type').value = 'ponctuelle';
  document.getElementById('cmd-date').value = new Date().toISOString().slice(0,10);
  document.getElementById('cmd-date-liv').value = '';
  document.getElementById('cmd-notes').value = '';
  document.getElementById('lignes-body').innerHTML = '';
  addLigne();
  LABO.openModal('modal-cmd');
}
async function editCmdById(id) {
  const cmd = await LABO.api('mes_cmd_get', { id });
  if (cmd.error) { LABO.toast(cmd.error, 'error'); return; }
  document.getElementById('modal-cmd-title').textContent = 'Modifier commande #' + cmd.id;
  document.getElementById('cmd-id').value = cmd.id;
  document.getElementById('cmd-type').value = cmd.type;
  document.getElementById('cmd-date').value = cmd.date_commande;
  document.getElementById('cmd-date-liv').value = cmd.date_livraison_prevue || '';
  document.getElementById('cmd-notes').value = cmd.notes || '';
  document.getElementById('lignes-body').innerHTML = '';
  cmd.lignes.forEach(l => addLigne(l.produit_id, l.quantite));
  LABO.openModal('modal-cmd');
}
async function saveCmd() {
  const lignes = [...document.querySelectorAll('#lignes-body tr')].map(tr => {
    const sel = tr.querySelector('.ligne-produit');
    return {
      produit_id: sel.value,
      quantite: tr.querySelector('.ligne-qte').value,
      prix_unitaire: sel.selectedOptions[0]?.dataset.prix || 0
    };
  });
  if (!lignes.length) { LABO.toast('Ajoutez au moins un produit', 'error'); return; }
  const r = await LABO.api('mes_cmd_save', {
    id: document.getElementById('cmd-id').value,
    type: document.getElementById('cmd-type').value,
    date_commande: document.getElementById('cmd-date').value,
    date_livraison_prevue: document.getElementById('cmd-date-liv').value || null,
    notes: document.getElementById('cmd-notes').value,
    lignes
  });
  if (r.ok) { LABO.closeModal('modal-cmd'); LABO.toast('Enregistré ✓'); loadCmd(); }
  else LABO.toast(r.error || 'Erreur', 'error');
}
async function openDetail(id) {
  const cmd = await LABO.api('mes_cmd_get', { id });
  if (cmd.error) { LABO.toast(cmd.error, 'error'); return; }
  const e = LABO.escape;
  const total = cmd.lignes.reduce((s, l) => s + parseFloat(l.quantite) * parseFloat(l.prix_unitaire), 0);
  document.getElementById('detail-title').textContent = 'Commande #' + cmd.id;
  document.getElementById('detail-body').innerHTML = `
    <div class="form-grid" style="margin-bottom:16px">
      <div class="form-group"><label>Statut</label><div><span class="badge ${statutBadge[cmd.statut]}">${statutLabels[cmd.statut]}</span></div></div>
      <div class="form-group"><label>Date commande</label><div>${LABO.formatDate(cmd.date_commande)}</div></div>
      <div class="form-group"><label>Livraison prévue</label><div>${LABO.formatDate(cmd.date_livraison_prevue)}</div></div>
    </div>
    <div class="table-wrap">
      <table><thead><tr><th>Produit</th><th>Quantité</th><th>Prix</th><th>Total</th></tr></thead>
      <tbody>${cmd.lignes.map(l => `<tr><td>${e(l.produit_nom)}</td><td class="num">${l.quantite}</td><td class="num">${LABO.formatCurrency(l.prix_unitaire)}</td><td class="num">${LABO.formatCurrency(l.quantite*l.prix_unitaire)}</td></tr>`).join('')}</tbody></table>
    </div>
    <div style="text-align:right;margin-top:10px;font-weight:700">Total : ${LABO.formatCurrency(total)}</div>
    ${cmd.notes ? `<div class="alert-box info" style="margin-top:16px">${e(cmd.notes)}</div>` : ''}
  `;
  LABO.openModal('modal-detail');
}
async function setStatut(id, statut) {
  if (statut === 'annulee' && !confirm('Annuler cette commande ?')) return;
  const r = await LABO.api('mes_cmd_set_statut', { id, statut });
  if (r.ok) { LABO.toast(statut === 'confirmee' ? 'Commande confirmée ✓' : 'Commande annulée'); loadCmd(); }
  else LABO.toast(r.error || 'Erreur', 'error');
}

(async function () { await loadRefs(); await loadCmd(); })();
</script>
<?php require_once '../includes/footer.php'; ?>
