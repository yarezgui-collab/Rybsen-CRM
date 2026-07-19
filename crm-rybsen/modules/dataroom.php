<?php
require_once '../config.php';
$pageTitle = 'Data Room Investisseurs';
$activePage = 'dataroom';
require_once '../includes/header.php';
if (($user['role'] ?? '') !== 'admin'): ?>
  <div class="alert-box urgent">⛔ Accès réservé aux administrateurs.</div>
<?php require_once '../includes/footer.php'; exit; endif; ?>

<style>
.dr-tabs { display:flex; gap:8px; margin-bottom:20px; flex-wrap:wrap; }
.dr-tab { padding:9px 20px; border-radius:22px; border:2px solid var(--border); background:#fff; color:#666; cursor:pointer; font-size:13px; font-weight:600; transition:all .2s; }
.dr-tab.active { background:var(--navy); border-color:var(--navy); color:#fff; }
.dr-tab .cnt { display:inline-block; background:rgba(255,255,255,.25); border-radius:10px; padding:1px 8px; font-size:11px; margin-left:5px; }
.dr-tab:not(.active) .cnt { background:#f0f0f0; color:#888; }
.dr-pane { display:none; }
.dr-pane.active { display:block; }

.tl-chart { display:flex; align-items:flex-end; gap:6px; height:120px; padding:10px 4px 0; }
.tl-col { flex:1; display:flex; flex-direction:column; justify-content:flex-end; gap:2px; align-items:center; min-width:0; }
.tl-bar-v { width:100%; max-width:34px; border-radius:4px 4px 0 0; background:var(--teal); min-height:2px; }
.tl-bar-l { width:100%; max-width:34px; border-radius:4px 4px 0 0; background:var(--navy); min-height:2px; }
.tl-day { font-size:9px; color:#999; margin-top:5px; white-space:nowrap; }

.sugg-card { border:1px solid var(--border); border-radius:12px; padding:16px 18px; margin-bottom:14px; background:#fff; }
.sugg-card.nouveau { border-left:4px solid var(--gold); }
.sugg-card.répondu { border-left:4px solid #16a34a; opacity:.85; }
.pwd-row { display:flex; gap:6px; }
.copy-btn { cursor:pointer; }
</style>

<div class="dr-tabs" id="dr-tabs">
  <button class="dr-tab active" data-pane="dash">📊 Dashboard</button>
  <button class="dr-tab" data-pane="acces">👥 Accès investisseurs <span class="cnt" id="cnt-acces">…</span></button>
  <button class="dr-tab" data-pane="docs">📄 Documents <span class="cnt" id="cnt-docs">…</span></button>
  <button class="dr-tab" data-pane="suggs">💬 Suggestions <span class="cnt" id="cnt-suggs">…</span></button>
  <button class="dr-tab" data-pane="logs">🕵️ Journal d'audit</button>
</div>

<!-- ═══ DASHBOARD ═══ -->
<div class="dr-pane active" id="pane-dash">
  <div class="kpi-grid" id="dr-kpis"></div>
  <div class="dash-grid">
    <div class="section-card">
      <div class="section-header"><div class="section-title">📈 Activité — 14 derniers jours</div></div>
      <div style="padding:16px 20px">
        <div class="tl-chart" id="tl-chart"></div>
        <div style="display:flex;gap:16px;font-size:11px;color:#888;margin-top:10px">
          <span><span style="display:inline-block;width:10px;height:10px;background:var(--navy);border-radius:2px"></span> Connexions</span>
          <span><span style="display:inline-block;width:10px;height:10px;background:var(--teal);border-radius:2px"></span> Documents consultés</span>
        </div>
      </div>
    </div>
    <div class="section-card">
      <div class="section-header"><div class="section-title">🌍 Connexions par pays</div></div>
      <div style="padding:14px 20px" id="pays-list"></div>
    </div>
  </div>
  <div class="dash-grid" style="margin-top:20px">
    <div class="section-card">
      <div class="section-header"><div class="section-title">🔥 Documents les plus consultés</div></div>
      <div class="table-wrap"><table>
        <thead><tr><th>Document</th><th>Vues</th><th>Lecteurs</th></tr></thead>
        <tbody id="top-docs-body"></tbody>
      </table></div>
    </div>
    <div class="section-card">
      <div class="section-header"><div class="section-title">👥 Activité par investisseur</div></div>
      <div class="table-wrap"><table>
        <thead><tr><th>Investisseur</th><th>NDA</th><th>Dernière connexion</th><th>Pays / IP</th><th>Vues</th></tr></thead>
        <tbody id="inv-activity-body"></tbody>
      </table></div>
    </div>
  </div>
</div>

<!-- ═══ ACCÈS ═══ -->
<div class="dr-pane" id="pane-acces">
  <div class="alert-box info">🔗 Portail investisseur : <strong>https://crm.rybsen.com/dataroom/</strong> — communiquez ce lien avec l'email et le mot de passe que vous créez ici.</div>
  <div class="section-card">
    <div class="section-header">
      <div class="section-title">👥 Comptes d'accès Data Room</div>
      <div class="section-actions">
        <button class="btn btn-primary" onclick="openAccesAdd()">+ Nouvel accès</button>
      </div>
    </div>
    <div class="table-wrap"><table>
      <thead><tr><th>Investisseur</th><th>Email</th><th>NDA</th><th>Dernière connexion</th><th>Pays / IP</th><th>Vues</th><th>Expire</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody id="acces-body"><tr><td colspan="9" style="text-align:center;padding:30px;color:#999">Chargement…</td></tr></tbody>
    </table></div>
  </div>
</div>

<!-- ═══ DOCUMENTS ═══ -->
<div class="dr-pane" id="pane-docs">
  <div class="section-card">
    <div class="section-header"><div class="section-title">⬆️ Ajouter un document</div></div>
    <div style="padding:20px">
      <form id="upload-form" class="form-grid" onsubmit="return uploadDoc(event)">
        <div class="form-group full">
          <label>Fichier — PDF/JPG/PNG/WEBP (30 Mo max) ou MP4/WEBM (500 Mo max) — convertir Excel/PPT en PDF</label>
          <input type="file" id="up-file" accept=".pdf,.jpg,.jpeg,.png,.webp,.mp4,.webm" required>
        </div>
        <div class="form-group"><label>Titre (FR) *</label><input type="text" id="up-titre" required placeholder="Pitch Deck 2026"></div>
        <div class="form-group"><label>Titre (EN)</label><input type="text" id="up-titre-en" placeholder="Pitch Deck 2026"></div>
        <div class="form-group">
          <label>Catégorie</label>
          <select id="up-cat">
            <option>Pitch & Vision</option><option>Produit & Technologie</option><option>Financier</option>
            <option>Juridique</option><option>Équipe</option><option>Marché & Traction</option><option>Vidéo</option><option>Autre</option>
          </select>
        </div>
        <div class="form-group"><label>Version</label><input type="text" id="up-version" value="v1"></div>
        <div class="form-group full"><label>Description</label><input type="text" id="up-desc" placeholder="Visible par les investisseurs"></div>
        <div class="form-group full" id="up-progress-wrap" style="display:none">
          <div style="background:#f0f0f0;border-radius:8px;height:10px;overflow:hidden">
            <div id="up-progress-bar" style="background:var(--teal);height:100%;width:0%;transition:width .15s"></div>
          </div>
          <div id="up-progress-txt" style="font-size:12px;color:#888;margin-top:4px">0 %</div>
        </div>
        <div class="form-group full">
          <button type="submit" class="btn btn-teal" id="up-btn">⬆️ Uploader</button>
        </div>
      </form>
    </div>
  </div>
  <div class="section-card">
    <div class="section-header"><div class="section-title">📄 Documents en ligne</div></div>
    <div class="table-wrap"><table>
      <thead><tr><th>Ordre</th><th>Catégorie</th><th>Titre</th><th>Version</th><th>Taille</th><th>Vues</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody id="docs-body"><tr><td colspan="8" style="text-align:center;padding:30px;color:#999">Chargement…</td></tr></tbody>
    </table></div>
  </div>
</div>

<!-- ═══ SUGGESTIONS ═══ -->
<div class="dr-pane" id="pane-suggs">
  <div id="suggs-list"></div>
</div>

<!-- ═══ JOURNAL ═══ -->
<div class="dr-pane" id="pane-logs">
  <div class="section-card">
    <div class="section-header">
      <div class="section-title">🕵️ Journal d'audit (300 derniers événements)</div>
      <div class="section-actions">
        <select id="logs-filter" class="btn btn-outline btn-sm" style="padding:6px 10px" onchange="loadLogs()">
          <option value="0">Tous les investisseurs</option>
        </select>
      </div>
    </div>
    <div class="table-wrap"><table>
      <thead><tr><th>Date</th><th>Investisseur</th><th>Action</th><th>Document</th><th>IP</th><th>Localisation</th></tr></thead>
      <tbody id="logs-body"><tr><td colspan="6" style="text-align:center;padding:30px;color:#999">Chargement…</td></tr></tbody>
    </table></div>
  </div>
</div>

<!-- MODAL ACCÈS -->
<div class="modal-overlay" id="modal-acces">
  <div class="modal" style="max-width:680px">
    <div class="modal-header">
      <div class="modal-title" id="modal-acces-title">Nouvel accès Data Room</div>
      <button class="modal-close" onclick="RYBSEN.closeModal('modal-acces')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="ac-id">
      <div class="form-grid">
        <div class="form-group"><label>Nom *</label><input type="text" id="ac-nom"></div>
        <div class="form-group"><label>Prénom</label><input type="text" id="ac-prenom"></div>
        <div class="form-group"><label>Email (identifiant) *</label><input type="email" id="ac-email"></div>
        <div class="form-group"><label>Société / Fonds</label><input type="text" id="ac-societe"></div>
        <div class="form-group"><label>Pays</label><input type="text" id="ac-pays"></div>
        <div class="form-group"><label>Téléphone</label><input type="text" id="ac-tel"></div>
        <div class="form-group">
          <label>Langue du portail</label>
          <select id="ac-langue"><option value="fr">Français</option><option value="en">English</option></select>
        </div>
        <div class="form-group"><label>Accès expire le (vide = illimité)</label><input type="date" id="ac-exp"></div>
        <div class="form-group full">
          <label>Mot de passe <span id="pwd-hint">(requis, 8 car. min)</span></label>
          <div class="pwd-row">
            <input type="text" id="ac-pwd" placeholder="••••••••" style="flex:1" autocomplete="new-password">
            <button type="button" class="btn btn-outline" onclick="genPwd()">🎲 Générer</button>
            <button type="button" class="btn btn-outline copy-btn" onclick="copyPwd()">📋 Copier</button>
          </div>
        </div>
        <div class="form-group full"><label>Notes internes</label><textarea id="ac-notes" rows="2"></textarea></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="RYBSEN.closeModal('modal-acces')">Annuler</button>
      <button class="btn btn-primary" onclick="saveAcces()">Enregistrer</button>
    </div>
  </div>
</div>

<!-- MODAL EDIT DOC -->
<div class="modal-overlay" id="modal-doc-edit">
  <div class="modal" style="max-width:620px">
    <div class="modal-header">
      <div class="modal-title">Modifier le document</div>
      <button class="modal-close" onclick="RYBSEN.closeModal('modal-doc-edit')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="dc-id">
      <div class="form-grid">
        <div class="form-group"><label>Titre (FR) *</label><input type="text" id="dc-titre"></div>
        <div class="form-group"><label>Titre (EN)</label><input type="text" id="dc-titre-en"></div>
        <div class="form-group">
          <label>Catégorie</label>
          <select id="dc-cat">
            <option>Pitch & Vision</option><option>Produit & Technologie</option><option>Financier</option>
            <option>Juridique</option><option>Équipe</option><option>Marché & Traction</option><option>Vidéo</option><option>Autre</option>
          </select>
        </div>
        <div class="form-group"><label>Version</label><input type="text" id="dc-version"></div>
        <div class="form-group"><label>Ordre d'affichage</label><input type="number" id="dc-ordre" value="0"></div>
        <div class="form-group">
          <label>Visible</label>
          <select id="dc-actif"><option value="1">Oui</option><option value="0">Non (masqué)</option></select>
        </div>
        <div class="form-group full"><label>Description</label><input type="text" id="dc-desc"></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="RYBSEN.closeModal('modal-doc-edit')">Annuler</button>
      <button class="btn btn-primary" onclick="saveDocMeta()">Enregistrer</button>
    </div>
  </div>
</div>

<!-- MODAL DOCUMENTS PAR INVESTISSEUR -->
<div class="modal-overlay" id="modal-acces-docs">
  <div class="modal" style="max-width:640px">
    <div class="modal-header">
      <div class="modal-title" id="acdocs-title">Documents visibles</div>
      <button class="modal-close" onclick="RYBSEN.closeModal('modal-acces-docs')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="acdocs-acces-id">
      <div class="alert-box info" style="margin-bottom:14px">
        ☑️ Coché = <strong>visible</strong> par cet investisseur · décoché = <strong>masqué</strong>. Par défaut tout est visible.
      </div>
      <div style="display:flex;gap:8px;margin-bottom:12px">
        <button type="button" class="btn btn-outline btn-sm" onclick="acdocsAll(true)">Tout cocher</button>
        <button type="button" class="btn btn-outline btn-sm" onclick="acdocsAll(false)">Tout décocher</button>
      </div>
      <div id="acdocs-list" style="max-height:52vh;overflow-y:auto"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="RYBSEN.closeModal('modal-acces-docs')">Annuler</button>
      <button class="btn btn-primary" onclick="saveAccesDocs()">Enregistrer</button>
    </div>
  </div>
</div>

<script>
const e = RYBSEN.escape.bind(RYBSEN);
let allAcces = [], allDrDocs = [];

const actionLabels = {
  login:'🟢 Connexion', login_echec:'🔴 Échec connexion', logout:'⚪ Déconnexion',
  nda_vue:'📜 NDA consulté', nda_signe:'✍️ NDA signé', vue_document:'👁 Document consulté',
  suggestion:'💬 Suggestion', acces_refuse:'⛔ Accès refusé'
};
const fmtDT = d => d ? new Date(d.replace(' ','T')).toLocaleString('fr-FR',{day:'2-digit',month:'2-digit',year:'2-digit',hour:'2-digit',minute:'2-digit'}) : '—';
const fmtSize = b => b >= 1048576 ? (b/1048576).toFixed(1)+' Mo' : Math.round(b/1024)+' Ko';

// ── TABS ──
document.getElementById('dr-tabs').addEventListener('click', ev => {
  const btn = ev.target.closest('.dr-tab');
  if (!btn) return;
  document.querySelectorAll('.dr-tab').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.dr-pane').forEach(p => p.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('pane-' + btn.dataset.pane).classList.add('active');
  ({dash:loadStats, acces:loadAcces, docs:loadDrDocs, suggs:loadSuggs, logs:loadLogs})[btn.dataset.pane]();
});

// ── DASHBOARD ──
async function loadStats() {
  const s = await RYBSEN.api('dr_stats');
  if (s.error) { RYBSEN.toast(s.error, 'error'); return; }
  document.getElementById('dr-kpis').innerHTML = `
    <div class="kpi-card navy"><div class="kpi-label">Accès actifs</div><div class="kpi-value">${s.acces_actifs}</div><div class="kpi-sub">${s.acces_total} au total</div></div>
    <div class="kpi-card teal"><div class="kpi-label">NDA signés</div><div class="kpi-value">${s.nda_signes}</div><div class="kpi-sub">sur ${s.acces_total} invités</div></div>
    <div class="kpi-card"><div class="kpi-label">Connexions (7 j)</div><div class="kpi-value">${s.connexions_7j}</div></div>
    <div class="kpi-card"><div class="kpi-label">Documents vus (7 j)</div><div class="kpi-value">${s.vues_7j}</div></div>
    <div class="kpi-card gold"><div class="kpi-label">Suggestions à traiter</div><div class="kpi-value">${s.sugg_nouvelles}</div></div>
    <div class="kpi-card ${s.echecs_login_7j > 5 ? 'red' : ''}"><div class="kpi-label">Échecs login (7 j)</div><div class="kpi-value">${s.echecs_login_7j}</div></div>`;

  // Timeline
  const days = {};
  for (let i = 13; i >= 0; i--) {
    const d = new Date(Date.now() - i * 86400000);
    days[d.toISOString().slice(0,10)] = { logins: 0, vues: 0 };
  }
  (s.timeline || []).forEach(r => { if (days[r.jour]) days[r.jour] = { logins:+r.logins, vues:+r.vues }; });
  const max = Math.max(1, ...Object.values(days).flatMap(d => [d.logins, d.vues]));
  document.getElementById('tl-chart').innerHTML = Object.entries(days).map(([j, d]) => `
    <div class="tl-col" title="${j} — ${d.logins} connexions, ${d.vues} vues">
      <div class="tl-bar-l" style="height:${Math.round(d.logins / max * 90)}px"></div>
      <div class="tl-bar-v" style="height:${Math.round(d.vues / max * 90)}px"></div>
      <div class="tl-day">${j.slice(8,10)}/${j.slice(5,7)}</div>
    </div>`).join('');

  // Pays
  const maxPays = Math.max(1, ...(s.pays || []).map(p => +p.n));
  document.getElementById('pays-list').innerHTML = (s.pays || []).length
    ? s.pays.map(p => `
      <div style="margin-bottom:10px">
        <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:3px">
          <strong>${e(p.pays)}</strong><span>${p.n}</span>
        </div>
        <div class="progress-bar"><div class="progress-fill" style="width:${Math.round(p.n / maxPays * 100)}%"></div></div>
      </div>`).join('')
    : '<div style="color:#999;font-size:13px;padding:10px 0">Aucune connexion géolocalisée pour le moment</div>';

  // Top docs
  document.getElementById('top-docs-body').innerHTML = (s.top_docs || []).length
    ? s.top_docs.map(d => `<tr><td><strong>${e(d.titre)}</strong></td><td>${d.vues}</td><td>${d.lecteurs}</td></tr>`).join('')
    : '<tr><td colspan="3" style="text-align:center;color:#999;padding:20px">Aucune consultation</td></tr>';

  // Par investisseur
  document.getElementById('inv-activity-body').innerHTML = (s.par_investisseur || []).length
    ? s.par_investisseur.map(a => `<tr>
        <td><strong>${e((a.prenom||'') + ' ' + a.nom)}</strong>${a.societe ? `<br><small style="color:#999">${e(a.societe)}</small>` : ''}</td>
        <td>${+a.nda_signe ? '<span class="badge badge-green">✓ Signé</span>' : '<span class="badge badge-grey">En attente</span>'}</td>
        <td>${fmtDT(a.derniere_connexion)}</td>
        <td>${a.dernier_pays ? e(a.dernier_pays) : '—'}${a.derniere_ip ? `<br><small style="color:#999;font-family:monospace">${e(a.derniere_ip)}</small>` : ''}</td>
        <td><strong>${a.vues}</strong></td>
      </tr>`).join('')
    : '<tr><td colspan="5" style="text-align:center;color:#999;padding:20px">Aucun accès actif</td></tr>';
}

// ── ACCÈS ──
async function loadAcces() {
  allAcces = await RYBSEN.api('dr_acces_list');
  if (allAcces.error) { RYBSEN.toast(allAcces.error, 'error'); return; }
  document.getElementById('cnt-acces').textContent = allAcces.length;
  const body = document.getElementById('acces-body');
  body.innerHTML = allAcces.length ? allAcces.map(a => `<tr>
    <td><strong>${e((a.prenom||'') + ' ' + a.nom)}</strong>${a.societe ? `<br><small style="color:#999">${e(a.societe)}</small>` : ''}</td>
    <td style="font-family:monospace;font-size:12px">${e(a.email)}</td>
    <td>${+a.nda_signe ? `<span class="badge badge-green" title="Signé par ${e(a.nda_nom_signe||'')} le ${fmtDT(a.nda_date)}">✓ ${fmtDT(a.nda_date)}</span>` : '<span class="badge badge-grey">En attente</span>'}</td>
    <td>${fmtDT(a.derniere_connexion)}<br><small style="color:#999">${a.nb_connexions} connexion(s)</small></td>
    <td>${a.dernier_pays ? e(a.dernier_pays) : '—'}${a.derniere_ip ? `<br><small style="color:#999;font-family:monospace">${e(a.derniere_ip)}</small>` : ''}</td>
    <td>${a.nb_vues} 👁 · ${a.nb_suggestions} 💬</td>
    <td>${a.date_expiration ? new Date(a.date_expiration).toLocaleDateString('fr-FR') : '∞'}</td>
    <td>${+a.actif ? '<span class="badge badge-teal">Actif</span>' : '<span class="badge badge-red">Révoqué</span>'}</td>
    <td style="white-space:nowrap">
      <button class="btn btn-outline btn-sm" onclick="openAccesDocs(${a.id})" title="Documents visibles">🗂${+a.nb_masques ? ` <span style="color:var(--gold)">${a.nb_masques}🚫</span>` : ''}</button>
      <button class="btn btn-outline btn-sm" onclick="openAccesEdit(${a.id})" title="Modifier">✏️</button>
      <button class="btn btn-outline btn-sm" onclick="toggleAcces(${a.id})" title="${+a.actif ? 'Révoquer' : 'Réactiver'}">${+a.actif ? '🚫' : '✅'}</button>
      <button class="btn btn-danger btn-sm" onclick="delAcces(${a.id})" title="Supprimer">🗑</button>
    </td>
  </tr>`).join('') : '<tr><td colspan="9" style="text-align:center;padding:30px;color:#999">Aucun accès créé — cliquez sur « + Nouvel accès »</td></tr>';
}

function genPwd() {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!#%+';
  let p = '';
  const buf = new Uint32Array(14);
  crypto.getRandomValues(buf);
  buf.forEach(v => p += chars[v % chars.length]);
  document.getElementById('ac-pwd').value = p;
}
function copyPwd() {
  const v = document.getElementById('ac-pwd').value;
  if (!v) return;
  navigator.clipboard.writeText(v).then(() => RYBSEN.toast('Mot de passe copié 📋'));
}

function openAccesAdd() {
  document.getElementById('modal-acces-title').textContent = 'Nouvel accès Data Room';
  ['ac-id','ac-nom','ac-prenom','ac-email','ac-societe','ac-pays','ac-tel','ac-exp','ac-pwd','ac-notes']
    .forEach(id => document.getElementById(id).value = '');
  document.getElementById('ac-langue').value = 'fr';
  document.getElementById('pwd-hint').textContent = '(requis, 8 car. min)';
  genPwd();
  RYBSEN.openModal('modal-acces');
}
function openAccesEdit(id) {
  const a = allAcces.find(x => x.id == id);
  if (!a) return;
  document.getElementById('modal-acces-title').textContent = 'Modifier — ' + a.email;
  document.getElementById('ac-id').value = a.id;
  document.getElementById('ac-nom').value = a.nom || '';
  document.getElementById('ac-prenom').value = a.prenom || '';
  document.getElementById('ac-email').value = a.email || '';
  document.getElementById('ac-societe').value = a.societe || '';
  document.getElementById('ac-pays').value = a.pays || '';
  document.getElementById('ac-tel').value = a.telephone || '';
  document.getElementById('ac-langue').value = a.langue || 'fr';
  document.getElementById('ac-exp').value = a.date_expiration || '';
  document.getElementById('ac-pwd').value = '';
  document.getElementById('ac-notes').value = a.notes || '';
  document.getElementById('pwd-hint').textContent = '(laisser vide pour ne pas changer)';
  RYBSEN.openModal('modal-acces');
}
async function saveAcces() {
  const id = document.getElementById('ac-id').value;
  const r = await RYBSEN.api('dr_acces_save', {
    id,
    nom: document.getElementById('ac-nom').value.trim(),
    prenom: document.getElementById('ac-prenom').value.trim(),
    email: document.getElementById('ac-email').value.trim(),
    societe: document.getElementById('ac-societe').value.trim(),
    pays: document.getElementById('ac-pays').value.trim(),
    telephone: document.getElementById('ac-tel').value.trim(),
    langue: document.getElementById('ac-langue').value,
    date_expiration: document.getElementById('ac-exp').value || null,
    password: document.getElementById('ac-pwd').value,
    notes: document.getElementById('ac-notes').value,
    actif: 1
  });
  if (r.ok) { RYBSEN.closeModal('modal-acces'); RYBSEN.toast('Accès enregistré ✓'); loadAcces(); }
  else RYBSEN.toast(r.error || 'Erreur', 'error');
}
async function toggleAcces(id) {
  const r = await RYBSEN.api('dr_acces_toggle', { id });
  if (r.ok) { RYBSEN.toast('Statut modifié'); loadAcces(); }
}

// ── Documents visibles par investisseur ──
async function openAccesDocs(id) {
  const a = allAcces.find(x => x.id == id);
  document.getElementById('acdocs-acces-id').value = id;
  document.getElementById('acdocs-title').textContent = 'Documents visibles — ' + (a ? ((a.prenom||'') + ' ' + a.nom).trim() : '');
  const box = document.getElementById('acdocs-list');
  box.innerHTML = '<div style="padding:20px;text-align:center;color:#999">Chargement…</div>';
  RYBSEN.openModal('modal-acces-docs');
  const r = await RYBSEN.api('dr_acces_docs', { acces_id: id });
  if (r.error) { box.innerHTML = `<div style="padding:20px;color:var(--red)">${e(r.error)}</div>`; return; }
  const docs = r.documents || [];
  if (!docs.length) { box.innerHTML = '<div style="padding:20px;text-align:center;color:#999">Aucun document en ligne. Ajoutez-en dans l\'onglet Documents.</div>'; return; }
  // Groupé par catégorie
  const cats = {};
  docs.forEach(d => { (cats[d.categorie] = cats[d.categorie] || []).push(d); });
  box.innerHTML = Object.entries(cats).map(([cat, items]) => `
    <div style="margin-bottom:14px">
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--teal);margin-bottom:6px">${e(cat)}</div>
      ${items.map(d => `
        <label style="display:flex;align-items:center;gap:10px;padding:8px 10px;border:1px solid var(--border);border-radius:8px;margin-bottom:5px;cursor:pointer;${+d.actif ? '' : 'opacity:.5'}">
          <input type="checkbox" class="acdoc-cb" data-id="${d.id}" ${d.masque ? '' : 'checked'} style="width:16px;height:16px;accent-color:var(--teal)">
          <span style="flex:1"><strong>${e(d.titre)}</strong> <span style="color:#999;font-size:12px">${e(d.version)}</span>${+d.actif ? '' : ' <span class="badge badge-grey">masqué globalement</span>'}</span>
        </label>`).join('')}
    </div>`).join('');
}
function acdocsAll(check) {
  document.querySelectorAll('.acdoc-cb').forEach(cb => cb.checked = check);
}
async function saveAccesDocs() {
  const id = document.getElementById('acdocs-acces-id').value;
  const masques = [];
  document.querySelectorAll('.acdoc-cb').forEach(cb => { if (!cb.checked) masques.push(+cb.dataset.id); });
  const r = await RYBSEN.api('dr_acces_docs_save', { acces_id: id, masques });
  if (r.ok) { RYBSEN.closeModal('modal-acces-docs'); RYBSEN.toast('Accès aux documents mis à jour ✓'); loadAcces(); }
  else RYBSEN.toast(r.error || 'Erreur', 'error');
}
async function delAcces(id) {
  if (!RYBSEN.confirmDelete('Supprimer cet accès et tout son historique ?')) return;
  const r = await RYBSEN.api('dr_acces_delete', { id });
  if (r.ok) { RYBSEN.toast('Supprimé'); loadAcces(); }
}

// ── DOCUMENTS ──
async function loadDrDocs() {
  allDrDocs = await RYBSEN.api('dr_doc_list');
  if (allDrDocs.error) { RYBSEN.toast(allDrDocs.error, 'error'); return; }
  document.getElementById('cnt-docs').textContent = allDrDocs.filter(d => +d.actif).length;
  const body = document.getElementById('docs-body');
  body.innerHTML = allDrDocs.length ? allDrDocs.map(d => `<tr>
    <td>${d.ordre}</td>
    <td><span class="badge badge-navy">${e(d.categorie)}</span></td>
    <td><strong>${e(d.titre)}</strong><br><small style="color:#999">${e(d.nom_original)}</small></td>
    <td>${e(d.version)}</td>
    <td>${fmtSize(+d.taille_octets)}</td>
    <td>${d.nb_vues} 👁 (${d.nb_lecteurs} inv.)</td>
    <td>${+d.actif ? '<span class="badge badge-teal">Visible</span>' : '<span class="badge badge-grey">Masqué</span>'}</td>
    <td style="white-space:nowrap">
      <a class="btn btn-outline btn-sm" href="/dataroom/viewer.php?id=${d.id}" target="_blank" title="Prévisualiser (nécessite un compte investisseur connecté)">👁</a>
      <button class="btn btn-outline btn-sm" onclick="openDocEdit(${d.id})">✏️</button>
      <button class="btn btn-danger btn-sm" onclick="delDrDoc(${d.id})">🗑</button>
    </td>
  </tr>`).join('') : '<tr><td colspan="8" style="text-align:center;padding:30px;color:#999">Aucun document — uploadez le premier ci-dessus</td></tr>';
}

const UP_DOC_EXTS   = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
const UP_VIDEO_EXTS = ['mp4', 'webm'];
const UP_DOC_MAX   = 30  * 1024 * 1024;
const UP_VIDEO_MAX = 500 * 1024 * 1024;

function uploadResetUI() {
  const btn = document.getElementById('up-btn');
  btn.disabled = false; btn.textContent = '⬆️ Uploader';
  document.getElementById('up-progress-wrap').style.display = 'none';
  document.getElementById('up-progress-bar').style.width = '0%';
  document.getElementById('up-progress-txt').textContent = '0 %';
}

function uploadDoc(ev) {
  ev.preventDefault();
  const btn = document.getElementById('up-btn');
  const file = document.getElementById('up-file').files[0];
  if (!file) return false;

  // Validation côté client AVANT envoi — évite d'attendre la fin d'un
  // upload volumineux pour découvrir que le format est refusé (ex: HEIC/MOV
  // depuis un iPhone/iPad — convertir en JPG/MP4 avant d'importer).
  const ext = (file.name.split('.').pop() || '').toLowerCase();
  const isVideo = UP_VIDEO_EXTS.includes(ext);
  const isDoc = UP_DOC_EXTS.includes(ext);
  if (!isVideo && !isDoc) {
    RYBSEN.toast(`Format ".${ext}" non pris en charge. Utilisez PDF/JPG/PNG/WEBP ou MP4/WEBM (convertir les .heic/.mov avant import).`, 'error');
    return false;
  }
  const maxSize = isVideo ? UP_VIDEO_MAX : UP_DOC_MAX;
  if (file.size > maxSize) {
    RYBSEN.toast(`Fichier trop volumineux (${Math.round(file.size / 1024 / 1024)} Mo, max ${Math.round(maxSize / 1024 / 1024)} Mo pour ce format).`, 'error');
    return false;
  }

  const fd = new FormData();
  fd.append('fichier', file);
  fd.append('titre', document.getElementById('up-titre').value);
  fd.append('titre_en', document.getElementById('up-titre-en').value);
  fd.append('categorie', document.getElementById('up-cat').value);
  fd.append('version', document.getElementById('up-version').value);
  fd.append('description', document.getElementById('up-desc').value);

  btn.disabled = true; btn.textContent = '⏳ Upload en cours…';
  const wrap = document.getElementById('up-progress-wrap');
  const bar  = document.getElementById('up-progress-bar');
  const txt  = document.getElementById('up-progress-txt');
  wrap.style.display = '';

  const xhr = new XMLHttpRequest();
  xhr.open('POST', '/api/dataroom_upload.php');
  xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
  xhr.timeout = 20 * 60 * 1000; // 20 min — vidéos volumineuses sur réseau mobile

  xhr.upload.addEventListener('progress', e => {
    if (!e.lengthComputable) return;
    const pct = Math.round(e.loaded / e.total * 100);
    bar.style.width = pct + '%';
    txt.textContent = pct + ' %' + (pct >= 100 ? ' — traitement côté serveur…' : '');
  });

  xhr.addEventListener('load', () => {
    let r;
    try { r = JSON.parse(xhr.responseText); }
    catch (e) {
      RYBSEN.toast('Réponse serveur invalide (HTTP ' + xhr.status + '). Réessayez ou contactez l\'administrateur.', 'error');
      uploadResetUI();
      return;
    }
    if (r.ok) {
      RYBSEN.toast('Document ajouté ✓');
      document.getElementById('upload-form').reset();
      loadDrDocs();
    } else {
      RYBSEN.toast(r.error || 'Erreur upload', 'error');
    }
    uploadResetUI();
  });

  xhr.addEventListener('error', () => {
    RYBSEN.toast('Erreur réseau pendant l\'upload — vérifiez la connexion et réessayez.', 'error');
    uploadResetUI();
  });
  xhr.addEventListener('timeout', () => {
    RYBSEN.toast('Upload trop long (délai dépassé) — réessayez avec une connexion plus stable ou un fichier plus léger.', 'error');
    uploadResetUI();
  });
  xhr.addEventListener('abort', uploadResetUI);

  xhr.send(fd);
  return false;
}

function openDocEdit(id) {
  const d = allDrDocs.find(x => x.id == id);
  if (!d) return;
  document.getElementById('dc-id').value = d.id;
  document.getElementById('dc-titre').value = d.titre;
  document.getElementById('dc-titre-en').value = d.titre_en || '';
  document.getElementById('dc-cat').value = d.categorie;
  document.getElementById('dc-version').value = d.version;
  document.getElementById('dc-ordre').value = d.ordre;
  document.getElementById('dc-actif').value = d.actif;
  document.getElementById('dc-desc').value = d.description || '';
  RYBSEN.openModal('modal-doc-edit');
}
async function saveDocMeta() {
  const r = await RYBSEN.api('dr_doc_save', {
    id: document.getElementById('dc-id').value,
    titre: document.getElementById('dc-titre').value.trim(),
    titre_en: document.getElementById('dc-titre-en').value.trim(),
    categorie: document.getElementById('dc-cat').value,
    version: document.getElementById('dc-version').value,
    ordre: document.getElementById('dc-ordre').value,
    actif: document.getElementById('dc-actif').value,
    description: document.getElementById('dc-desc').value
  });
  if (r.ok) { RYBSEN.closeModal('modal-doc-edit'); RYBSEN.toast('Document mis à jour ✓'); loadDrDocs(); }
  else RYBSEN.toast(r.error || 'Erreur', 'error');
}
async function delDrDoc(id) {
  if (!RYBSEN.confirmDelete('Supprimer définitivement ce document (fichier inclus) ?')) return;
  const r = await RYBSEN.api('dr_doc_delete', { id });
  if (r.ok) { RYBSEN.toast('Supprimé'); loadDrDocs(); }
}

// ── SUGGESTIONS ──
async function loadSuggs() {
  const suggs = await RYBSEN.api('dr_sugg_list');
  if (suggs.error) { RYBSEN.toast(suggs.error, 'error'); return; }
  document.getElementById('cnt-suggs').textContent = suggs.filter(s => s.statut === 'nouveau').length;
  document.getElementById('suggs-list').innerHTML = suggs.length ? suggs.map(s => `
    <div class="sugg-card ${e(s.statut)}">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap">
        <div>
          <strong>${e((s.acces_prenom||'') + ' ' + s.acces_nom)}</strong>
          ${s.societe ? `<span style="color:#888"> · ${e(s.societe)}</span>` : ''}
          <span class="badge ${s.statut==='nouveau'?'badge-gold':s.statut==='répondu'?'badge-green':'badge-grey'}" style="margin-left:8px">${e(s.statut)}</span>
          ${s.doc_titre ? `<br><small style="color:#888">📄 À propos de : ${e(s.doc_titre)}</small>` : '<br><small style="color:#888">Question générale</small>'}
        </div>
        <small style="color:#999">${fmtDT(s.created_at)}</small>
      </div>
      <p style="margin:10px 0;font-size:13.5px;white-space:pre-wrap">${e(s.message)}</p>
      ${s.reponse ? `<div style="background:#f0fdf4;border-radius:8px;padding:10px 12px;font-size:13px;margin-bottom:10px"><strong>Votre réponse :</strong> ${e(s.reponse)}</div>` : ''}
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <input type="text" id="rep-${s.id}" placeholder="Réponse interne (visible dans le CRM uniquement)…" style="flex:1;min-width:220px;padding:8px 10px;border:1px solid var(--border);border-radius:8px;font-size:13px" value="">
        <button class="btn btn-teal btn-sm" onclick="replySugg(${s.id}, 'répondu')">Répondre</button>
        ${s.statut === 'nouveau' ? `<button class="btn btn-outline btn-sm" onclick="replySugg(${s.id}, 'lu')">Marquer lu</button>` : ''}
        <button class="btn btn-danger btn-sm" onclick="delSugg(${s.id})">🗑</button>
      </div>
    </div>`).join('')
    : '<div class="section-card"><div class="empty-state"><div class="empty-icon">💬</div><p>Aucune suggestion pour le moment</p></div></div>';
}
async function replySugg(id, statut) {
  const rep = document.getElementById('rep-' + id).value.trim();
  const r = await RYBSEN.api('dr_sugg_reply', { id, statut, reponse: rep });
  if (r.ok) { RYBSEN.toast('Suggestion mise à jour ✓'); loadSuggs(); }
}
async function delSugg(id) {
  if (!RYBSEN.confirmDelete()) return;
  const r = await RYBSEN.api('dr_sugg_delete', { id });
  if (r.ok) { RYBSEN.toast('Supprimé'); loadSuggs(); }
}

// ── JOURNAL ──
async function loadLogs() {
  if (!allAcces.length) {
    allAcces = await RYBSEN.api('dr_acces_list');
    const sel = document.getElementById('logs-filter');
    sel.innerHTML = '<option value="0">Tous les investisseurs</option>' +
      (allAcces.map ? allAcces.map(a => `<option value="${a.id}">${e((a.prenom||'')+' '+a.nom)}</option>`).join('') : '');
  }
  const logs = await RYBSEN.api('dr_logs_list', { acces_id: +document.getElementById('logs-filter').value });
  if (logs.error) { RYBSEN.toast(logs.error, 'error'); return; }
  document.getElementById('logs-body').innerHTML = logs.length ? logs.map(l => `<tr>
    <td style="white-space:nowrap">${fmtDT(l.created_at)}</td>
    <td>${l.acces_nom ? `<strong>${e((l.acces_prenom||'')+' '+l.acces_nom)}</strong>${l.societe?`<br><small style="color:#999">${e(l.societe)}</small>`:''}` : '<span style="color:#999">—</span>'}</td>
    <td>${actionLabels[l.action] || e(l.action)}${l.detail ? `<br><small style="color:#999">${e(l.detail)}</small>` : ''}</td>
    <td>${l.doc_titre ? e(l.doc_titre) : '—'}</td>
    <td style="font-family:monospace;font-size:11.5px">${e(l.ip || '—')}</td>
    <td>${[l.ville_ip, l.pays_ip].filter(Boolean).map(x => e(x)).join(', ') || '—'}</td>
  </tr>`).join('') : '<tr><td colspan="6" style="text-align:center;padding:30px;color:#999">Aucun événement</td></tr>';
}

// Init
loadStats();
RYBSEN.api('dr_acces_list').then(r => { if (!r.error) document.getElementById('cnt-acces').textContent = r.length; });
RYBSEN.api('dr_doc_list').then(r => { if (!r.error) document.getElementById('cnt-docs').textContent = r.filter(d => +d.actif).length; });
RYBSEN.api('dr_sugg_list').then(r => { if (!r.error) document.getElementById('cnt-suggs').textContent = r.filter(s => s.statut === 'nouveau').length; });
</script>
<?php require_once '../includes/footer.php'; ?>
