<?php
require_once '../config.php';
requireRole(['admin','magasinier']);
$pageTitle = 'Commandes fournisseur';
$activePage = 'commandes';
require_once '../includes/header.php';
?>
<div class="section-card">
  <div class="section-header">
    <div class="section-title">📦 Commandes de pièces (fournisseur)</div>
    <div class="section-actions"><button class="btn btn-primary" onclick="openAdd()">+ Nouvelle commande</button></div>
  </div>
  <div class="filters-bar">
    <input type="text" id="search" placeholder="🔍 N°, fournisseur…">
    <select id="f-statut" onchange="render()">
      <option value="">Tous statuts</option>
      <option value="brouillon">Brouillon</option>
      <option value="commandee">Commandée</option>
      <option value="partielle">Partielle</option>
      <option value="recue">Reçue</option>
      <option value="annulee">Annulée</option>
    </select>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>N°</th><th>Fournisseur</th><th>Lignes</th><th>Montant</th><th>Commandée</th><th>Réception prév.</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody id="body"><tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-muted)">Chargement…</td></tr></tbody>
    </table>
  </div>
  <div style="padding:10px 14px;color:var(--text-muted);font-size:13px" id="count"></div>
</div>

<!-- Modal édition -->
<div class="modal-overlay" id="modal-cmd">
  <div class="modal" style="max-width:820px">
    <div class="modal-header"><div class="modal-title" id="modal-title">Commande</div><button class="modal-close" onclick="CTP.closeModal('modal-cmd')">✕</button></div>
    <div class="modal-body">
      <input type="hidden" id="o-id">
      <div class="form-grid">
        <div class="form-group"><label>Fournisseur</label><input type="text" id="o-fourn" placeholder="Kodak / distributeur"></div>
        <div class="form-group"><label>Date commande</label><input type="date" id="o-date"></div>
        <div class="form-group"><label>Réception prévue</label><input type="date" id="o-recprev"></div>
        <div class="form-group full"><label>Notes</label><input type="text" id="o-notes"></div>
      </div>
      <div class="section-title" style="margin:16px 0 8px">Lignes</div>
      <div class="table-wrap"><table>
        <thead><tr><th style="width:45%">Pièce</th><th>Qté</th><th>PU (TND)</th><th>Total</th><th></th></tr></thead>
        <tbody id="lignes-body"></tbody>
      </table></div>
      <button class="btn btn-outline btn-sm" style="margin-top:10px" onclick="addLigne()">+ Ajouter une ligne</button>
      <div style="text-align:right;margin-top:12px;font-weight:700" id="o-total">Total : —</div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="CTP.closeModal('modal-cmd')">Annuler</button>
      <button class="btn btn-primary" onclick="save()">Enregistrer</button>
    </div>
  </div>
</div>

<!-- Modal réception -->
<div class="modal-overlay" id="modal-recept">
  <div class="modal" style="max-width:720px">
    <div class="modal-header"><div class="modal-title" id="recept-title">Réception</div><button class="modal-close" onclick="CTP.closeModal('modal-recept')">✕</button></div>
    <div class="modal-body">
      <div class="alert-box info">Saisissez les quantités reçues maintenant. Le stock est incrémenté et tracé automatiquement.</div>
      <input type="hidden" id="r-id">
      <div class="table-wrap"><table>
        <thead><tr><th>Pièce</th><th>Commandé</th><th>Déjà reçu</th><th>Reste</th><th>Reçu maintenant</th></tr></thead>
        <tbody id="recept-body"></tbody>
      </table></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="CTP.closeModal('modal-recept')">Annuler</button>
      <button class="btn btn-teal" onclick="recevoir()">Valider la réception</button>
    </div>
  </div>
</div>

