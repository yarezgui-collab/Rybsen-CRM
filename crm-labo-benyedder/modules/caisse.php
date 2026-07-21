<?php
require_once '../config.php';
requireRole(['point_vente']);
$pageTitle = 'Caisse';
$activePage = 'caisse';
require_once '../includes/header.php';
?>
<div class="section-card">
  <div class="section-header"><div class="section-title">💰 Encaisser une commande</div></div>
  <div style="padding:20px 24px">
    <div class="form-grid">
      <div class="form-group full"><label>Client</label>
        <select id="vente-client" onchange="onClientChange()">
          <option value="">Client de passage (comptant)</option>
        </select>
        <div id="client-info" style="margin-top:6px;font-size:13px;color:var(--text-muted)"></div>
      </div>
    </div>

    <div class="form-grid" style="align-items:end">
      <div class="form-group full"><label>Ajouter un produit</label><select id="vente-produit" onchange="onProduitChange()"></select></div>
      <div class="form-group"><label>Quantité</label><input type="number" step="1" min="1" id="vente-qte" value="1"></div>
      <div class="form-group"><label>&nbsp;</label><button class="btn btn-outline" onclick="ajouterLigne()">+ Ajouter au panier</button></div>
    </div>

    <div class="table-wrap" style="margin-top:10px">
      <table>
        <thead><tr><th>Produit</th><th>Qté</th><th>Prix</th><th>Total</th><th></th></tr></thead>
        <tbody id="panier-body"><tr><td colspan="5" style="text-align:center;padding:20px;color:var(--text-muted)">Panier vide</td></tr></tbody>
      </table>
    </div>

    <div style="text-align:right;margin-top:14px;font-size:20px;font-weight:700" id="panier-total">Total : —</div>
    <div id="mode-info" style="text-align:right;font-size:13px;color:var(--text-muted);margin-top:4px"></div>
    <div style="text-align:right;margin-top:14px">
      <button class="btn btn-primary" onclick="encaisser()" id="btn-encaisser">Valider la commande</button>
    </div>
  </div>
</div>

<div class="section-card">
  <div class="section-header"><div class="section-title">Historique des ventes</div></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Numéro</th><th>Date</th><th>Montant TTC</th><th>Statut</th></tr></thead>
      <tbody id="ventes-body"><tr><td colspan="4" style="text-align:center;padding:30px;color:var(--text-muted)">Chargement...</td></tr></tbody>
    </table>
  </div>
</div>

<script>
let allProduits = [], allClients = [], panier = [];

async function loadRefs() {
  allProduits = await LABO.api('prod_list');
  document.getElementById('vente-produit').innerHTML = allProduits.map(p => `<option value="${p.id}" data-prix="${p.prix_vente}">${LABO.escape(p.nom)} — ${LABO.formatCurrency(p.prix_vente)}</option>`).join('');
  allClients = await LABO.api('clients_pv_list');
  if (Array.isArray(allClients)) {
    document.getElementById('vente-client').innerHTML = '<option value="">Client de passage (comptant)</option>' +
      allClients.map(c => `<option value="${c.id}">${LABO.escape(c.nom)}</option>`).join('');
  }
  onClientChange();
}
function selectedClient() {
  const id = document.getElementById('vente-client').value;
  return id ? allClients.find(c => String(c.id) === String(id)) : null;
}
function onClientChange() {
  const c = selectedClient();
  const info = document.getElementById('client-info');
  if (!c) { info.textContent = 'Vente au comptant, encaissée immédiatement.'; }
  else if (c.mode_paiement_defaut === 'terme') {
    info.innerHTML = `<span class="badge badge-gold">À terme ${c.delai_paiement_jours}j</span> facturé à crédit${parseFloat(c.remise_pct)>0?' · remise '+c.remise_pct+'%':''}`;
  } else {
    info.innerHTML = `<span class="badge badge-green">Comptant</span>${parseFloat(c.remise_pct)>0?' · remise '+c.remise_pct+'%':''}`;
  }
  updateTotal();
}
function onProduitChange() {}
function ajouterLigne() {
  const sel = document.getElementById('vente-produit');
  const id = parseInt(sel.value);
  const qte = parseInt(document.getElementById('vente-qte').value) || 0;
  if (!id || qte < 1) { LABO.toast('Quantité invalide', 'error'); return; }
  const p = allProduits.find(x => x.id === id);
  const ex = panier.find(l => l.produit_id === id);
  if (ex) ex.quantite += qte;
  else panier.push({ produit_id: id, nom: p.nom, prix: parseFloat(p.prix_vente), quantite: qte });
  document.getElementById('vente-qte').value = 1;
  renderPanier();
}
function retirer(id) { panier = panier.filter(l => l.produit_id !== id); renderPanier(); }
function renderPanier() {
  const e = LABO.escape;
  document.getElementById('panier-body').innerHTML = panier.length ? panier.map(l => `
    <tr><td>${e(l.nom)}</td><td class="num">${l.quantite}</td><td class="num">${LABO.formatCurrency(l.prix)}</td>
    <td class="num">${LABO.formatCurrency(l.prix*l.quantite)}</td>
    <td><button class="btn btn-danger btn-sm" onclick="retirer(${l.produit_id})">✕</button></td></tr>`).join('')
    : '<tr><td colspan="5" style="text-align:center;padding:20px;color:var(--text-muted)">Panier vide</td></tr>';
  updateTotal();
}
function updateTotal() {
  const c = selectedClient();
  const remise = c ? parseFloat(c.remise_pct) || 0 : 0;
  let ht = panier.reduce((s,l) => s + l.prix*l.quantite, 0);
  if (remise > 0) ht = ht * (1 - remise/100);
  const ttc = ht * 1.19;
  document.getElementById('panier-total').textContent = 'Total TTC : ' + LABO.formatCurrency(ttc);
  const credit = c && c.mode_paiement_defaut === 'terme';
  document.getElementById('mode-info').textContent = credit ? 'Sera facturé à terme (crédit)' : 'Encaissement comptant';
  document.getElementById('btn-encaisser').textContent = credit ? 'Valider (à crédit)' : 'Encaisser (comptant)';
}

