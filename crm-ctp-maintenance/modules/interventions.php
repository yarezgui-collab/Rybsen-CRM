<?php
require_once '../config.php';
requireRole(['admin','technicien','magasinier']);
$user = currentUser();
$peutEditer = in_array($user['role'], ['admin','technicien'], true);
$estAdmin = $user['role'] === 'admin';
$pageTitle = 'Interventions';
$activePage = 'interventions';
require_once '../includes/header.php';
?>
<div class="section-card">
  <div class="section-header">
    <div class="section-title">🔧 Interventions — maintenance & réparations</div>
    <?php if ($peutEditer): ?><div class="section-actions"><button class="btn btn-primary" onclick="openAdd()">+ Nouvelle intervention</button></div><?php endif; ?>
  </div>
  <div class="filters-bar">
    <input type="text" id="search" placeholder="🔍 N°, machine, client, description…">
    <select id="f-statut" onchange="render()">
      <option value="">Tous statuts</option>
      <option value="nouvelle">Nouvelle</option>
      <option value="planifiee">Planifiée</option>
      <option value="en_cours">En cours</option>
      <option value="en_attente_piece">Attente pièce</option>
      <option value="resolue">Résolue</option>
      <option value="cloturee">Clôturée</option>
      <option value="annulee">Annulée</option>
    </select>
    <select id="f-prio" onchange="render()">
      <option value="">Toutes priorités</option>
      <option value="urgente">Urgente</option>
      <option value="haute">Haute</option>
      <option value="normale">Normale</option>
      <option value="basse">Basse</option>
    </select>
    <select id="f-type" onchange="render()">
      <option value="">Tous types</option>
      <option value="corrective">Corrective</option>
      <option value="preventive">Préventive</option>
      <option value="installation">Installation</option>
      <option value="mise_a_jour">Mise à jour</option>
    </select>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>N°</th><th>Machine</th><th>Client</th><th>Type</th><th>Priorité</th><th>Technicien</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody id="body"><tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-muted)">Chargement…</td></tr></tbody>
    </table>
  </div>
  <div style="padding:10px 14px;color:var(--text-muted);font-size:13px" id="count"></div>
</div>

<!-- Modal édition -->
<div class="modal-overlay" id="modal-int">
  <div class="modal" style="max-width:760px">
    <div class="modal-header"><div class="modal-title" id="modal-title">Intervention</div><button class="modal-close" onclick="CTP.closeModal('modal-int')">✕</button></div>
    <div class="modal-body">
      <input type="hidden" id="i-id">
      <div class="form-grid">
        <div class="form-group full"><label>Machine *</label><select id="i-machine"></select></div>
        <div class="form-group"><label>Type</label><select id="i-type">
          <option value="corrective">Corrective (panne)</option><option value="preventive">Préventive</option>
          <option value="installation">Installation</option><option value="mise_a_jour">Mise à jour</option></select></div>
        <div class="form-group"><label>Priorité</label><select id="i-prio">
          <option value="basse">Basse</option><option value="normale" selected>Normale</option>
          <option value="haute">Haute</option><option value="urgente">Urgente</option></select></div>
        <div class="form-group"><label>Technicien</label><select id="i-tech"></select></div>
        <div class="form-group"><label>Statut</label><select id="i-statut">
          <option value="nouvelle">Nouvelle</option><option value="planifiee">Planifiée</option>
          <option value="en_cours">En cours</option><option value="en_attente_piece">Attente pièce</option>
          <option value="resolue">Résolue</option><option value="cloturee">Clôturée</option>
          <option value="annulee">Annulée</option></select></div>
        <div class="form-group"><label>Date planifiée</label><input type="datetime-local" id="i-planif"></div>
        <div class="form-group"><label>Relevé compteur (plaques)</label><input type="number" id="i-compteur" min="0"></div>
        <div class="form-group"><label>Temps passé (h)</label><input type="number" step="0.25" id="i-temps" min="0"></div>
        <div class="form-group"><label>Coût main d'œuvre (TND)</label><input type="number" step="0.001" id="i-cout" min="0"></div>
        <div class="form-group full"><label>Objet / symptôme</label><textarea id="i-desc"></textarea></div>
        <div class="form-group full"><label>Diagnostic</label><textarea id="i-diag"></textarea></div>
        <div class="form-group full"><label>Résolution</label><textarea id="i-reso"></textarea></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="CTP.closeModal('modal-int')">Annuler</button>
      <button class="btn btn-primary" onclick="save()">Enregistrer</button>
    </div>
  </div>
