<?php
require_once '../config.php';
requireRole(['admin','technicien']);
$pageTitle = 'Planning & tournées';
$activePage = 'planning';
require_once '../includes/header.php';
?>
<style>
  .plan-toolbar { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
  .plan-nav { display:flex; align-items:center; gap:6px; }
  .plan-month { font-family:Georgia,serif; font-weight:bold; font-size:16px; color:var(--navy); min-width:170px; text-align:center; text-transform:capitalize; }
  .cal-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:6px; }
  .cal-head { font-weight:700; text-transform:uppercase; font-size:10px; letter-spacing:1px; color:var(--text-muted); text-align:center; padding:6px 0; }
  .cal-cell { min-height:104px; border:1px solid var(--border); border-radius:8px; padding:5px; background:var(--white); overflow:hidden; }
  .cal-cell.other { background:#fbfbfc; }
  .cal-cell.other .cal-daynum { opacity:.35; }
  .cal-cell.today { border-color:var(--teal); box-shadow:0 0 0 2px rgba(218,41,28,.15); }
  .cal-daynum { font-size:11px; font-weight:700; color:var(--text-muted); }
  .cal-chip { display:block; font-size:11px; line-height:1.25; padding:3px 6px; border-radius:5px; margin-top:4px; cursor:pointer; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; border-left:3px solid transparent; }
  .cal-chip.preventive { background:var(--teal-light); color:#8a231b; border-left-color:var(--teal); }
  .cal-chip.previsionnelle { background:var(--gold-light); color:#8a6a10; border-left-color:var(--gold); }
  .cal-chip.retard { background:var(--red-light); color:var(--red); border-left-color:var(--red); }
  .cal-chip .who { font-size:9px; opacity:.8; }
  .grp-card { border:1px solid var(--border); border-radius:10px; margin-bottom:12px; overflow:hidden; }
  .grp-head { display:flex; justify-content:space-between; align-items:center; padding:10px 14px; background:var(--cream); font-weight:700; color:var(--navy); }
  @media (max-width:768px){ .cal-cell{ min-height:76px; } .cal-chip{ font-size:10px; } }
</style>

<div class="section-card">
  <div class="section-header">
    <div class="section-title">🗓️ Planning & tournées</div>
    <div class="section-actions">
      <button class="btn btn-gold btn-sm" onclick="genererTous()" title="Reconduit d'un an le dernier cycle de chaque contrat actif">↻ Générer les cycles suivants</button>
      <a href="/modules/maintenance.php" class="btn btn-outline btn-sm">Vue liste</a>
    </div>
  </div>
  <div class="filters-bar plan-toolbar">
    <div class="plan-nav">
      <button class="btn btn-outline btn-sm" onclick="moveMonth(-1)">◀</button>
      <span class="plan-month" id="plan-month">—</span>
      <button class="btn btn-outline btn-sm" onclick="moveMonth(1)">▶</button>
      <button class="btn btn-outline btn-sm" onclick="goToday()">Aujourd'hui</button>
    </div>
    <select id="f-vue" onchange="render()">
      <option value="calendrier">📅 Calendrier</option>
      <option value="technicien">👷 Par technicien</option>
      <option value="region">📍 Par région</option>
    </select>
    <select id="f-tech" onchange="render()"><option value="">Tous les techniciens</option></select>
    <select id="f-type" onchange="render()">
      <option value="">Préventives + prévisionnelles</option>
      <option value="preventive">Préventives</option>
      <option value="previsionnelle">Prévisionnelles</option>
    </select>
  </div>
  <div style="padding:16px" id="plan-body"><div style="text-align:center;color:var(--text-muted);padding:30px">Chargement…</div></div>
  <div style="padding:0 16px 14px;color:var(--text-muted);font-size:13px" id="count"></div>
</div>

<!-- Modal affectation -->
<div class="modal-overlay" id="modal-assign">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Visite préventive</div><button class="modal-close" onclick="CTP.closeModal('modal-assign')">✕</button></div>
    <div class="modal-body">
      <input type="hidden" id="as-id">
      <div id="as-info" style="margin-bottom:14px"></div>
      <div class="form-group"><label>Technicien affecté (tournée)</label><select id="as-tech"></select></div>
    </div>
    <div class="modal-footer">
      <a class="btn btn-outline" id="as-link" href="#">Ouvrir dans la liste</a>
      <button class="btn btn-primary" onclick="affecter()">Enregistrer l'affectation</button>
    </div>
  </div>
</div>

<script>
let all = [], techniciens = [];
let cur = new Date(); cur.setDate(1);
const MOIS = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
const JOURS = ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'];
const typeLabel = t => t === 'preventive' ? 'Préventive' : 'Prévisionnelle';
const initials = n => (n||'').split(/\s+/).map(w=>w[0]||'').join('').slice(0,2).toUpperCase();

async function load() {
  const opt = await CTP.api('mp_options'); techniciens = opt.techniciens || [];
  document.getElementById('f-tech').innerHTML = '<option value="">Tous les techniciens</option>' +
    '<option value="none">— Non affectés —</option>' +
    techniciens.map(t => `<option value="${t.id}">${CTP.escape(t.nom)}</option>`).join('');
  all = await CTP.api('mp_list'); render();
}
function filtered() {
  const ft = document.getElementById('f-type').value;
  const tech = document.getElementById('f-tech').value;
  return all.filter(x => {
    if (ft && x.type !== ft) return false;
    if (tech === 'none' && x.technicien_id) return false;
    if (tech && tech !== 'none' && String(x.technicien_id) !== tech) return false;
    return true;
  });
}
function inMonth(dstr) {
  const d = new Date(dstr + 'T00:00');
  return d.getFullYear() === cur.getFullYear() && d.getMonth() === cur.getMonth();
}
function render() {
  document.getElementById('plan-month').textContent = MOIS[cur.getMonth()] + ' ' + cur.getFullYear();
  const vue = document.getElementById('f-vue').value;
  if (vue === 'calendrier') renderCalendar();
  else renderGroups(vue);
}
function chipHtml(x) {
  const e = CTP.escape;
  const overdue = parseInt(x.jours_restants,10) < 0;
  const cls = overdue ? 'retard' : x.type;
  const who = x.technicien_nom ? `<span class="who">· ${e(initials(x.technicien_nom))}</span>` : '<span class="who">· ?</span>';
  return `<span class="cal-chip ${cls}" onclick="openAssign(${x.id})" title="${e(x.raison_sociale)} — ${e(x.modele)} (${typeLabel(x.type)})">${e(x.raison_sociale)} ${who}</span>`;
}
function renderCalendar() {
  const rows = filtered().filter(x => inMonth(x.date_prevue));
  const byDay = {};
  rows.forEach(x => { const d = x.date_prevue; (byDay[d] = byDay[d] || []).push(x); });
  // grille : lundi comme premier jour
  const first = new Date(cur.getFullYear(), cur.getMonth(), 1);
  let start = (first.getDay() + 6) % 7; // 0 = lundi
  const daysInMonth = new Date(cur.getFullYear(), cur.getMonth()+1, 0).getDate();
  const todayStr = new Date().toISOString().slice(0,10);
  let html = '<div class="cal-grid">' + JOURS.map(j => `<div class="cal-head">${j}</div>`).join('');
  // cellules avant le 1er
  const prevDays = new Date(cur.getFullYear(), cur.getMonth(), 0).getDate();
  for (let i = start-1; i >= 0; i--) html += `<div class="cal-cell other"><div class="cal-daynum">${prevDays - i}</div></div>`;
  for (let d = 1; d <= daysInMonth; d++) {
    const ds = `${cur.getFullYear()}-${String(cur.getMonth()+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
    const chips = (byDay[ds] || []).map(chipHtml).join('');
    html += `<div class="cal-cell ${ds===todayStr?'today':''}"><div class="cal-daynum">${d}</div>${chips}</div>`;
  }
  const total = start + daysInMonth; const trailing = (7 - (total % 7)) % 7;
  for (let i = 1; i <= trailing; i++) html += `<div class="cal-cell other"><div class="cal-daynum">${i}</div></div>`;
  html += '</div>';
  document.getElementById('plan-body').innerHTML = html;
  document.getElementById('count').textContent = `${rows.length} visite(s) en ${MOIS[cur.getMonth()]} ${cur.getFullYear()}`;
}
function renderGroups(mode) {
  const e = CTP.escape;
  const rows = filtered().filter(x => inMonth(x.date_prevue))
    .sort((a,b) => a.date_prevue.localeCompare(b.date_prevue));
  const groups = {};
  rows.forEach(x => {
    const key = mode === 'technicien' ? (x.technicien_nom || '— Non affecté —') : (x.ville || '— Ville inconnue —');
    (groups[key] = groups[key] || []).push(x);
  });
  const keys = Object.keys(groups).sort();
  if (!keys.length) { document.getElementById('plan-body').innerHTML = '<div style="text-align:center;color:var(--text-muted);padding:30px">Aucune visite ce mois</div>'; document.getElementById('count').textContent=''; return; }
  let html = '';
  keys.forEach(k => {
    const g = groups[k];
    html += `<div class="grp-card">
      <div class="grp-head"><span>${mode==='technicien'?'👷':'📍'} ${e(k)}</span><span class="badge badge-navy">${g.length} visite(s)</span></div>
      <div class="table-wrap"><table>
        <thead><tr><th>Date</th><th>Client</th><th>Machine</th><th>Ville</th><th>Type</th><th>${mode==='technicien'?'Ville':'Technicien'}</th><th></th></tr></thead>
        <tbody>${g.map(x => `<tr>
          <td><strong>${CTP.formatDate(x.date_prevue)}</strong></td>
          <td>${e(x.raison_sociale)}</td>
          <td>${e(x.modele)} <span style="color:var(--text-muted)">${e(x.n_serie)}</span></td>
          <td>${e(x.ville)||'—'}</td>
          <td><span class="badge ${x.type==='preventive'?'badge-teal':'badge-gold'}">${typeLabel(x.type)}</span></td>
          <td>${mode==='technicien' ? (e(x.ville)||'—') : (e(x.technicien_nom)||'<span style="color:var(--text-muted)">non affecté</span>')}</td>
          <td><button class="btn btn-outline btn-sm" onclick="openAssign(${x.id})">Affecter</button></td>
        </tr>`).join('')}</tbody>
      </table></div>
    </div>`;
  });
  document.getElementById('plan-body').innerHTML = html;
  document.getElementById('count').textContent = `${rows.length} visite(s) · ${keys.length} ${mode==='technicien'?'technicien(s)':'ville(s)'}`;
}
function moveMonth(delta) { cur.setMonth(cur.getMonth() + delta); render(); }
function goToday() { cur = new Date(); cur.setDate(1); render(); }

function find(id) { return all.find(x => String(x.id) === String(id)); }
function openAssign(id) {
  const x = find(id); if (!x) return;
  const e = CTP.escape;
  document.getElementById('as-id').value = id;
  document.getElementById('as-info').innerHTML =
    `<span class="badge badge-navy">${e(x.raison_sociale)}</span>
     <span class="badge badge-grey">${e(x.modele)} · ${e(x.n_serie)}</span>
     <span class="badge ${x.type==='preventive'?'badge-teal':'badge-gold'}">${typeLabel(x.type)}</span>
     <span class="badge badge-grey">${CTP.formatDate(x.date_prevue)}</span>
     ${x.ville?`<span class="badge badge-grey">📍 ${e(x.ville)}</span>`:''}`;
  document.getElementById('as-tech').innerHTML = '<option value="">— Non affecté —</option>' +
    techniciens.map(t => `<option value="${t.id}" ${String(t.id)===String(x.technicien_id)?'selected':''}>${e(t.nom)}</option>`).join('');
  document.getElementById('as-link').href = '/modules/maintenance.php';
  CTP.openModal('modal-assign');
}
async function affecter() {
  const r = await CTP.api('mp_assigner', { id: document.getElementById('as-id').value, technicien_id: document.getElementById('as-tech').value });
  if (r.error) return CTP.toast(r.error, 'error');
  CTP.toast('Technicien affecté'); CTP.closeModal('modal-assign'); load();
}
async function genererTous() {
  if (!confirm("Reconduire d'un an le dernier cycle de visites de chaque contrat actif ?\n(idempotent : aucune visite en double)")) return;
  const r = await CTP.api('mp_generer_tous');
  if (r.error) return CTP.toast(r.error, 'error');
  CTP.toast(`${r.visites} visite(s) générée(s) sur ${r.contrats} contrat(s)`); load();
}
load();
</script>
<?php require_once '../includes/footer.php'; ?>
