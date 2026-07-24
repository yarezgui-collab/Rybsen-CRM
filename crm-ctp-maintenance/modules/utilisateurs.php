<?php
require_once '../config.php';
requireRole(['admin']);
$pageTitle = 'Utilisateurs';
$activePage = 'utilisateurs';
require_once '../includes/header.php';
?>
<div class="section-card">
  <div class="section-header">
    <div class="section-title">👥 Utilisateurs</div>
    <div class="section-actions"><button class="btn btn-primary" onclick="openAdd()">+ Nouvel utilisateur</button></div>
  </div>
  <div class="filters-bar">
    <input type="text" id="search" placeholder="🔍 Nom, email…">
    <select id="f-role" onchange="render()">
      <option value="">Tous les rôles</option>
      <option value="admin">Administrateur</option>
      <option value="technicien">Technicien</option>
      <option value="magasinier">Magasinier</option>
      <option value="client">Client</option>
    </select>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Nom</th><th>Email</th><th>Rôle</th><th>Client rattaché</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody id="body"><tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted)">Chargement…</td></tr></tbody>
    </table>
  </div>
  <div style="padding:10px 14px;color:var(--text-muted);font-size:13px" id="count"></div>
</div>

<div class="modal-overlay" id="modal-user">
  <div class="modal">
    <div class="modal-header"><div class="modal-title" id="modal-title">Utilisateur</div><button class="modal-close" onclick="CTP.closeModal('modal-user')">✕</button></div>
    <div class="modal-body">
      <input type="hidden" id="u-id">
      <div class="form-grid">
        <div class="form-group"><label>Nom *</label><input type="text" id="u-nom"></div>
        <div class="form-group"><label>Email *</label><input type="email" id="u-email"></div>
        <div class="form-group"><label>Rôle</label><select id="u-role" onchange="onRole()">
          <option value="technicien">Technicien</option><option value="magasinier">Magasinier</option>
          <option value="admin">Administrateur</option><option value="client">Client (portail)</option></select></div>
        <div class="form-group" id="wrap-client"><label>Client rattaché *</label><select id="u-client"></select></div>
        <div class="form-group"><label>Téléphone</label><input type="text" id="u-tel"></div>
        <div class="form-group"><label>Statut</label><select id="u-actif"><option value="1">Actif</option><option value="0">Inactif</option></select></div>
        <div class="form-group full"><label id="u-pass-label">Mot de passe *</label><input type="text" id="u-pass" placeholder="Laisser vide pour ne pas changer"></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="CTP.closeModal('modal-user')">Annuler</button>
      <button class="btn btn-primary" onclick="save()">Enregistrer</button>
    </div>
  </div>
</div>

<script>
let all = [], clients = [];
const roleLabel = { admin:'Administrateur', technicien:'Technicien', magasinier:'Magasinier', client:'Client' };
const roleBadge = r => ({admin:'badge-red', technicien:'badge-teal', magasinier:'badge-gold', client:'badge-navy'}[r] || 'badge-grey');
async function load() {
  clients = await CTP.api('user_clients');
  all = await CTP.api('user_list'); render();
}
function render() {
  const e = CTP.escape;
  const q = document.getElementById('search').value.toLowerCase();
  const fr = document.getElementById('f-role').value;
  const rows = all.filter(u => {
    if (fr && u.role !== fr) return false;
    if (q && !(`${u.nom} ${u.email}`).toLowerCase().includes(q)) return false;
    return true;
  });
  const body = document.getElementById('body');
  if (!rows.length) { body.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted)">Aucun utilisateur</td></tr>'; }
  else body.innerHTML = rows.map(u => `<tr>
    <td><strong>${e(u.nom)}</strong></td>
    <td>${e(u.email)}</td>
    <td><span class="badge ${roleBadge(u.role)}">${roleLabel[u.role]||e(u.role)}</span></td>
    <td>${e(u.raison_sociale) || '—'}</td>
    <td>${u.actif == 1 ? '<span class="badge badge-green">Actif</span>' : '<span class="badge badge-grey">Inactif</span>'}</td>
    <td>
      <button class="btn btn-outline btn-sm" onclick="edit(${u.id})">Modifier</button>
      <button class="btn btn-outline btn-sm" onclick="toggleActif(${u.id})">${u.actif == 1 ? 'Désactiver' : 'Activer'}</button>
      <button class="btn btn-danger btn-sm" onclick="del(${u.id})">Suppr.</button>
    </td></tr>`).join('');
  document.getElementById('count').textContent = `${rows.length} utilisateur(s)`;
}
function onRole() {
  document.getElementById('wrap-client').style.display = document.getElementById('u-role').value === 'client' ? '' : 'none';
}
function clientOptions(sel) {
  return '<option value="">— Choisir —</option>' + clients.map(c => `<option value="${c.id}" ${c.id==sel?'selected':''}>${CTP.escape(c.raison_sociale)}</option>`).join('');
}
function fill(u) {
  u = u || {};
  document.getElementById('u-id').value = u.id || '';
  document.getElementById('u-nom').value = u.nom || '';
  document.getElementById('u-email').value = u.email || '';
  document.getElementById('u-role').value = u.role || 'technicien';
  document.getElementById('u-client').innerHTML = clientOptions(u.client_id);
  document.getElementById('u-tel').value = u.telephone || '';
  document.getElementById('u-actif').value = u.actif !== undefined ? u.actif : 1;
  document.getElementById('u-pass').value = '';
  document.getElementById('u-pass-label').textContent = u.id ? 'Nouveau mot de passe (facultatif)' : 'Mot de passe *';
  onRole();
}
function openAdd() { document.getElementById('modal-title').textContent = 'Nouvel utilisateur'; fill(null); CTP.openModal('modal-user'); }
async function edit(id) {
  const u = await CTP.api('user_get', { id });
  if (u.error) return CTP.toast(u.error, 'error');
  document.getElementById('modal-title').textContent = 'Modifier ' + u.nom; fill(u); CTP.openModal('modal-user');
}
async function save() {
  const d = {
    id: document.getElementById('u-id').value,
    nom: document.getElementById('u-nom').value.trim(),
    email: document.getElementById('u-email').value.trim(),
    role: document.getElementById('u-role').value,
    client_id: document.getElementById('u-client').value,
    telephone: document.getElementById('u-tel').value.trim(),
    actif: document.getElementById('u-actif').value,
    password: document.getElementById('u-pass').value,
  };
  if (!d.nom) return CTP.toast('Nom requis', 'error');
  if (!d.email) return CTP.toast('Email requis', 'error');
  const r = await CTP.api('user_save', d);
  if (r.error) return CTP.toast(r.error, 'error');
  CTP.toast('Utilisateur enregistré'); CTP.closeModal('modal-user'); load();
}
async function toggleActif(id) { const r = await CTP.api('user_toggle_actif', { id }); if (r.error) return CTP.toast(r.error,'error'); load(); }
async function del(id) {
  if (!CTP.confirmDelete('Supprimer cet utilisateur ?')) return;
  const r = await CTP.api('user_delete', { id });
  if (r.error) return CTP.toast(r.error, 'error');
  CTP.toast('Utilisateur supprimé'); load();
}
load();
</script>
<?php require_once '../includes/footer.php'; ?>
