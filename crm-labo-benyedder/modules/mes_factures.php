<?php
require_once '../config.php';
requireRole(['franchise','client_terme','point_vente']);
$user = currentUser();
$peutDeclarer = in_array($user['role'], ['franchise','client_terme'], true);
$pageTitle = 'Mes factures';
$activePage = 'mes_factures';
require_once '../includes/header.php';
?>
<div class="section-card">
  <div class="section-header"><div class="section-title">🧾 Mes factures</div></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Numéro</th><th>Mode</th><th>Montant TTC</th><th>Payé</th><th>Échéance</th><th>Statut</th></tr></thead>
      <tbody id="fact-body"><tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted)">Chargement...</td></tr></tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="modal-detail">
  <div class="modal" style="max-width:650px">
    <div class="modal-header"><div class="modal-title" id="detail-title">Facture</div><button class="modal-close" onclick="LABO.closeModal('modal-detail')">✕</button></div>
    <div class="modal-body" id="detail-body"></div>
    <div class="modal-footer" id="detail-footer"></div>
  </div>
</div>

<?php if ($peutDeclarer): ?>
<div class="modal-overlay" id="modal-declarer">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Déclarer un paiement</div><button class="modal-close" onclick="LABO.closeModal('modal-declarer')">✕</button></div>
    <div class="modal-body">
      <div class="alert-box info">Votre déclaration sera vérifiée par l'équipe Ben Yedder avant d'être prise en compte dans votre solde.</div>
      <input type="hidden" id="decl-facture-id">
      <div class="form-grid">
        <div class="form-group"><label>Montant</label><input type="number" step="0.001" id="decl-montant"></div>
        <div class="form-group"><label>Mode</label>
          <select id="decl-mode"><option value="virement">Virement</option><option value="cheque">Chèque</option><option value="especes">Espèces</option><option value="carte">Carte</option></select>
        </div>
        <div class="form-group"><label>Date</label><input type="date" id="decl-date"></div>
        <div class="form-group"><label>Référence</label><input type="text" id="decl-ref" placeholder="N° de virement/chèque"></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="LABO.closeModal('modal-declarer')">Annuler</button>
      <button class="btn btn-primary" onclick="declarerPaiement()">Envoyer la déclaration</button>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
const peutDeclarer = <?= $peutDeclarer ? 'true' : 'false' ?>;
const modeLabels = { comptant: 'Comptant', terme: 'Terme' };
const statutLabels = { brouillon: 'Brouillon', emise: 'Émise', partiellement_payee: 'Partiellement payée', payee: 'Payée', impayee: 'Impayée' };
const statutBadge = { brouillon: 'badge-grey', emise: 'badge-navy', partiellement_payee: 'badge-gold', payee: 'badge-green', impayee: 'badge-red' };
const declStatutLabels = { en_attente: 'En attente de validation', validee: 'Validée', rejetee: 'Rejetée' };
const declStatutBadge = { en_attente: 'badge-gold', validee: 'badge-green', rejetee: 'badge-red' };

async function loadFact() {
  const rows = await LABO.api('mes_fact_list');
  const e = LABO.escape;
  document.getElementById('fact-body').innerHTML = rows.length ? rows.map(f => `
    <tr style="cursor:pointer" onclick="openDetail(${f.id})">
      <td><strong>${e(f.numero)}</strong></td>
      <td><span class="badge badge-navy">${modeLabels[f.mode_paiement]}</span></td>
      <td class="num">${LABO.formatCurrency(f.montant_ttc)}</td>
      <td class="num">${LABO.formatCurrency(f.montant_paye)}</td>
      <td>${LABO.formatDate(f.date_echeance)}</td>
      <td><span class="badge ${statutBadge[f.statut]}">${statutLabels[f.statut]}</span></td>
    </tr>`).join('') : '<tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted)">Aucune facture pour le moment</td></tr>';
}

