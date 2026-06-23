<?php
require_once '../config.php';
$pageTitle = 'Calendrier Éditorial LinkedIn';
$activePage = 'linkedin';
require_once '../includes/header.php';
?>
<div class="kpi-grid">
  <div class="kpi-card navy"><div class="kpi-label">Total posts</div><div class="kpi-value" id="kpi-li-total">—</div><div class="kpi-sub">dans le calendrier</div></div>
  <div class="kpi-card gold"><div class="kpi-label">À programmer</div><div class="kpi-value" id="kpi-li-aprog">—</div><div class="kpi-sub">en attente de rédaction</div></div>
  <div class="kpi-card teal"><div class="kpi-label">Prêts</div><div class="kpi-value" id="kpi-li-prets">—</div><div class="kpi-sub">prêts à publier</div></div>
  <div class="kpi-card red"><div class="kpi-label">Aujourd'hui</div><div class="kpi-value" id="kpi-li-today">—</div><div class="kpi-sub">post(s) prévu(s) ce jour</div></div>
</div>

<div class="section-card">
  <div class="section-header">
    <div class="section-title">📅 Calendrier Éditorial LinkedIn</div>
    <div class="section-actions">
      <button class="btn btn-outline btn-sm" id="view-quick" onclick="setView('quick')">⭐ Publication rapide</button>
      <button class="btn btn-outline btn-sm" id="view-kanban" onclick="setView('kanban')">📋 Kanban</button>
      <button class="btn btn-outline btn-sm" id="view-table" onclick="setView('table')">📊 Table complète</button>
      <button class="btn btn-primary" onclick="openAdd()">+ Nouveau post</button>
    </div>
  </div>

  <!-- VUE PUBLICATION RAPIDE -->
  <div id="quick-view" style="padding:20px">
    <div id="quick-body"></div>
  </div>

  <!-- VUE KANBAN -->
  <div id="kanban-view" style="display:none;padding:20px">
    <div class="kanban-board">
      <div class="kanban-col" data-statut="À programmer"><div class="kanban-col-title">🗓 À programmer</div><div class="kanban-items" id="kanban-aprog"></div></div>
      <div class="kanban-col" data-statut="Prêt"><div class="kanban-col-title">✅ Prêt</div><div class="kanban-items" id="kanban-pret"></div></div>
      <div class="kanban-col" data-statut="Publié"><div class="kanban-col-title">🚀 Publié</div><div class="kanban-items" id="kanban-publie"></div></div>
      <div class="kanban-col" data-statut="Republié page"><div class="kanban-col-title">🔁 Republié page</div><div class="kanban-items" id="kanban-republie"></div></div>
    </div>
  </div>

  <!-- VUE TABLE COMPLÈTE -->
  <div id="table-view" style="display:none">
    <div class="filters-bar">
      <input type="text" id="search-li" placeholder="🔍 Rechercher...">
      <select id="filter-li-statut"><option value="">Tous statuts</option><option>À programmer</option><option>Prêt</option><option>Publié</option><option>Republié page</option></select>
      <select id="filter-li-secteur"><option value="">Tous secteurs</option><option>Offset</option><option>Textile</option><option>Agri-food</option><option>Transversal</option><option>Reglementation</option></select>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>#</th><th>Titre</th><th>Date</th><th>Heure</th><th>Secteur</th><th>Statut</th><th>Actions</th></tr></thead>
        <tbody id="li-body"><tr><td colspan="7" style="text-align:center;padding:30px;color:#999">Chargement...</td></tr></tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modal-li">
  <div class="modal" style="max-width:700px">
    <div class="modal-header">
      <div class="modal-title" id="modal-li-title">Nouveau post LinkedIn</div>
      <button class="modal-close" onclick="RYBSEN.closeModal('modal-li')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="li-id">
      <div class="form-grid">
        <div class="form-group"><label>N°</label><input type="number" id="li-numero" placeholder="13"></div>
        <div class="form-group"><label>Titre *</label><input type="text" id="li-titre" placeholder="Titre du post"></div>
        <div class="form-group"><label>Semaine</label><input type="text" id="li-semaine" placeholder="Sem. 5"></div>
        <div class="form-group"><label>Jour</label>
          <select id="li-jour"><option>Lundi</option><option>Mardi</option><option>Mercredi</option><option>Jeudi</option><option>Vendredi</option><option>Samedi</option><option>Dimanche</option></select>
        </div>
        <div class="form-group"><label>Heure</label><input type="time" id="li-heure" value="08:00"></div>
        <div class="form-group"><label>Date de publication</label><input type="date" id="li-date"></div>
        <div class="form-group"><label>Secteur</label>
          <select id="li-secteur"><option>Transversal</option><option>Offset</option><option>Textile</option><option>Agri-food</option><option>Reglementation</option><option>Autre</option></select>
        </div>
        <div class="form-group"><label>Statut</label>
          <select id="li-statut"><option>À programmer</option><option>Prêt</option><option>Publié</option><option>Republié page</option></select>
        </div>
        <div class="form-group full"><label>Texte du post</label><textarea id="li-texte" style="min-height:140px" placeholder="Texte intégral du post LinkedIn..."></textarea></div>
        <div class="form-group full"><label>Prompt image IA</label><textarea id="li-prompt" placeholder="Prompt pour générer l'image associée..."></textarea></div>
        <div class="form-group full"><label>Hashtags</label><input type="text" id="li-hashtags" placeholder="#WaterTech #DeepTech #RYBSEN"></div>
        <div class="form-group full"><label>Lien du post (une fois publié)</label><input type="url" id="li-lien" placeholder="https://linkedin.com/posts/..."></div>
        <div class="form-group full"><label>Notes</label><textarea id="li-notes"></textarea></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="RYBSEN.closeModal('modal-li')">Annuler</button>
      <button class="btn btn-primary" onclick="saveLi()">Enregistrer</button>
    </div>
  </div>
