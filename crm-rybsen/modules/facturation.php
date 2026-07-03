<?php
require_once '../config.php';
$pageTitle = 'Facturation';
$activePage = 'facturation';
require_once '../includes/header.php';
?>
<style>
.doc-tabs { display:flex; gap:8px; margin-bottom:20px; flex-wrap:wrap; }
.doc-tab { padding:8px 18px; border-radius:20px; border:2px solid var(--border); background:#fff; color:#666; cursor:pointer; font-size:13px; font-weight:600; transition:all .2s; }
.doc-tab.active { background:var(--navy); border-color:var(--navy); color:#fff; }
.doc-tab .tab-count { display:inline-block; background:rgba(255,255,255,.25); border-radius:10px; padding:1px 7px; font-size:11px; margin-left:5px; }
.doc-tab:not(.active) .tab-count { background:#f0f0f0; color:#888; }

.stat-row { display:flex; gap:12px; margin-bottom:20px; flex-wrap:wrap; }
.stat-pill { background:#fff; border:1px solid var(--border); border-radius:10px; padding:10px 16px; display:flex; flex-direction:column; min-width:130px; }
.stat-pill .sp-val { font-size:20px; font-weight:800; color:var(--navy); }
.stat-pill .sp-lbl { font-size:11px; color:#888; text-transform:uppercase; letter-spacing:.5px; margin-top:2px; }

.lignes-table { width:100%; border-collapse:collapse; margin-bottom:8px; }
.lignes-table th { background:#f8f9fa; font-size:11px; text-transform:uppercase; letter-spacing:.5px; color:#666; padding:8px; text-align:left; }
.lignes-table td { padding:6px 4px; border-bottom:1px solid #f0f0f0; vertical-align:top; }
.lignes-table input { width:100%; border:1px solid #e0e0e0; border-radius:6px; padding:5px 8px; font-size:13px; }
.lignes-table input:focus { outline:none; border-color:var(--teal); }
.btn-del-ligne { background:none; border:none; color:#dc2626; cursor:pointer; font-size:16px; padding:4px; }
.totals-box { background:#f8f9fa; border-radius:10px; padding:14px 18px; min-width:240px; margin-left:auto; }
.totals-box .t-row { display:flex; justify-content:space-between; padding:4px 0; font-size:14px; }
.totals-box .t-row.ttc { font-size:17px; font-weight:800; color:var(--navy); border-top:2px solid var(--navy); margin-top:6px; padding-top:8px; }
.totals-box .t-row.tva { color:#888; }

.paiements-list { margin-top:8px; }
.paiement-row { display:flex; align-items:center; gap:8px; padding:6px 0; border-bottom:1px solid #f0f0f0; font-size:13px; }
.paiement-row:last-child { border:none; }
.badge-paid { background:#dcfce7; color:#166534; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:700; }
.client-dropdown { display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #e0e0e0; border-radius:6px; box-shadow:0 4px 12px rgba(0,0,0,.12); z-index:999; max-height:200px; overflow-y:auto; }
.client-option { padding:8px 12px; cursor:pointer; font-size:13px; border-bottom:1px solid #f5f5f5; }
.client-option:last-child { border-bottom:none; }
.client-option:hover { background:#f0fbff; }
.client-option strong { color:var(--navy); }
.client-option small { color:#888; }
</style>

<div class="doc-tabs" id="doc-tabs">
  <button class="doc-tab active" data-type="">Tous <span class="tab-count" id="cnt-all">0</span></button>
  <button class="doc-tab" data-type="Devis">Devis <span class="tab-count" id="cnt-devis">0</span></button>
  <button class="doc-tab" data-type="Facture">Factures <span class="tab-count" id="cnt-fac">0</span></button>
  <button class="doc-tab" data-type="Pro forma">Pro forma <span class="tab-count" id="cnt-pf">0</span></button>
  <button class="doc-tab" data-type="Bon de livraison">BL <span class="tab-count" id="cnt-bl">0</span></button>
</div>

<div class="stat-row" id="stat-row">
  <div class="stat-pill"><span class="sp-val" id="st-ca">—</span><span class="sp-lbl">CA facturé HT</span></div>
  <div class="stat-pill"><span class="sp-val" id="st-encaisse">—</span><span class="sp-lbl">Encaissé</span></div>
  <div class="stat-pill"><span class="sp-val" id="st-reste">—</span><span class="sp-lbl">Reste à encaisser</span></div>
  <div class="stat-pill"><span class="sp-val" id="st-devis">—</span><span class="sp-lbl">Devis en attente</span></div>
</div>

<div class="section-card">
  <div class="section-header">
    <div class="section-title">🧾 Documents commerciaux</div>
    <div class="section-actions">
      <select id="filter-statut-doc" class="btn btn-outline btn-sm" style="padding:6px 10px">
        <option value="">Tous statuts</option>
        <option>Brouillon</option><option>Envoyé</option><option>Accepté</option>
        <option>Refusé</option><option>Partiellement payé</option><option>Payé</option><option>Annulé</option>
      </select>
      <button class="btn btn-primary" onclick="openAdd()">+ Nouveau document</button>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Numéro</th><th>Type</th><th>Client</th><th>Date</th><th>Total TTC</th><th>Payé</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody id="doc-body"><tr><td colspan="8" style="text-align:center;padding:30px;color:#999">Chargement...</td></tr></tbody>
    </table>
  </div>
</div>

<!-- MODAL DOCUMENT -->
<div class="modal-overlay" id="modal-doc" style="align-items:flex-start;padding:20px 0;overflow-y:auto">
  <div class="modal" style="max-width:860px;width:95%;margin:auto">
    <div class="modal-header">
      <div class="modal-title" id="modal-doc-title">Nouveau document</div>
      <button class="modal-close" onclick="RYBSEN.closeModal('modal-doc')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="doc-id">
      <div class="form-grid">
        <div class="form-group">
          <label>Type *</label>
          <select id="doc-type" onchange="handleTypeChange()">
            <option>Facture</option><option>Devis</option><option>Pro forma</option><option>Bon de livraison</option>
          </select>
        </div>
        <div class="form-group">
          <label>Numéro *</label>
          <div style="display:flex;gap:6px">
            <input type="text" id="doc-numero" placeholder="FAC-2026-001" style="flex:1">
            <button type="button" class="btn btn-outline btn-sm" onclick="fetchNumero()" title="Générer">↺</button>
          </div>
        </div>
        <div class="form-group">
          <label>Statut</label>
          <select id="doc-statut">
            <option>Brouillon</option><option>Envoyé</option><option>Accepté</option>
            <option>Refusé</option><option>Partiellement payé</option><option>Payé</option><option>Annulé</option>
          </select>
        </div>
        <div class="form-group">
          <label>Devise</label>
          <select id="doc-devise">
            <option>TND</option><option>EUR</option><option>USD</option><option>XOF</option><option>DZD</option><option>MAD</option>
          </select>
        </div>
        <div class="form-group">
          <label>Date document</label>
          <input type="date" id="doc-date">
        </div>
        <div class="form-group" id="grp-echeance">
          <label>Date échéance</label>
          <input type="date" id="doc-echeance">
        </div>
        <div class="form-group" id="grp-validite" style="display:none">
          <label>Validité devis jusqu'au</label>
          <input type="date" id="doc-validite">
        </div>
      </div>

      <hr style="margin:16px 0;border:none;border-top:1px solid #eee">
      <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#888;margin-bottom:12px">Informations client</div>
      <input type="hidden" id="doc-client-id">
      <div class="form-grid">
        <div class="form-group" style="position:relative">
          <label>Nom client *</label>
          <input type="text" id="doc-cnom" placeholder="BMT, Imprimerie X..." autocomplete="off"
                 oninput="searchClient(this.value)" onblur="hideClientDropdown()">
          <div class="client-dropdown" id="client-dropdown"></div>
        </div>
        <div class="form-group">
          <label>Matricule fiscale</label>
          <input type="text" id="doc-cmf" placeholder="1234567 A/P/M/000">
        </div>
        <div class="form-group full">
          <label>Adresse</label>
          <input type="text" id="doc-cadresse" placeholder="Rue, ville, code postal">
        </div>
        <div class="form-group">
          <label>Pays</label>
          <input type="text" id="doc-cpays" placeholder="Tunisie">
        </div>
        <div class="form-group">
          <label>Email client</label>
          <input type="email" id="doc-cemail">
        </div>
      </div>

      <hr style="margin:16px 0;border:none;border-top:1px solid #eee">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
        <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#888">Lignes</div>
        <button type="button" class="btn btn-outline btn-sm" onclick="addLigne()">+ Ligne</button>
      </div>
      <table class="lignes-table">
        <thead><tr><th style="width:45%">Description</th><th style="width:12%">Qté</th><th style="width:18%">Prix unit. HT</th><th style="width:18%">Total HT</th><th style="width:7%"></th></tr></thead>
        <tbody id="lignes-body"></tbody>
      </table>

      <div style="display:flex;justify-content:flex-end;margin-top:12px">
        <div class="totals-box">
          <div class="t-row"><span>Total HT</span><span id="tot-ht">0,000</span></div>
          <div class="t-row tva">
            <span>TVA <input type="number" id="doc-tva" value="19" min="0" max="100" step="0.5" style="width:50px;border:1px solid #e0e0e0;border-radius:4px;padding:2px 4px;font-size:12px" onchange="recalc()"> %</span>
            <span id="tot-tva">0,000</span>
          </div>
          <div class="t-row tva"><span>Timbre fiscal</span>
            <span><input type="number" id="doc-timbre" value="1" min="0" step="0.1" style="width:60px;border:1px solid #e0e0e0;border-radius:4px;padding:2px 4px;font-size:12px" onchange="recalc()"></span>
          </div>
          <div class="t-row ttc"><span>Total TTC</span><span id="tot-ttc">0,000</span></div>
        </div>
      </div>

      <hr style="margin:16px 0;border:none;border-top:1px solid #eee">
      <div class="form-grid">
        <div class="form-group full">
          <label>Mode de paiement</label>
          <input type="text" id="doc-modepay" placeholder="70 % à la commande et 30 % à l'installation.">
        </div>
        <div class="form-group full">
          <label>Notes internes</label>
          <textarea id="doc-notes" rows="2"></textarea>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="RYBSEN.closeModal('modal-doc')">Annuler</button>
      <button class="btn btn-primary" onclick="saveDoc()">Enregistrer</button>
    </div>
  </div>
</div>

<!-- MODAL DÉTAIL / PAIEMENTS -->
<div class="modal-overlay" id="modal-detail">
  <div class="modal" style="max-width:640px">
    <div class="modal-header">
      <div class="modal-title" id="detail-title">Détail document</div>
      <button class="modal-close" onclick="RYBSEN.closeModal('modal-detail')">✕</button>
    </div>
    <div class="modal-body" id="detail-body"></div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="RYBSEN.closeModal('modal-detail')">Fermer</button>
      <button class="btn btn-teal" id="detail-print-btn" onclick="">🖨 Imprimer</button>
      <button class="btn btn-primary" id="detail-edit-btn" onclick="">✏️ Modifier</button>
    </div>
  </div>
</div>

<script>
let allDocs = [];
let currentType = '';
let currentDocId = null;

const typeColors = {
  'Devis':'badge-navy','Facture':'badge-teal','Pro forma':'badge-gold','Bon de livraison':'badge-grey'
};
const statColors = {
  'Brouillon':'badge-grey','Envoyé':'badge-navy','Accepté':'badge-teal','Refusé':'badge-red',
  'Partiellement payé':'badge-gold','Payé':'badge-green','Annulé':'badge-red'
};
const fmt = (n, devise) => new Intl.NumberFormat('fr-FR',{minimumFractionDigits:3,maximumFractionDigits:3}).format(n||0) + ' ' + (devise||'TND');

async function loadDocs() {
  allDocs = await RYBSEN.api('doc_list');
  updateCounts();
  updateStats();
  renderDocs();
}

function updateCounts() {
  document.getElementById('cnt-all').textContent = allDocs.length;
  document.getElementById('cnt-devis').textContent = allDocs.filter(d=>d.type==='Devis').length;
  document.getElementById('cnt-fac').textContent = allDocs.filter(d=>d.type==='Facture').length;
  document.getElementById('cnt-pf').textContent = allDocs.filter(d=>d.type==='Pro forma').length;
  document.getElementById('cnt-bl').textContent = allDocs.filter(d=>d.type==='Bon de livraison').length;
}

function updateStats() {
  const factures = allDocs.filter(d => d.type === 'Facture' && d.statut !== 'Annulé');
  const ca = factures.reduce((s,d) => s + parseFloat(d.sous_total_ht||0), 0);
  const enc = factures.reduce((s,d) => s + parseFloat(d.montant_paye||0), 0);
  const reste = factures.reduce((s,d) => {
    const mp = parseFloat(d.montant_paye||0);
    const ttc = parseFloat(d.total_ttc||0);
    return s + Math.max(0, ttc - mp);
  }, 0);
  const devisAtt = allDocs.filter(d => d.type==='Devis' && ['Brouillon','Envoyé'].includes(d.statut)).length;
  const d = factures[0]?.devise || 'TND';
  document.getElementById('st-ca').textContent = fmt(ca, d);
  document.getElementById('st-encaisse').textContent = fmt(enc, d);
  document.getElementById('st-reste').textContent = fmt(reste, d);
  document.getElementById('st-devis').textContent = devisAtt;
}

function renderDocs() {
  const e = RYBSEN.escape.bind(RYBSEN);
  const filterStatut = document.getElementById('filter-statut-doc').value;
  let data = allDocs;
  if (currentType) data = data.filter(d => d.type === currentType);
  if (filterStatut) data = data.filter(d => d.statut === filterStatut);
  const body = document.getElementById('doc-body');
  if (!data.length) {
    body.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:30px;color:#999">Aucun document</td></tr>';
    return;
  }
  body.innerHTML = data.map(d => {
    const paid = parseFloat(d.montant_paye||0);
    const ttc = parseFloat(d.total_ttc||0);
    const paidStr = paid > 0 ? `<span class="badge badge-green" style="font-size:10px">${fmt(paid, d.devise)}</span>` : '—';
    return `<tr>
      <td><strong style="font-family:monospace;color:var(--navy)">${e(d.numero)}</strong></td>
      <td><span class="badge ${typeColors[d.type]||'badge-grey'}">${e(d.type)}</span></td>
      <td><strong>${e(d.client_nom)}</strong>${d.client_pays?`<br><small style="color:#999">${e(d.client_pays)}</small>`:''}</td>
      <td>${d.date_document ? new Date(d.date_document).toLocaleDateString('fr-FR') : '—'}</td>
      <td><strong>${fmt(ttc, d.devise)}</strong></td>
      <td>${paidStr}</td>
      <td><span class="badge ${statColors[d.statut]||'badge-grey'}">${e(d.statut)}</span></td>
      <td style="white-space:nowrap">
        <button onclick="openDetail(${d.id})" class="btn btn-outline btn-sm" title="Voir">👁</button>
        <button onclick="openEdit(${d.id})" class="btn btn-outline btn-sm" title="Modifier">✏️</button>
        <a href="/modules/facturation_print.php?id=${d.id}" target="_blank" class="btn btn-outline btn-sm" title="Imprimer">🖨</a>
        <button onclick="delDoc(${d.id})" class="btn btn-danger btn-sm" title="Supprimer">🗑</button>
      </td>
    </tr>`;
  }).join('');
}

// TABS
document.getElementById('doc-tabs').addEventListener('click', e => {
  const btn = e.target.closest('.doc-tab');
  if (!btn) return;
  document.querySelectorAll('.doc-tab').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  currentType = btn.dataset.type;
  renderDocs();
});

document.getElementById('filter-statut-doc').addEventListener('change', renderDocs);

// LIGNE ITEMS
let ligneIndex = 0;

function addLigne(desc='', qte=1, pu=0) {
  const idx = ligneIndex++;
  const tr = document.createElement('tr');
  tr.dataset.idx = idx;
  tr.innerHTML = `
    <td><input type="text" placeholder="Description du service / produit" value="${RYBSEN.escape(desc)}" onchange="recalc()"></td>
    <td><input type="number" value="${qte}" min="0" step="0.001" style="width:70px" oninput="recalcRow(this)" onchange="recalc()"></td>
    <td><input type="number" value="${pu}" min="0" step="0.001" oninput="recalcRow(this)" onchange="recalc()"></td>
    <td><input type="number" value="${Math.round(qte*pu*1000)/1000}" readonly style="background:#f8f9fa;color:var(--navy);font-weight:600"></td>
    <td><button type="button" class="btn-del-ligne" onclick="this.closest('tr').remove();recalc()">✕</button></td>`;
  document.getElementById('lignes-body').appendChild(tr);
  recalc();
}

function recalcRow(inp) {
  const tr = inp.closest('tr');
  const inputs = tr.querySelectorAll('input[type=number]');
  const q = parseFloat(inputs[0].value) || 0;
  const p = parseFloat(inputs[1].value) || 0;
  inputs[2].value = Math.round(q * p * 1000) / 1000;
}

function recalc() {
  let ht = 0;
  document.querySelectorAll('#lignes-body tr').forEach(tr => {
    const inputs = tr.querySelectorAll('input[type=number]');
    if (inputs.length >= 3) ht += parseFloat(inputs[2].value) || 0;
  });
  const tvaRate = parseFloat(document.getElementById('doc-tva').value) || 0;
  const timbre = parseFloat(document.getElementById('doc-timbre').value) || 0;
  const tva = Math.round(ht * tvaRate / 100 * 1000) / 1000;
  const ttc = Math.round((ht + tva + timbre) * 1000) / 1000;
  const dev = document.getElementById('doc-devise').value;
  const f = n => new Intl.NumberFormat('fr-FR',{minimumFractionDigits:3}).format(n) + ' ' + dev;
  document.getElementById('tot-ht').textContent = f(ht);
  document.getElementById('tot-tva').textContent = f(tva);
  document.getElementById('tot-ttc').textContent = f(ttc);
}

function handleTypeChange() {
  const t = document.getElementById('doc-type').value;
  document.getElementById('grp-validite').style.display = t === 'Devis' ? '' : 'none';
  document.getElementById('grp-echeance').style.display = ['Facture'].includes(t) ? '' : 'none';
  fetchNumero();
}

async function fetchNumero() {
  const type = document.getElementById('doc-type').value;
  const r = await RYBSEN.api('doc_next_numero', { type });
  if (r.numero) document.getElementById('doc-numero').value = r.numero;
}

function openAdd() {
  document.getElementById('modal-doc-title').textContent = 'Nouveau document';
  document.getElementById('doc-id').value = '';
  document.getElementById('doc-type').value = 'Facture';
  document.getElementById('doc-statut').value = 'Brouillon';
  document.getElementById('doc-devise').value = 'TND';
  document.getElementById('doc-date').value = new Date().toISOString().split('T')[0];
  document.getElementById('doc-echeance').value = '';
  document.getElementById('doc-validite').value = '';
  document.getElementById('doc-client-id').value = '';
  document.getElementById('doc-cnom').value = '';
  document.getElementById('doc-cmf').value = '';
  document.getElementById('doc-cadresse').value = '';
  document.getElementById('doc-cpays').value = 'Tunisie';
  document.getElementById('doc-cemail').value = '';
  document.getElementById('doc-modepay').value = "70 % à la commande et 30 % à l'installation.";
  document.getElementById('doc-notes').value = '';
  document.getElementById('doc-tva').value = 19;
  document.getElementById('doc-timbre').value = 1;
  document.getElementById('lignes-body').innerHTML = '';
  ligneIndex = 0;
  handleTypeChange();
  addLigne();
  RYBSEN.openModal('modal-doc');
}

async function openEdit(id) {
  const doc = await RYBSEN.api('doc_get', { id });
  document.getElementById('modal-doc-title').textContent = 'Modifier — ' + doc.numero;
  document.getElementById('doc-id').value = doc.id;
  document.getElementById('doc-type').value = doc.type;
  document.getElementById('doc-numero').value = doc.numero;
  document.getElementById('doc-statut').value = doc.statut;
  document.getElementById('doc-devise').value = doc.devise;
  document.getElementById('doc-date').value = doc.date_document || '';
  document.getElementById('doc-echeance').value = doc.date_echeance || '';
  document.getElementById('doc-validite').value = doc.date_validite || '';
  document.getElementById('doc-client-id').value = doc.client_id || '';
  document.getElementById('doc-cnom').value = doc.client_nom || '';
  document.getElementById('doc-cmf').value = doc.client_mf || '';
  document.getElementById('doc-cadresse').value = doc.client_adresse || '';
  document.getElementById('doc-cpays').value = doc.client_pays || '';
  document.getElementById('doc-cemail').value = doc.client_email || '';
  document.getElementById('doc-tva').value = doc.taux_tva || 19;
  document.getElementById('doc-timbre').value = doc.timbre || 1;
  document.getElementById('doc-modepay').value = doc.mode_paiement || '';
  document.getElementById('doc-notes').value = doc.notes || '';
  document.getElementById('lignes-body').innerHTML = '';
  ligneIndex = 0;
  (doc.lignes || []).forEach(l => addLigne(l.description, l.quantite, l.prix_unitaire_ht));
  handleTypeChange();
  document.getElementById('doc-type').value = doc.type;
  document.getElementById('doc-numero').value = doc.numero;
  RYBSEN.openModal('modal-doc');
}

async function saveDoc() {
  const nom = document.getElementById('doc-cnom').value.trim();
  const num = document.getElementById('doc-numero').value.trim();
  if (!nom || !num) { RYBSEN.toast('Numéro et nom client requis', 'error'); return; }

  const lignes = [];
  document.querySelectorAll('#lignes-body tr').forEach((tr, pos) => {
    const inputs = tr.querySelectorAll('input');
    const desc = inputs[0].value.trim();
    if (!desc) return;
    lignes.push({
      description: desc,
      quantite: parseFloat(inputs[1].value) || 1,
      prix_unitaire_ht: parseFloat(inputs[2].value) || 0
    });
  });

  const r = await RYBSEN.api('doc_save', {
    id: document.getElementById('doc-id').value,
    numero: num,
    type: document.getElementById('doc-type').value,
    statut: document.getElementById('doc-statut').value,
    devise: document.getElementById('doc-devise').value,
    date_document: document.getElementById('doc-date').value || null,
    date_echeance: document.getElementById('doc-echeance').value || null,
    date_validite: document.getElementById('doc-validite').value || null,
    client_id: document.getElementById('doc-client-id').value || null,
    client_nom: nom,
    client_mf: document.getElementById('doc-cmf').value,
    client_adresse: document.getElementById('doc-cadresse').value,
    client_pays: document.getElementById('doc-cpays').value,
    client_email: document.getElementById('doc-cemail').value,
    taux_tva: document.getElementById('doc-tva').value,
    timbre: document.getElementById('doc-timbre').value,
    mode_paiement: document.getElementById('doc-modepay').value,
    notes: document.getElementById('doc-notes').value,
    lignes
  });
  if (r.ok) { RYBSEN.closeModal('modal-doc'); RYBSEN.toast('Document enregistré ✓'); loadDocs(); }
  else RYBSEN.toast(r.error || 'Erreur', 'error');
}

async function openDetail(id) {
  currentDocId = id;
  const doc = await RYBSEN.api('doc_get', { id });
  const e = RYBSEN.escape.bind(RYBSEN);
  document.getElementById('detail-title').textContent = doc.numero + ' — ' + doc.client_nom;
  document.getElementById('detail-print-btn').onclick = () => window.open('/modules/facturation_print.php?id=' + id, '_blank');
  document.getElementById('detail-edit-btn').onclick = () => { RYBSEN.closeModal('modal-detail'); openEdit(id); };

  const paid = parseFloat(doc.montant_paye||0);
  const ttc = parseFloat(doc.total_ttc||0);
  const reste = Math.max(0, ttc - paid);

  let paiementsHtml = '';
  if (doc.type === 'Facture') {
    paiementsHtml = `<hr style="margin:14px 0;border:none;border-top:1px solid #eee">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
      <strong style="font-size:13px">Paiements reçus</strong>
      <button class="btn btn-teal btn-sm" onclick="openPaiement(${id}, ${ttc})">+ Paiement</button>
    </div>
    <div class="paiements-list">
      ${(doc.paiements||[]).length === 0 ? '<div style="color:#999;font-size:13px">Aucun paiement enregistré</div>' :
        (doc.paiements||[]).map(p => `<div class="paiement-row">
          <span class="badge-paid">${fmt(p.montant, doc.devise)}</span>
          <span>${e(p.mode)}</span>
          <span style="color:#888;font-size:12px">${p.date_paiement ? new Date(p.date_paiement).toLocaleDateString('fr-FR') : ''}</span>
          ${p.reference ? `<span style="color:#666;font-size:11px">Réf: ${e(p.reference)}</span>` : ''}
          <button onclick="delPaiement(${p.id}, ${id})" class="btn-del-ligne" style="margin-left:auto">✕</button>
        </div>`).join('')
      }
    </div>
    <div style="display:flex;justify-content:flex-end;gap:16px;margin-top:10px;font-size:13px">
      <span>Encaissé: <strong style="color:#16a34a">${fmt(paid, doc.devise)}</strong></span>
      <span>Reste: <strong style="color:${reste>0?'#dc2626':'#16a34a'}">${fmt(reste, doc.devise)}</strong></span>
    </div>`;
  }

  document.getElementById('detail-body').innerHTML = `
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
      <div><div style="font-size:11px;color:#888;text-transform:uppercase">Client</div><strong>${e(doc.client_nom)}</strong>${doc.client_adresse?`<br><small style="color:#666">${e(doc.client_adresse)}</small>`:''}</div>
      <div>
        <div><span class="badge ${statColors[doc.statut]||'badge-grey'}">${e(doc.statut)}</span></div>
        ${doc.date_document?`<div style="margin-top:4px;font-size:13px">Date: ${new Date(doc.date_document).toLocaleDateString('fr-FR')}</div>`:''}
        ${doc.date_echeance?`<div style="font-size:12px;color:#888">Échéance: ${new Date(doc.date_echeance).toLocaleDateString('fr-FR')}</div>`:''}
      </div>
    </div>
    <table style="width:100%;border-collapse:collapse;font-size:13px">
      <thead><tr style="background:#f8f9fa"><th style="padding:7px;text-align:left">Description</th><th style="padding:7px;text-align:right">Qté</th><th style="padding:7px;text-align:right">PU HT</th><th style="padding:7px;text-align:right">Total HT</th></tr></thead>
      <tbody>
        ${(doc.lignes||[]).map(l=>`<tr style="border-bottom:1px solid #f0f0f0">
          <td style="padding:7px">${e(l.description)}</td>
          <td style="padding:7px;text-align:right">${parseFloat(l.quantite)}</td>
          <td style="padding:7px;text-align:right">${fmt(l.prix_unitaire_ht, doc.devise)}</td>
          <td style="padding:7px;text-align:right"><strong>${fmt(l.total_ht, doc.devise)}</strong></td>
        </tr>`).join('')}
      </tbody>
    </table>
    <div style="display:flex;justify-content:flex-end;margin-top:10px">
      <div style="min-width:220px;font-size:13px">
        <div style="display:flex;justify-content:space-between;padding:3px 0">Total HT<strong>${fmt(doc.sous_total_ht, doc.devise)}</strong></div>
        <div style="display:flex;justify-content:space-between;padding:3px 0;color:#888">TVA ${doc.taux_tva}%<span>${fmt(doc.montant_tva, doc.devise)}</span></div>
        ${parseFloat(doc.timbre)>0?`<div style="display:flex;justify-content:space-between;padding:3px 0;color:#888">Timbre<span>${fmt(doc.timbre, doc.devise)}</span></div>`:''}
        <div style="display:flex;justify-content:space-between;padding:6px 0;border-top:2px solid var(--navy);margin-top:4px;font-size:16px;font-weight:800;color:var(--navy)">Total TTC<span>${fmt(doc.total_ttc, doc.devise)}</span></div>
      </div>
    </div>
    ${paiementsHtml}`;
  RYBSEN.openModal('modal-detail');
}

// PAIEMENT
let _paiDoc = null, _paiTtc = 0;
function openPaiement(docId, ttc) {
  _paiDoc = docId; _paiTtc = ttc;
  const today = new Date().toISOString().split('T')[0];
  const html = `<div style="padding:4px 0">
    <div class="form-grid">
      <div class="form-group"><label>Montant *</label><input type="number" id="pai-montant" value="${ttc}" min="0" step="0.001"></div>
      <div class="form-group"><label>Date</label><input type="date" id="pai-date" value="${today}"></div>
      <div class="form-group"><label>Mode</label><select id="pai-mode"><option>Virement</option><option>Chèque</option><option>Espèces</option><option>Traite</option></select></div>
      <div class="form-group"><label>Référence</label><input type="text" id="pai-ref" placeholder="N° virement..."></div>
      <div class="form-group full"><label>Notes</label><textarea id="pai-notes" rows="2"></textarea></div>
    </div>
    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px">
      <button class="btn btn-outline" onclick="openDetail(${docId})">Annuler</button>
      <button class="btn btn-primary" onclick="savePaiement()">Enregistrer</button>
    </div>
  </div>`;
  document.getElementById('detail-title').textContent = 'Enregistrer un paiement';
  document.getElementById('detail-body').innerHTML = html;
  document.getElementById('detail-print-btn').style.display = 'none';
  document.getElementById('detail-edit-btn').style.display = 'none';
}

async function savePaiement() {
  const montant = document.getElementById('pai-montant').value;
  if (!montant) { RYBSEN.toast('Montant requis', 'error'); return; }
  const r = await RYBSEN.api('paiement_save', {
    document_id: _paiDoc,
    montant,
    date_paiement: document.getElementById('pai-date').value || null,
    mode: document.getElementById('pai-mode').value,
    reference: document.getElementById('pai-ref').value,
    notes: document.getElementById('pai-notes').value
  });
  if (r.ok) {
    RYBSEN.toast('Paiement enregistré ✓');
    document.getElementById('detail-print-btn').style.display = '';
    document.getElementById('detail-edit-btn').style.display = '';
    loadDocs();
    openDetail(_paiDoc);
  } else RYBSEN.toast(r.error || 'Erreur', 'error');
}

async function delPaiement(paiId, docId) {
  if (!RYBSEN.confirmDelete()) return;
  const r = await RYBSEN.api('paiement_delete', { id: paiId });
  if (r.ok) { RYBSEN.toast('Supprimé'); loadDocs(); openDetail(docId); }
}

async function delDoc(id) {
  if (!RYBSEN.confirmDelete()) return;
  const r = await RYBSEN.api('doc_delete', { id });
  if (r.ok) { RYBSEN.toast('Supprimé'); loadDocs(); }
}

// CLIENT AUTOCOMPLETE
let _clientResults = [];
let _clientTimer = null;

async function searchClient(q) {
  document.getElementById('doc-client-id').value = '';
  clearTimeout(_clientTimer);
  if (q.length < 2) { hideClientDropdown(); return; }
  _clientTimer = setTimeout(async () => {
    const r = await RYBSEN.api('cli_search', { q });
    const results = r.results || [];
    if (!results.length) { hideClientDropdown(); return; }
    _clientResults = results;
    const dd = document.getElementById('client-dropdown');
    dd.innerHTML = results.map((c, i) =>
      `<div class="client-option" onmousedown="selectClient(${i})">
        <strong>${RYBSEN.escape(c.nom_entreprise)}</strong>
        ${c.pays ? `<small> · ${RYBSEN.escape(c.pays)}</small>` : ''}
        ${c.contact_email ? `<small style="display:block;color:#aaa;font-size:11px">${RYBSEN.escape(c.contact_email)}</small>` : ''}
      </div>`
    ).join('');
    dd.style.display = 'block';
  }, 250);
}

function selectClient(idx) {
  const c = _clientResults[idx];
  if (!c) return;
  document.getElementById('doc-client-id').value = c.id;
  document.getElementById('doc-cnom').value = c.nom_entreprise;
  document.getElementById('doc-cemail').value = c.contact_email || '';
  document.getElementById('doc-cpays').value = c.pays || '';
  document.getElementById('doc-cadresse').value = c.ville || '';
  hideClientDropdown();
}

function hideClientDropdown() {
  setTimeout(() => {
    const dd = document.getElementById('client-dropdown');
    if (dd) dd.style.display = 'none';
  }, 150);
}

loadDocs();
</script>
<?php require_once '../includes/footer.php'; ?>