</div>

<!-- Modal détail + pièces -->
<div class="modal-overlay" id="modal-detail">
  <div class="modal" style="max-width:780px">
    <div class="modal-header"><div class="modal-title" id="detail-title">Détail</div><button class="modal-close" onclick="CTP.closeModal('modal-detail')">✕</button></div>
    <div class="modal-body" id="detail-body"></div>
    <div class="modal-footer"><button class="btn btn-outline" onclick="CTP.closeModal('modal-detail')">Fermer</button></div>
  </div>
</div>

<script>
const peutEditer = <?= $peutEditer ? 'true':'false' ?>;
const estAdmin = <?= $estAdmin ? 'true':'false' ?>;
let all = [], opts = { machines:[], techniciens:[], pieces:[] };
const statutFlow = ['nouvelle','planifiee','en_cours','en_attente_piece','resolue','cloturee','annulee'];
const statutBadge = s => ({nouvelle:'badge-red', planifiee:'badge-blue', en_cours:'badge-teal', en_attente_piece:'badge-gold', resolue:'badge-green', cloturee:'badge-grey', annulee:'badge-grey'}[s] || 'badge-grey');
const prioBadge = p => ({urgente:'badge-red', haute:'badge-gold', normale:'badge-navy', basse:'badge-grey'}[p] || 'badge-grey');
const lbl = s => (s||'').replace(/_/g,' ');

async function load() {
  opts = await CTP.api('int_options');
  all = await CTP.api('int_list'); render();
  if (location.hash) { const id = parseInt(location.hash.slice(1),10); if (id) detail(id); }
}
function render() {
  const e = CTP.escape;
  const q = document.getElementById('search').value.toLowerCase();
  const fs = document.getElementById('f-statut').value, fp = document.getElementById('f-prio').value, ft = document.getElementById('f-type').value;
  const rows = all.filter(i => {
    if (fs && i.statut !== fs) return false;
    if (fp && i.priorite !== fp) return false;
    if (ft && i.type !== ft) return false;
    if (q && !(`${i.numero} ${i.modele} ${i.n_serie} ${i.raison_sociale} ${i.description||''}`).toLowerCase().includes(q)) return false;
    return true;
  });
  const body = document.getElementById('body');
  if (!rows.length) { body.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-muted)">Aucune intervention</td></tr>'; }
  else body.innerHTML = rows.map(i => `<tr>
    <td><strong>${e(i.numero)}</strong></td>
    <td>${e(i.modele)}<br><span style="color:var(--text-muted)">${e(i.n_serie)}</span></td>
    <td>${e(i.raison_sociale)}</td>
    <td>${e(i.type)}</td>
    <td><span class="badge ${prioBadge(i.priorite)}">${e(i.priorite)}</span></td>
    <td>${e(i.technicien_nom) || '<span style="color:var(--text-muted)">non assigné</span>'}</td>
    <td><span class="badge ${statutBadge(i.statut)}">${lbl(i.statut)}</span></td>
    <td>
      <button class="btn btn-outline btn-sm" onclick="detail(${i.id})">Détail</button>
      ${peutEditer ? `<button class="btn btn-outline btn-sm" onclick="edit(${i.id})">Modifier</button>`:''}
    </td></tr>`).join('');
  document.getElementById('count').textContent = `${rows.length} intervention(s)`;
}
function machineOptions(sel) {
  return opts.machines.map(m => `<option value="${m.id}" ${m.id==sel?'selected':''}>${CTP.escape(m.raison_sociale)} — ${CTP.escape(m.modele)} (${CTP.escape(m.n_serie)})</option>`).join('');
}
function techOptions(sel) {
  return '<option value="">— Non assigné —</option>' + opts.techniciens.map(t => `<option value="${t.id}" ${t.id==sel?'selected':''}>${CTP.escape(t.nom)}</option>`).join('');
}
function fill(i) {
  i = i || {};
  document.getElementById('i-id').value = i.id || '';
  document.getElementById('i-machine').innerHTML = '<option value="">— Choisir —</option>' + machineOptions(i.machine_id);
  document.getElementById('i-type').value = i.type || 'corrective';
  document.getElementById('i-prio').value = i.priorite || 'normale';
  document.getElementById('i-tech').innerHTML = techOptions(i.technicien_id);
  document.getElementById('i-statut').value = i.statut || 'nouvelle';
  document.getElementById('i-planif').value = i.date_planifiee ? i.date_planifiee.replace(' ','T').slice(0,16) : '';
  document.getElementById('i-compteur').value = i.compteur_releve || '';
  document.getElementById('i-temps').value = i.temps_passe_h || '';
  document.getElementById('i-cout').value = i.cout_main_oeuvre || '';
  document.getElementById('i-desc').value = i.description || '';
  document.getElementById('i-diag').value = i.diagnostic || '';
  document.getElementById('i-reso').value = i.resolution || '';
}
function openAdd() { document.getElementById('modal-title').textContent = 'Nouvelle intervention'; fill(null); CTP.openModal('modal-int'); }
async function edit(id) {
  const i = await CTP.api('int_get', { id });
  if (i.error) return CTP.toast(i.error, 'error');
  document.getElementById('modal-title').textContent = 'Modifier ' + i.numero; fill(i); CTP.openModal('modal-int');
}
async function save() {
  const d = {
    id: document.getElementById('i-id').value,
    machine_id: document.getElementById('i-machine').value,
    type: document.getElementById('i-type').value,
    priorite: document.getElementById('i-prio').value,
    technicien_id: document.getElementById('i-tech').value,
    statut: document.getElementById('i-statut').value,
    date_planifiee: document.getElementById('i-planif').value ? document.getElementById('i-planif').value.replace('T',' ')+':00' : '',
    compteur_releve: document.getElementById('i-compteur').value,
    temps_passe_h: document.getElementById('i-temps').value,
    cout_main_oeuvre: document.getElementById('i-cout').value,
    description: document.getElementById('i-desc').value.trim(),
    diagnostic: document.getElementById('i-diag').value.trim(),
    resolution: document.getElementById('i-reso').value.trim(),
  };
  if (!d.machine_id) return CTP.toast('Machine requise', 'error');
  const r = await CTP.api('int_save', d);
  if (r.error) return CTP.toast(r.error, 'error');
  CTP.toast('Intervention enregistrée'); CTP.closeModal('modal-int'); load();
}

