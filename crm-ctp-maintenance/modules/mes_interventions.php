<?php
require_once '../config.php';
requireRole(['client']);
$pageTitle = 'Mes interventions';
$activePage = 'mes_interventions';
require_once '../includes/header.php';
?>
<div class="section-card">
  <div class="section-header">
    <div class="section-title">🔧 Mes interventions</div>
    <div class="section-actions"><button class="btn btn-teal" onclick="openSignaler()">⚠ Signaler une panne</button></div>
  </div>
  <div class="filters-bar">
    <input type="text" id="search" placeholder="🔍 N°, machine, description…">
    <select id="f-statut" onchange="render()">
      <option value="">Tous statuts</option>
      <option value="ouvertes" selected>En cours</option>
      <option value="cloturee">Clôturées</option>
    </select>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>N°</th><th>Machine</th><th>Type</th><th>Priorité</th><th>Créée le</th><th>Statut</th><th></th></tr></thead>
      <tbody id="body"><tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-muted)">Chargement…</td></tr></tbody>
    </table>
  </div>
  <div style="padding:10px 14px;color:var(--text-muted);font-size:13px" id="count"></div>
</div>

<!-- Modal signalement -->
<div class="modal-overlay" id="modal-signal">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Signaler une panne</div><button class="modal-close" onclick="CTP.closeModal('modal-signal')">✕</button></div>
    <div class="modal-body">
      <div class="alert-box info">Décrivez le problème : notre équipe SAV crée une intervention et vous en suivez l'avancement ici.</div>
      <div class="form-group"><label>Machine concernée *</label><select id="sg-machine"></select></div>
      <div class="form-group" style="margin-top:12px"><label>Priorité</label><select id="sg-prio">
        <option value="normale">Normale</option><option value="haute">Haute</option>
        <option value="urgente">Urgente (production arrêtée)</option><option value="basse">Basse</option></select></div>
      <div class="form-group" style="margin-top:12px"><label>Description du problème *</label><textarea id="sg-desc" placeholder="Symptômes, messages d'erreur, contexte…"></textarea></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="CTP.closeModal('modal-signal')">Annuler</button>
      <button class="btn btn-teal" onclick="signaler()">Envoyer le signalement</button>
    </div>
  </div>
</div>

<!-- Modal détail -->
<div class="modal-overlay" id="modal-detail">
  <div class="modal">
    <div class="modal-header"><div class="modal-title" id="detail-title">Détail</div><button class="modal-close" onclick="CTP.closeModal('modal-detail')">✕</button></div>
    <div class="modal-body" id="detail-body"></div>
    <div class="modal-footer"><button class="btn btn-outline" onclick="CTP.closeModal('modal-detail')">Fermer</button></div>
  </div>
</div>