async function openDetail(id) {
  const f = await LABO.api('mes_fact_get', { id });
  if (f.error) { LABO.toast(f.error, 'error'); return; }
  const e = LABO.escape;
  const reste = f.montant_ttc - f.paiements.reduce((s,p) => s + parseFloat(p.montant), 0);
  document.getElementById('detail-title').textContent = 'Facture ' + f.numero;
  document.getElementById('detail-body').innerHTML = `
    <div class="form-grid" style="margin-bottom:16px">
      <div class="form-group"><label>Mode</label><div><span class="badge badge-navy">${modeLabels[f.mode_paiement]}</span></div></div>
      <div class="form-group"><label>Statut</label><div><span class="badge ${statutBadge[f.statut]}">${statutLabels[f.statut]}</span></div></div>
      <div class="form-group"><label>Émise le</label><div>${LABO.formatDate(f.date_emission)}</div></div>
      <div class="form-group"><label>Échéance</label><div>${LABO.formatDate(f.date_echeance)}</div></div>
    </div>
    ${f.lignes.length ? `<div class="table-wrap"><table><thead><tr><th>Produit</th><th>Qté</th><th>Total</th></tr></thead>
      <tbody>${f.lignes.map(l => `<tr><td>${e(l.produit_nom)}</td><td class="num">${l.quantite}</td><td class="num">${LABO.formatCurrency(l.quantite*l.prix_unitaire)}</td></tr>`).join('')}</tbody></table></div>` : ''}
    <div class="kpi-grid" style="margin-top:16px;margin-bottom:0">
      <div class="kpi-card"><div class="kpi-label">Montant TTC</div><div class="kpi-value" style="font-size:18px">${LABO.formatCurrency(f.montant_ttc)}</div></div>
      <div class="kpi-card ${reste <= 0.001 ? '' : 'red'}"><div class="kpi-label">Reste à payer</div><div class="kpi-value" style="font-size:18px">${LABO.formatCurrency(Math.max(0,reste))}</div></div>
    </div>
    ${f.declarations.length ? `<div style="margin-top:16px"><strong>Mes déclarations :</strong>
      <div class="table-wrap"><table><thead><tr><th>Date</th><th>Montant</th><th>Statut</th></tr></thead>
      <tbody>${f.declarations.map(d => `<tr><td>${LABO.formatDate(d.date_declaration)}</td><td class="num">${LABO.formatCurrency(d.montant)}</td><td><span class="badge ${declStatutBadge[d.statut]}">${declStatutLabels[d.statut]}</span></td></tr>`).join('')}</tbody></table></div>
    </div>` : ''}
  `;
  document.getElementById('detail-footer').innerHTML = (peutDeclarer && reste > 0.001)
    ? `<button class="btn btn-primary" onclick="ouvrirDeclaration(${f.id}, ${reste})">Déclarer un paiement</button>` : '';
  LABO.openModal('modal-detail');
}

function ouvrirDeclaration(factureId, reste) {
  document.getElementById('decl-facture-id').value = factureId;
  document.getElementById('decl-montant').value = reste.toFixed(3);
  document.getElementById('decl-date').value = new Date().toISOString().slice(0,10);
  document.getElementById('decl-ref').value = '';
  LABO.openModal('modal-declarer');
}
async function declarerPaiement() {
  const r = await LABO.api('paiement_declarer', {
    facture_id: document.getElementById('decl-facture-id').value,
    montant: document.getElementById('decl-montant').value,
    mode: document.getElementById('decl-mode').value,
    date_declaration: document.getElementById('decl-date').value,
    reference: document.getElementById('decl-ref').value
  });
  if (r.ok) { LABO.closeModal('modal-declarer'); LABO.toast('Déclaration envoyée — en attente de validation'); loadFact(); LABO.closeModal('modal-detail'); }
  else LABO.toast(r.error || 'Erreur', 'error');
}

loadFact();
</script>
<?php require_once '../includes/footer.php'; ?>
