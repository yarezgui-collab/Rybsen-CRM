<?php
require_once '../config.php';
requireRole(['admin','labo']);
$pageTitle = 'Factures & paiements';
$activePage = 'facturation';
require_once '../includes/header.php';
?>
<div class="section-card">
  <div class="section-header"><div class="section-title">📋 Commandes livrées à facturer</div></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>#</th><th>Canal</th><th>Destination</th><th>Montant HT</th><th>Action</th></tr></thead>
      <tbody id="candidats-body"><tr><td colspan="5" style="text-align:center;padding:20px;color:var(--text-muted)">Chargement...</td></tr></tbody>
    </table>
  </div>
</div>

<div class="section-card">
  <div class="section-header"><div class="section-title">🧾 Factures</div></div>
  <div class="filters-bar">
    <select id="filter-statut">
      <option value="">Tous les statuts</option>
      <option value="emise">Émise</option>
      <option value="partiellement_payee">Partiellement payée</option>
      <option value="payee">Payée</option>
      <option value="impayee">Impayée</option>
    </select>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Numéro</th><th>Client / PDV</th><th>Mode</th><th>Montant TTC</th><th>Payé</th><th>Échéance</th><th>Statut</th><th></th></tr></thead>
      <tbody id="fact-body"><tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-muted)">Chargement...</td></tr></tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="modal-detail">
  <div class="modal" style="max-width:700px">
    <div class="modal-header"><div class="modal-title" id="detail-title">Facture</div><button class="modal-close" onclick="LABO.closeModal('modal-detail')">✕</button></div>
    <div class="modal-body" id="detail-body"></div>
    <div class="modal-footer" id="detail-footer"></div>
  </div>
</div>

<div class="modal-overlay" id="modal-paiement">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Enregistrer un paiement</div><button class="modal-close" onclick="LABO.closeModal('modal-paiement')">✕</button></div>
    <div class="modal-body">
      <input type="hidden" id="pai-facture-id">
      <div class="form-grid">
        <div class="form-group"><label>Montant</label><input type="number" step="0.001" id="pai-montant"></div>
        <div class="form-group"><label>Mode</label>
          <select id="pai-mode"><option value="especes">Espèces</option><option value="carte">Carte</option><option value="cheque">Chèque</option><option value="virement">Virement</option></select>
        </div>
        <div class="form-group"><label>Date</label><input type="date" id="pai-date"></div>
        <div class="form-group"><label>Référence</label><input type="text" id="pai-ref"></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="LABO.closeModal('modal-paiement')">Annuler</button>
      <button class="btn btn-primary" onclick="enregistrerPaiement()">Enregistrer</button>
    </div>
  </div>
</div>

<script>
const canalLabels = { terme: 'Client à terme', franchise: 'Franchise', point_vente: 'Point de vente' };
const modeLabels = { comptant: 'Comptant', terme: 'Terme' };
const statutLabels = { brouillon: 'Brouillon', emise: 'Émise', partiellement_payee: 'Partiellement payée', payee: 'Payée', impayee: 'Impayée' };
const statutBadge = { brouillon: 'badge-grey', emise: 'badge-navy', partiellement_payee: 'badge-gold', payee: 'badge-green', impayee: 'badge-red' };
let allFact = [];

async function loadCandidats() {
  const rows = await LABO.api('fact_candidats');
  const e = LABO.escape;
  document.getElementById('candidats-body').innerHTML = rows.length ? rows.map(c => `
    <tr>
      <td>#${c.id}</td>
      <td>${canalLabels[c.canal]}</td>
      <td>${e(c.client_nom || c.point_vente_nom || '—')}</td>
      <td class="num">${LABO.formatCurrency(c.montant_total)}</td>
      <td><button class="btn btn-primary btn-sm" onclick="creerFacture(${c.id})">Facturer</button></td>
    </tr>`).join('') : '<tr><td colspan="5" style="text-align:center;padding:20px;color:var(--text-muted)">Aucune commande livrée en attente de facturation</td></tr>';
}
async function creerFacture(commandeId) {
  const r = await LABO.api('fact_creer', { commande_id: commandeId });
  if (r.ok) { LABO.toast('Facture ' + r.numero + ' créée ✓'); loadCandidats(); loadFact(); }
  else LABO.toast(r.error || 'Erreur', 'error');
}

async function loadFact() {
  allFact = await LABO.api('fact_list');
  applyFilters();
}
function applyFilters() {
  const s = document.getElementById('filter-statut').value;
  renderFact(allFact.filter(f => !s || f.statut === s));
}
function renderFact(data) {
  const e = LABO.escape;
  document.getElementById('fact-body').innerHTML = data.length ? data.map(f => `
    <tr style="cursor:pointer" onclick="openDetail(${f.id})">
      <td><strong>${e(f.numero)}</strong></td>
      <td>${e(f.client_nom || f.point_vente_nom || '—')}</td>
      <td><span class="badge badge-navy">${modeLabels[f.mode_paiement]}</span></td>
      <td class="num">${LABO.formatCurrency(f.montant_ttc)}</td>
      <td class="num">${LABO.formatCurrency(f.montant_paye)}</td>
      <td>${LABO.formatDate(f.date_echeance)}</td>
      <td><span class="badge ${statutBadge[f.statut]}">${statutLabels[f.statut]}</span></td>
      <td></td>
    </tr>`).join('') : '<tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-muted)">Aucune facture</td></tr>';
}