<script>
let all = [], machines = [];
const statutBadge = s => ({nouvelle:'badge-red', planifiee:'badge-blue', en_cours:'badge-teal', en_attente_piece:'badge-gold', resolue:'badge-green', cloturee:'badge-grey', annulee:'badge-grey'}[s] || 'badge-grey');
const prioBadge = p => ({urgente:'badge-red', haute:'badge-gold', normale:'badge-navy', basse:'badge-grey'}[p] || 'badge-grey');
const lbl = s => (s||'').replace(/_/g,' ');
async function load() {
  machines = await CTP.api('mes_machines');
  all = await CTP.api('mes_interventions'); render();
}
function render() {
  const e = CTP.escape;
  const q = document.getElementById('search').value.toLowerCase();
  const fs = document.getElementById('f-statut').value;
  const rows = all.filter(i => {
    if (fs === 'ouvertes' && ['cloturee','annulee'].includes(i.statut)) return false;
    if (fs === 'cloturee' && i.statut !== 'cloturee') return false;
    if (q && !(`${i.numero} ${i.modele} ${i.n_serie} ${i.description||''}`).toLowerCase().includes(q)) return false;
    return true;
  });
  const body = document.getElementById('body');
  if (!rows.length) { body.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-muted)">Aucune intervention</td></tr>'; }
  else body.innerHTML = rows.map(i => `<tr>
    <td><strong>${e(i.numero)}</strong></td>
    <td>${e(i.modele)}<br><span style="color:var(--text-muted)">${e(i.n_serie)}</span></td>
    <td>${e(i.type)}</td>
    <td><span class="badge ${prioBadge(i.priorite)}">${e(i.priorite)}</span></td>
    <td>${CTP.formatDate(i.created_at)}</td>
    <td><span class="badge ${statutBadge(i.statut)}">${lbl(i.statut)}</span></td>
    <td><button class="btn btn-outline btn-sm" onclick="detail(${i.id})">Suivi</button></td>
  </tr>`).join('');
  document.getElementById('count').textContent = `${rows.length} intervention(s)`;
}
function openSignaler() {
  const avail = machines.filter(m => m.statut !== 'retire');
  if (!avail.length) return CTP.toast("Aucune machine disponible", 'error');
  document.getElementById('sg-machine').innerHTML = avail.map(m => `<option value="${m.id}">${CTP.escape(m.modele)} — ${CTP.escape(m.n_serie)}</option>`).join('');
  document.getElementById('sg-prio').value = 'normale';
  document.getElementById('sg-desc').value = '';
  CTP.openModal('modal-signal');
}
async function signaler() {
  const d = {
    machine_id: document.getElementById('sg-machine').value,
    priorite: document.getElementById('sg-prio').value,
    description: document.getElementById('sg-desc').value.trim(),
  };
  if (!d.machine_id) return CTP.toast('Choisissez une machine', 'error');
  if (!d.description) return CTP.toast('Décrivez le problème', 'error');
  const r = await CTP.api('mes_signaler', d);
  if (r.error) return CTP.toast(r.error, 'error');
  CTP.toast('Signalement envoyé — ' + r.numero); CTP.closeModal('modal-signal'); load();
}
async function detail(id) {
  const i = await CTP.api('mes_intervention_get', { id });
  if (i.error) return CTP.toast(i.error, 'error');
  const e = CTP.escape;
  document.getElementById('detail-title').textContent = i.numero + ' — ' + i.modele;
  document.getElementById('detail-body').innerHTML = `
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
      <span class="badge ${statutBadge(i.statut)}">${lbl(i.statut)}</span>
      <span class="badge ${prioBadge(i.priorite)}">${e(i.priorite)}</span>
      <span class="badge badge-navy">${e(i.type)}</span>
      <span class="badge badge-grey">S/N ${e(i.n_serie)}</span>
    </div>
    <div class="form-grid" style="margin-bottom:8px">
      <div><label style="font-size:11px;color:var(--text-muted)">Signalée le</label><div>${CTP.formatDate(i.created_at)}</div></div>
      <div><label style="font-size:11px;color:var(--text-muted)">Planifiée</label><div>${CTP.formatDateTime(i.date_planifiee)}</div></div>
      <div><label style="font-size:11px;color:var(--text-muted)">Début</label><div>${CTP.formatDateTime(i.date_debut)}</div></div>
      <div><label style="font-size:11px;color:var(--text-muted)">Résolue le</label><div>${CTP.formatDateTime(i.date_fin)}</div></div>
    </div>
    ${i.description ? `<p style="margin:8px 0"><strong>Problème signalé :</strong> ${e(i.description)}</p>`:''}
    ${i.diagnostic ? `<p style="margin:8px 0"><strong>Diagnostic :</strong> ${e(i.diagnostic)}</p>`:''}
    ${i.resolution ? `<p style="margin:8px 0"><strong>Résolution :</strong> ${e(i.resolution)}</p>`:''}`;
  CTP.openModal('modal-detail');
}
load();
</script>
<?php require_once '../includes/footer.php'; ?>