function encaisser() {
  if (!panier.length) { LABO.toast('Le panier est vide', 'error'); return; }
  const clientId = document.getElementById('vente-client').value || null;
  const c = selectedClient();
  const credit = c && c.mode_paiement_defaut === 'terme';
  let ht = panier.reduce((s,l) => s + l.prix*l.quantite, 0);
  if (c && parseFloat(c.remise_pct) > 0) ht = ht * (1 - parseFloat(c.remise_pct)/100);
  OfflineCaisse.enqueueSale({
    client_id: clientId,
    lignes: panier.map(l => ({ produit_id: l.produit_id, quantite: l.quantite })),
    _montant: ht * 1.19
  });
  if (navigator.onLine) LABO.toast(credit ? 'Commande enregistrée à crédit ✓' : 'Vente encaissée ✓');
  else LABO.toast('Enregistré hors-ligne — synchronisation au retour du réseau', 'info');
  panier = []; renderPanier();
  setTimeout(loadVentes, 400);
}

function renderPending() {
  const q = (function(){ try { return JSON.parse(localStorage.getItem('benyedder_ventes_queue')||'[]'); } catch(e){ return []; } })();
  return q.map(v => `<tr style="opacity:.7">
    <td><strong>— local —</strong></td>
    <td>${LABO.formatDate(new Date(v._ts).toISOString().slice(0,10))}</td>
    <td class="num">${v._montant ? LABO.formatCurrency(v._montant) : '—'}</td>
    <td><span class="badge badge-gold">⏳ À synchroniser</span></td></tr>`).join('');
}
async function loadVentes() {
  const e = LABO.escape;
  const statutLabels = { emise: 'À terme', payee: 'Payée', partiellement_payee: 'Partielle', impayee: 'Impayée' };
  const statutBadge = { emise: 'badge-gold', payee: 'badge-green', partiellement_payee: 'badge-navy', impayee: 'badge-red' };
  let serverRows = [];
  try { const r = await LABO.api('mes_ventes_list'); if (Array.isArray(r)) serverRows = r; } catch (err) {}
  const server = serverRows.map(f => `
    <tr><td><strong>${e(f.numero)}</strong></td><td>${LABO.formatDate(f.date_emission)}</td><td class="num">${LABO.formatCurrency(f.montant_ttc)}</td><td><span class="badge ${statutBadge[f.statut]||'badge-grey'}">${statutLabels[f.statut]||e(f.statut)}</span></td></tr>`).join('');
  document.getElementById('ventes-body').innerHTML = (renderPending() + server) || '<tr><td colspan="4" style="text-align:center;padding:30px;color:var(--text-muted)">Aucune vente pour le moment</td></tr>';
}
if (window.OfflineCaisse) OfflineCaisse.onChange(function(){ loadVentes(); });

(async function () { await loadRefs(); await loadVentes(); })();
</script>
<?php require_once '../includes/footer.php'; ?>