async function openDetail(id) {
  const f = await LABO.api('fact_get', { id });
  if (f.error) { LABO.toast(f.error, 'error'); return; }
  const e = LABO.escape;
  const reste = f.montant_ttc - f.paiements.reduce((s,p) => s + parseFloat(p.montant), 0);
  document.getElementById('detail-title').textContent = 'Facture ' + f.numero;
  document.getElementById('detail-body').innerHTML = `
    <div class="form-grid" style="margin-bottom:16px">
      <div class="form-group"><label>Client / PDV</label><div>${e(f.client_nom || f.point_vente_nom || '—')}</div></div>
      <div class="form-group"><label>Mode</label><div><span class="badge badge-navy">${modeLabels[f.mode_paiement]}</span></div></div>
      <div class="form-group"><label>Statut</label><div><span class="badge ${statutBadge[f.statut]}">${statutLabels[f.statut]}</span></div></div>
      <div class="form-group"><label>Échéance</label><div>${LABO.formatDate(f.date_echeance)}</div></div>
    </div>
    ${f.lignes.length ? `<div class="table-wrap"><table><thead><tr><th>Produit</th><th>Qté</th><th>PU</th><th>Total</th></tr></thead>
      <tbody>${f.lignes.map(l => `<tr><td>${e(l.produit_nom)}</td><td class="num">${l.quantite}</td><td class="num">${LABO.formatCurrency(l.prix_unitaire)}</td><td class="num">${LABO.formatCurrency(l.quantite*l.prix_unitaire)}</td></tr>`).join('')}</tbody></table></div>` : ''}
    <div class="kpi-grid" style="margin-top:16px;margin-bottom:0">
      <div class="kpi-card"><div class="kpi-label">Montant HT</div><div class="kpi-value" style="font-size:18px">${LABO.formatCurrency(f.montant_ht)}</div></div>
      <div class="kpi-card teal"><div class="kpi-label">Montant TTC</div><div class="kpi-value" style="font-size:18px">${LABO.formatCurrency(f.montant_ttc)}</div></div>
      <div class="kpi-card ${reste <= 0.001 ? '' : 'red'}"><div class="kpi-label">Reste à payer</div><div class="kpi-value" style="font-size:18px">${LABO.formatCurrency(Math.max(0,reste))}</div></div>
    </div>
    ${f.paiements.length ? `<div style="margin-top:16px"><strong>Paiements :</strong>
      <div class="table-wrap"><table><thead><tr><th>Date</th><th>Montant</th><th>Mode</th><th></th></tr></thead>
      <tbody>${f.paiements.map(p => `<tr><td>${LABO.formatDate(p.date_paiement)}</td><td class="num">${LABO.formatCurrency(p.montant)}</td><td>${e(p.mode)}</td><td><button class="btn btn-danger btn-sm" onclick="delPaiement(${p.id}, ${f.id})">🗑</button></td></tr>`).join('')}</tbody></table></div>
    </div>` : ''}
  `;
  document.getElementById('detail-footer').innerHTML = reste > 0.001
    ? `<button class="btn btn-primary" onclick="ouvrirPaiement(${f.id}, ${reste})">+ Enregistrer un paiement</button>` : '';
  LABO.openModal('modal-detail');
}

function ouvrirPaiement(factureId, reste) {
  document.getElementById('pai-facture-id').value = factureId;
  document.getElementById('pai-montant').value = reste.toFixed(3);
  document.getElementById('pai-date').value = new Date().toISOString().slice(0,10);
  document.getElementById('pai-ref').value = '';
  LABO.openModal('modal-paiement');
}
async function enregistrerPaiement() {
  const factureId = document.getElementById('pai-facture-id').value;
  const r = await LABO.api('paiement_save', {
    facture_id: factureId,
    montant: document.getElementById('pai-montant').value,
    mode: document.getElementById('pai-mode').value,
    date_paiement: document.getElementById('pai-date').value,
    reference: document.getElementById('pai-ref').value
  });
  if (r.ok) { LABO.closeModal('modal-paiement'); LABO.toast('Paiement enregistré ✓'); loadFact(); openDetail(factureId); }
  else LABO.toast(r.error || 'Erreur', 'error');
}
async function delPaiement(id, factureId) {
  if (!LABO.confirmDelete()) return;
  const r = await LABO.api('paiement_delete', { id });
  if (r.ok) { LABO.toast('Paiement supprimé'); loadFact(); openDetail(factureId); }
}

document.getElementById('filter-statut').addEventListener('change', applyFilters);
loadCandidats();
loadFact();
</script>
<?php require_once '../includes/footer.php'; ?>