<script>
let all = [], pieces = [];
const statutBadge = s => ({brouillon:'badge-grey', commandee:'badge-blue', partielle:'badge-gold', recue:'badge-green', annulee:'badge-red'}[s] || 'badge-grey');
async function load() {
  const opt = await CTP.api('cmd_options'); pieces = opt.pieces || [];
  all = await CTP.api('cmd_list'); render();
}
function render() {
  const e = CTP.escape;
  const q = document.getElementById('search').value.toLowerCase();
  const fs = document.getElementById('f-statut').value;
  const rows = all.filter(c => {
    if (fs && c.statut !== fs) return false;
    if (q && !(`${c.numero} ${c.fournisseur||''}`).toLowerCase().includes(q)) return false;
    return true;
  });
  const body = document.getElementById('body');
  if (!rows.length) { body.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-muted)">Aucune commande</td></tr>'; }
  else body.innerHTML = rows.map(c => {
    const peutRecevoir = ['commandee','partielle'].includes(c.statut);
    const peutCommander = c.statut === 'brouillon';
    const peutSuppr = ['brouillon','commandee','annulee'].includes(c.statut);
    return `<tr>
      <td><strong>${e(c.numero)}</strong></td>
      <td>${e(c.fournisseur) || '—'}</td>
      <td class="num">${c.nb_lignes}</td>
      <td class="num">${CTP.formatCurrency(c.montant_total)}</td>
      <td>${CTP.formatDate(c.date_commande)}</td>
      <td>${CTP.formatDate(c.date_reception_prevue)}</td>
      <td><span class="badge ${statutBadge(c.statut)}">${e(c.statut)}</span></td>
      <td>
        <button class="btn btn-outline btn-sm" onclick="edit(${c.id})">${c.statut==='brouillon'?'Modifier':'Voir'}</button>
        ${peutCommander ? `<button class="btn btn-gold btn-sm" onclick="commander(${c.id})">Commander</button>`:''}
        ${peutRecevoir ? `<button class="btn btn-teal btn-sm" onclick="openRecept(${c.id})">Réceptionner</button>`:''}
        ${peutSuppr ? `<button class="btn btn-danger btn-sm" onclick="del(${c.id})">Suppr.</button>`:''}
      </td></tr>`;
  }).join('');
  document.getElementById('count').textContent = `${rows.length} commande(s)`;
}
function pieceOptions(sel) {
  return '<option value="">— Choisir —</option>' + pieces.map(p => `<option value="${p.id}" data-prix="${p.prix_achat}" ${p.id==sel?'selected':''}>${CTP.escape(p.reference)} — ${CTP.escape(p.designation)}</option>`).join('');
}
function ligneRow(l) {
  l = l || {};
  return `<tr>
    <td><select class="ln-piece" onchange="onPieceChange(this)">${pieceOptions(l.piece_id)}</select></td>
    <td><input type="number" class="ln-qte" style="width:70px" value="${l.quantite||1}" min="1" oninput="recalc()"></td>
    <td><input type="number" step="0.001" class="ln-pu" style="width:100px" value="${l.prix_unitaire||0}" oninput="recalc()"></td>
    <td class="ln-total num">—</td>
    <td><button class="btn btn-danger btn-sm" onclick="this.closest('tr').remove();recalc()">✕</button></td>
  </tr>`;
}
function addLigne(l) { document.getElementById('lignes-body').insertAdjacentHTML('beforeend', ligneRow(l)); recalc(); }
function onPieceChange(sel) {
  const opt = sel.options[sel.selectedIndex];
  const pu = opt ? opt.getAttribute('data-prix') : null;
  const row = sel.closest('tr');
  if (pu && parseFloat(row.querySelector('.ln-pu').value) === 0) row.querySelector('.ln-pu').value = pu;
  recalc();
}
function recalc() {
  let total = 0;
  document.querySelectorAll('#lignes-body tr').forEach(tr => {
    const q = parseFloat(tr.querySelector('.ln-qte').value) || 0;
    const pu = parseFloat(tr.querySelector('.ln-pu').value) || 0;
    const t = q * pu; total += t;
    tr.querySelector('.ln-total').textContent = CTP.formatCurrency(t);
  });
  document.getElementById('o-total').textContent = 'Total : ' + CTP.formatCurrency(total);
}
function openAdd() {
  document.getElementById('modal-title').textContent = 'Nouvelle commande';
  document.getElementById('o-id').value = '';
  document.getElementById('o-fourn').value = ''; document.getElementById('o-date').value = '';
  document.getElementById('o-recprev').value = ''; document.getElementById('o-notes').value = '';
  document.getElementById('lignes-body').innerHTML = ''; addLigne();
  CTP.openModal('modal-cmd');
}
async function edit(id) {
  const c = await CTP.api('cmd_get', { id });
  if (c.error) return CTP.toast(c.error, 'error');
  document.getElementById('modal-title').textContent = c.statut === 'brouillon' ? 'Modifier ' + c.numero : 'Commande ' + c.numero;
  document.getElementById('o-id').value = c.id;
  document.getElementById('o-fourn').value = c.fournisseur || '';
  document.getElementById('o-date').value = c.date_commande || '';
  document.getElementById('o-recprev').value = c.date_reception_prevue || '';
  document.getElementById('o-notes').value = c.notes || '';
  document.getElementById('lignes-body').innerHTML = '';
  (c.lignes || []).forEach(addLigne);
  if (!c.lignes || !c.lignes.length) addLigne();
  recalc();
  CTP.openModal('modal-cmd');
}
function collectLignes() {
  const lignes = [];
  document.querySelectorAll('#lignes-body tr').forEach(tr => {
    const pid = tr.querySelector('.ln-piece').value;
    const q = parseInt(tr.querySelector('.ln-qte').value, 10) || 0;
    const pu = parseFloat(tr.querySelector('.ln-pu').value) || 0;
    if (pid && q > 0) lignes.push({ piece_id: pid, quantite: q, prix_unitaire: pu });
  });
  return lignes;
}
async function save() {
  const lignes = collectLignes();
  if (!lignes.length) return CTP.toast('Ajoutez au moins une ligne', 'error');
  const d = {
    id: document.getElementById('o-id').value,
    fournisseur: document.getElementById('o-fourn').value.trim(),
    date_commande: document.getElementById('o-date').value,
    date_reception_prevue: document.getElementById('o-recprev').value,
    notes: document.getElementById('o-notes').value.trim(),
    lignes,
  };
  const r = await CTP.api('cmd_save', d);
  if (r.error) return CTP.toast(r.error, 'error');
  CTP.toast('Commande enregistrée'); CTP.closeModal('modal-cmd'); load();
}
async function commander(id) {
  const r = await CTP.api('cmd_changer_statut', { id, statut: 'commandee' });
  if (r.error) return CTP.toast(r.error, 'error');
  CTP.toast('Commande passée'); load();
}
async function openRecept(id) {
  const c = await CTP.api('cmd_get', { id });
  if (c.error) return CTP.toast(c.error, 'error');
  const e = CTP.escape;
  document.getElementById('r-id').value = id;
  document.getElementById('recept-title').textContent = 'Réception — ' + c.numero;
  document.getElementById('recept-body').innerHTML = (c.lignes || []).map(l => {
    const reste = l.quantite - l.quantite_recue;
    return `<tr>
      <td>${e(l.reference)} <span style="color:var(--text-muted)">${e(l.designation)}</span></td>
      <td class="num">${l.quantite}</td><td class="num">${l.quantite_recue}</td><td class="num">${reste}</td>
      <td><input type="number" class="rc-qte" data-ligne="${l.id}" style="width:80px" value="${reste}" min="0" max="${reste}"></td>
    </tr>`;
  }).join('');
  CTP.openModal('modal-recept');
}
async function recevoir() {
  const recues = {};
  document.querySelectorAll('.rc-qte').forEach(inp => { recues[inp.dataset.ligne] = parseInt(inp.value, 10) || 0; });
  const r = await CTP.api('cmd_recevoir', { id: document.getElementById('r-id').value, recues });
  if (r.error) return CTP.toast(r.error, 'error');
  CTP.toast('Réception enregistrée (stock mis à jour) — statut : ' + r.statut);
  CTP.closeModal('modal-recept'); load();
}
async function del(id) {
  if (!CTP.confirmDelete('Supprimer cette commande ?')) return;
  const r = await CTP.api('cmd_delete', { id });
  if (r.error) return CTP.toast(r.error, 'error');
  CTP.toast('Commande supprimée'); load();
}
load();
</script>
<?php require_once '../includes/footer.php'; ?>