let currentDetail = null;
async function detail(id) {
  const i = await CTP.api('int_get', { id });
  if (i.error) return CTP.toast(i.error, 'error');
  currentDetail = i;
  const e = CTP.escape;
  const pieces = i.pieces || [];
  const totalPieces = pieces.reduce((s,p) => s + p.quantite * parseFloat(p.prix_unitaire), 0);
  const totalGlobal = totalPieces + parseFloat(i.cout_main_oeuvre || 0);
  document.getElementById('detail-title').textContent = i.numero + ' — ' + i.modele;

  const statutBtns = peutEditer ? statutFlow.filter(s => s !== i.statut).map(s =>
    `<button class="btn btn-outline btn-sm" onclick="changeStatut(${i.id},'${s}')">${lbl(s)}</button>`).join(' ') : '';

  const pieceRows = pieces.length ? pieces.map(p => `<tr>
      <td>${e(p.reference)}</td><td>${e(p.designation)}</td>
      <td class="num">${p.quantite}</td><td class="num">${CTP.formatCurrency(p.prix_unitaire)}</td>
      <td class="num">${CTP.formatCurrency(p.quantite * p.prix_unitaire)}</td>
      <td>${peutEditer ? `<button class="btn btn-danger btn-sm" onclick="removePiece(${p.id})">✕</button>`:''}</td>
    </tr>`).join('') : '<tr><td colspan="6" style="text-align:center;padding:16px;color:var(--text-muted)">Aucune pièce consommée</td></tr>';

  const pieceSelect = opts.pieces.map(p => `<option value="${p.id}" data-prix="${p.prix_vente}">${e(p.reference)} — ${e(p.designation)} (stock ${p.stock})</option>`).join('');

  document.getElementById('detail-body').innerHTML = `
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
      <span class="badge ${statutBadge(i.statut)}">${lbl(i.statut)}</span>
      <span class="badge ${prioBadge(i.priorite)}">${e(i.priorite)}</span>
      <span class="badge badge-navy">${e(i.type)}</span>
      <span class="badge badge-grey">${e(i.raison_sociale)}</span>
      <span class="badge badge-grey">S/N ${e(i.n_serie)}</span>
    </div>
    ${statutBtns ? `<div style="margin-bottom:16px"><label style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-muted)">Changer le statut</label><div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px">${statutBtns}</div></div>`:''}
    <div class="form-grid" style="margin-bottom:8px">
      <div><label style="font-size:11px;color:var(--text-muted)">Technicien</label><div>${e(i.technicien_nom)||'—'}</div></div>
      <div><label style="font-size:11px;color:var(--text-muted)">Planifiée</label><div>${CTP.formatDateTime(i.date_planifiee)}</div></div>
      <div><label style="font-size:11px;color:var(--text-muted)">Début</label><div>${CTP.formatDateTime(i.date_debut)}</div></div>
      <div><label style="font-size:11px;color:var(--text-muted)">Fin</label><div>${CTP.formatDateTime(i.date_fin)}</div></div>
    </div>
    ${i.description ? `<p style="margin:8px 0"><strong>Objet :</strong> ${e(i.description)}</p>`:''}
    ${i.diagnostic ? `<p style="margin:8px 0"><strong>Diagnostic :</strong> ${e(i.diagnostic)}</p>`:''}
    ${i.resolution ? `<p style="margin:8px 0"><strong>Résolution :</strong> ${e(i.resolution)}</p>`:''}

    <div class="section-title" style="margin:18px 0 8px">⚙️ Pièces consommées</div>
    <div class="table-wrap"><table>
      <thead><tr><th>Réf</th><th>Désignation</th><th>Qté</th><th>PU</th><th>Total</th><th></th></tr></thead>
      <tbody>${pieceRows}</tbody>
    </table></div>
    ${peutEditer ? `<div style="display:flex;gap:8px;align-items:flex-end;margin-top:12px;flex-wrap:wrap">
      <div class="form-group" style="flex:1;min-width:220px"><label>Ajouter une pièce</label><select id="add-piece">${pieceSelect}</select></div>
      <div class="form-group" style="width:90px"><label>Qté</label><input type="number" id="add-qte" value="1" min="1"></div>
      <button class="btn btn-teal" onclick="addPiece(${i.id})">+ Consommer</button>
    </div>`:''}

    <div style="margin-top:16px;padding-top:12px;border-top:1px solid var(--border);text-align:right">
      <div style="color:var(--text-muted)">Pièces : ${CTP.formatCurrency(totalPieces)} · Main d'œuvre : ${CTP.formatCurrency(i.cout_main_oeuvre||0)}</div>
      <div style="font-size:18px;font-weight:700;margin-top:4px">Total : ${CTP.formatCurrency(totalGlobal)}</div>
    </div>`;
  CTP.openModal('modal-detail');
}
async function changeStatut(id, statut) {
  const r = await CTP.api('int_change_statut', { id, statut });
  if (r.error) return CTP.toast(r.error, 'error');
  CTP.toast('Statut mis à jour'); await load(); detail(id);
}
async function addPiece(intId) {
  const sel = document.getElementById('add-piece');
  const pieceId = sel.value; const qte = document.getElementById('add-qte').value;
  if (!pieceId) return CTP.toast('Choisissez une pièce', 'error');
  const r = await CTP.api('int_add_piece', { intervention_id: intId, piece_id: pieceId, quantite: qte });
  if (r.error) return CTP.toast(r.error, 'error');
  CTP.toast('Pièce consommée (stock décrémenté)'); opts = await CTP.api('int_options'); detail(intId);
}
async function removePiece(ligneId) {
  if (!CTP.confirmDelete('Retirer cette pièce (le stock sera recrédité) ?')) return;
  const r = await CTP.api('int_remove_piece', { ligne_id: ligneId });
  if (r.error) return CTP.toast(r.error, 'error');
  CTP.toast('Pièce retirée'); opts = await CTP.api('int_options'); detail(currentDetail.id);
}
load();
</script>
<?php require_once '../includes/footer.php'; ?>