</div>

<style>
.kanban-board { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }
.kanban-col { background: var(--cream); border-radius: 10px; padding: 12px; min-height: 200px; }
.kanban-col-title { font-size: 12px; font-weight: 700; color: var(--navy); margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
.kanban-card { background: white; border-radius: 8px; padding: 10px 12px; margin-bottom: 8px; border: 1px solid var(--border); cursor: pointer; font-size: 13px; }
.kanban-card:hover { border-color: var(--teal); }
.kanban-card .kc-title { font-weight: 600; color: var(--navy); margin-bottom: 4px; }
.kanban-card .kc-date { font-size: 11px; color: var(--text-muted); }
.kanban-actions { display: flex; gap: 4px; margin-top: 6px; }
@media (max-width: 768px) { .kanban-board { grid-template-columns: 1fr; } }
</style>

<script>
let allLi = [];
let currentView = 'quick';
const statColorsLi = {'À programmer':'badge-grey','Prêt':'badge-gold','Publié':'badge-teal','Republié page':'badge-green'};

async function loadLi() {
  allLi = await RYBSEN.api('linkedin_list');
  if (allLi.error) { RYBSEN.toast(allLi.error, 'error'); return; }
  updateKPIs();
  renderQuick();
  renderKanban();
  renderTable();
}

function updateKPIs() {
  const today = new Date().toISOString().split('T')[0];
  document.getElementById('kpi-li-total').textContent = allLi.length;
  document.getElementById('kpi-li-aprog').textContent = allLi.filter(p => p.statut === 'À programmer').length;
  document.getElementById('kpi-li-prets').textContent = allLi.filter(p => p.statut === 'Prêt').length;
  document.getElementById('kpi-li-today').textContent = allLi.filter(p => p.date_publication === today).length;
}

function setView(v) {
  currentView = v;
  ['quick','kanban','table'].forEach(x => {
    document.getElementById(x + '-view').style.display = x === v ? 'block' : 'none';
    document.getElementById('view-' + x).classList.toggle('btn-primary', x === v);
    document.getElementById('view-' + x).classList.toggle('btn-outline', x !== v);
  });
}

function renderQuick() {
  const today = new Date().toISOString().split('T')[0];
  const upcoming = allLi.filter(p => p.statut !== 'Publié' && p.statut !== 'Republié page' && (!p.date_publication || p.date_publication >= today))
    .sort((a,b) => (a.date_publication||'9999').localeCompare(b.date_publication||'9999'));
  const body = document.getElementById('quick-body');
  if (!upcoming.length) {
    body.innerHTML = '<div class="empty-state"><div class="empty-icon">✅</div><p>Aucun post à venir. Ajoute le prochain post du calendrier éditorial.</p></div>';
    return;
  }
  body.innerHTML = upcoming.map(p => {
    const isToday = p.date_publication === today;
    return `<div class="section-card" style="${isToday?'border-color:#E8A44C;border-width:2px':''}">
      <div class="section-header">
        <div class="section-title">${isToday?'🔴 AUJOURD\'HUI · ':''}#${p.numero||'—'} ${escapeHtmlLi(p.titre)}</div>
        <span class="badge ${statColorsLi[p.statut]}">${p.statut}</span>
      </div>
      <div style="padding:16px 20px">
        <p style="font-size:13px;color:#6B7A8A;margin-bottom:10px">${p.date_publication?new Date(p.date_publication).toLocaleDateString('fr-FR',{weekday:'long',day:'numeric',month:'long'}):'Date non définie'} à ${p.heure?p.heure.substring(0,5):'08:00'} · ${p.secteur}</p>
        <div style="background:#FAFAF7;border-radius:8px;padding:14px;margin-bottom:10px;font-size:13px;white-space:pre-wrap">${escapeHtmlLi(p.texte_post)||'Pas de texte rédigé encore'}</div>
        ${p.hashtags?`<p style="font-size:12px;color:#4A9B8F;margin-bottom:10px">${escapeHtmlLi(p.hashtags)}</p>`:''}
        ${p.prompt_image?`<details style="margin-bottom:10px"><summary style="font-size:12px;cursor:pointer;color:#999">🎨 Prompt image IA</summary><p style="font-size:12px;color:#666;margin-top:6px">${escapeHtmlLi(p.prompt_image)}</p></details>`:''}
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <button onclick="quickAdvance(${p.id}, '${p.statut}')" class="btn btn-teal btn-sm">Marquer ${nextStatut(p.statut)}</button>
          <button onclick="editLiById(${p.id})" class="btn btn-outline btn-sm">✏️ Modifier</button>
        </div>
      </div>
    </div>`;
  }).join('');
}

function nextStatut(s) {
  const order = ['À programmer','Prêt','Publié','Republié page'];
  const idx = order.indexOf(s);
  return idx < order.length - 1 ? '« ' + order[idx+1] + ' »' : 'terminé';
}

async function quickAdvance(id, current) {
  const order = ['À programmer','Prêt','Publié','Republié page'];
  const idx = order.indexOf(current);
  if (idx >= order.length - 1) return;
  const r = await RYBSEN.api('linkedin_set_statut', { id, statut: order[idx+1] });
  if (r.ok) { RYBSEN.toast('Statut mis à jour ✓'); loadLi(); }
}

function renderKanban() {
  const cols = {'À programmer':'kanban-aprog','Prêt':'kanban-pret','Publié':'kanban-publie','Republié page':'kanban-republie'};
  Object.entries(cols).forEach(([statut, elId]) => {
    const items = allLi.filter(p => p.statut === statut);
    document.getElementById(elId).innerHTML = items.length ? items.map(p => `
      <div class="kanban-card" onclick="editLiById(${p.id})">
        <div class="kc-title">#${p.numero||'—'} ${escapeHtmlLi(p.titre)}</div>
        <div class="kc-date">${p.date_publication?new Date(p.date_publication).toLocaleDateString('fr-FR'):'Pas de date'} · ${p.secteur}</div>
      </div>`).join('') : '<p style="font-size:12px;color:#999;text-align:center;padding:10px">Vide</p>';
  });
}

function renderTable() {
  const q = (document.getElementById('search-li').value || '').toLowerCase();
  const s = document.getElementById('filter-li-statut').value;
  const sec = document.getElementById('filter-li-secteur').value;
  const filtered = allLi.filter(p =>
    (!q || (p.titre + (p.texte_post||'')).toLowerCase().includes(q)) &&
    (!s || p.statut === s) && (!sec || p.secteur === sec));
  const body = document.getElementById('li-body');
  if (!filtered.length) { body.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:30px;color:#999">Aucun post</td></tr>'; return; }
  body.innerHTML = filtered.map(p => `<tr>
    <td>${p.numero||'—'}</td>
    <td><strong>${escapeHtmlLi(p.titre)}</strong></td>
    <td>${p.date_publication?new Date(p.date_publication).toLocaleDateString('fr-FR'):'—'}</td>
    <td>${p.heure?p.heure.substring(0,5):'—'}</td>
    <td><span class="badge badge-grey">${p.secteur}</span></td>
    <td><span class="badge ${statColorsLi[p.statut]}">${p.statut}</span></td>
    <td><button onclick="editLiById(${p.id})" class="btn btn-outline btn-sm">✏️</button>
        <button onclick="delLi(${p.id})" class="btn btn-danger btn-sm">🗑</button></td>
  </tr>`).join('');
}

function escapeHtmlLi(str) {
  if (!str) return '';
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

function openAdd() {
  document.getElementById('modal-li-title').textContent = 'Nouveau post LinkedIn';
  document.getElementById('li-id').value = '';
  ['li-numero','li-titre','li-semaine','li-date','li-texte','li-prompt','li-hashtags','li-lien','li-notes'].forEach(i => document.getElementById(i).value = '');
  document.getElementById('li-jour').value = 'Lundi';
  document.getElementById('li-heure').value = '08:00';
  document.getElementById('li-secteur').value = 'Transversal';
  document.getElementById('li-statut').value = 'À programmer';
  RYBSEN.openModal('modal-li');
}

function editLiById(id) {
  const p = allLi.find(x => x.id === id);
  if (!p) return;
  document.getElementById('modal-li-title').textContent = 'Modifier le post';
  document.getElementById('li-id').value = p.id;
  document.getElementById('li-numero').value = p.numero || '';
  document.getElementById('li-titre').value = p.titre;
  document.getElementById('li-semaine').value = p.semaine || '';
  document.getElementById('li-jour').value = p.jour;
  document.getElementById('li-heure').value = p.heure ? p.heure.substring(0,5) : '08:00';
  document.getElementById('li-date').value = p.date_publication || '';
  document.getElementById('li-secteur').value = p.secteur;
  document.getElementById('li-statut').value = p.statut;
  document.getElementById('li-texte').value = p.texte_post || '';
  document.getElementById('li-prompt').value = p.prompt_image || '';
  document.getElementById('li-hashtags').value = p.hashtags || '';
  document.getElementById('li-lien').value = p.lien_post || '';
  document.getElementById('li-notes').value = p.notes || '';
  RYBSEN.openModal('modal-li');
}

async function saveLi() {
  const titre = document.getElementById('li-titre').value.trim();
  if (!titre) { RYBSEN.toast('Le titre est requis', 'error'); return; }
  const data = {
    id: document.getElementById('li-id').value,
    numero: document.getElementById('li-numero').value || null,
    titre, semaine: document.getElementById('li-semaine').value,
    jour: document.getElementById('li-jour').value,
    heure: document.getElementById('li-heure').value,
    date_publication: document.getElementById('li-date').value || null,
    secteur: document.getElementById('li-secteur').value,
    statut: document.getElementById('li-statut').value,
    texte_post: document.getElementById('li-texte').value,
    prompt_image: document.getElementById('li-prompt').value,
    hashtags: document.getElementById('li-hashtags').value,
    lien_post: document.getElementById('li-lien').value,
    notes: document.getElementById('li-notes').value
  };
  const r = await RYBSEN.api('linkedin_save', data);
  if (r.ok) { RYBSEN.closeModal('modal-li'); RYBSEN.toast('Post enregistré ✓'); loadLi(); }
  else RYBSEN.toast(r.error || 'Erreur', 'error');
}

async function delLi(id) {
  if (!RYBSEN.confirmDelete('Supprimer ce post du calendrier ?')) return;
  const r = await RYBSEN.api('linkedin_delete', { id });
  if (r.ok) { RYBSEN.toast('Supprimé'); loadLi(); }
}

['search-li','filter-li-statut','filter-li-secteur'].forEach(id => {
  const el = document.getElementById(id);
  if (el) el.addEventListener('input', renderTable);
});

setView('quick');
loadLi();
</script>
<?php require_once '../includes/footer.php'; ?>
