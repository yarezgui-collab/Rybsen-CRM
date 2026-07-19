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
    <div class="section-actions">
      <button class="btn btn-outline" onclick="LABO.openModal('modal-mdp')">🔑 Changer mon mot de passe</button>
      <button class="btn btn-primary" onclick="openAddUser()">+ Utilisateur</button>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Nom</th><th>Email</th><th>Rôle</th><th>Rattachement</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody id="user-body"><tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted)">Chargement...</td></tr></tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="modal-user">
  <div class="modal">
    <div class="modal-header"><div class="modal-title" id="modal-user-title">Ajouter un utilisateur</div><button class="modal-close" onclick="LABO.closeModal('modal-user')">✕</button></div>
    <div class="modal-body">
      <input type="hidden" id="user-id">
      <div class="form-grid">
        <div class="form-group full"><label>Nom *</label><input type="text" id="user-nom"></div>
        <div class="form-group full"><label>Email *</label><input type="email" id="user-email"></div>
        <div class="form-group"><label>Rôle</label>
          <select id="user-role" onchange="onRoleChange()">
            <option value="admin">Admin</option>
            <option value="labo">Labo</option>
            <option value="production">Production</option>
            <option value="franchise">Franchise</option>
            <option value="point_vente">Point de vente</option>
            <option value="client_terme">Client à terme</option>
          </select>
        </div>
        <div class="form-group"><label>Avatar (2 lettres)</label><input type="text" id="user-avatar" maxlength="2"></div>
        <div class="form-group" id="wrap-user-client" style="display:none"><label>Client rattaché</label><select id="user-client"></select></div>
        <div class="form-group" id="wrap-user-pv" style="display:none"><label>Point de vente rattaché</label><select id="user-pv"></select></div>
        <div class="form-group full"><label id="user-pwd-label">Mot de passe *</label><input type="password" id="user-password" placeholder="Laisser vide pour ne pas changer"></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="LABO.closeModal('modal-user')">Annuler</button>
      <button class="btn btn-primary" onclick="saveUser()">Enregistrer</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modal-mdp">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Changer mon mot de passe</div><button class="modal-close" onclick="LABO.closeModal('modal-mdp')">✕</button></div>
    <div class="modal-body">
      <div class="form-group" style="margin-bottom:16px"><label>Mot de passe actuel</label><input type="password" id="mdp-actuel"></div>
      <div class="form-group"><label>Nouveau mot de passe</label><input type="password" id="mdp-nouveau"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="LABO.closeModal('modal-mdp')">Annuler</button>
      <button class="btn btn-primary" onclick="changerMdp()">Valider</button>
    </div>
  </div>
</div>

<script>
let allUsers = [], refClients = [], refPv = [];
const roleLabels = { admin: 'Admin', labo: 'Labo', production: 'Production', franchise: 'Franchise', point_vente: 'Point de vente', client_terme: 'Client à terme' };

async function loadRefs() {
  [refClients, refPv] = await Promise.all([LABO.api('cli_list'), LABO.api('pv_list')]);
  document.getElementById('user-client').innerHTML = '<option value="">—</option>' + refClients.map(c => `<option value="${c.id}">${LABO.escape(c.nom)}</option>`).join('');
  document.getElementById('user-pv').innerHTML = '<option value="">—</option>' + refPv.map(p => `<option value="${p.id}">${LABO.escape(p.nom)}</option>`).join('');
}
function onRoleChange() {
  const role = document.getElementById('user-role').value;
  document.getElementById('wrap-user-client').style.display = (role === 'client_terme' || role === 'franchise') ? '' : 'none';
  document.getElementById('wrap-user-pv').style.display = role === 'point_vente' ? '' : 'none';
}

async function loadUsers() {
  allUsers = await LABO.api('user_list');
  const e = LABO.escape;
  document.getElementById('user-body').innerHTML = allUsers.length ? allUsers.map(u => `
    <tr>
      <td><strong>${e(u.nom)}</strong></td>
      <td>${e(u.email)}</td>
      <td><span class="badge badge-navy">${roleLabels[u.role] || u.role}</span></td>
      <td>${e(u.client_nom || u.point_vente_nom) || '—'}</td>
      <td><span class="badge ${u.actif == 1 ? 'badge-green' : 'badge-grey'}">${u.actif == 1 ? 'Actif' : 'Inactif'}</span></td>
      <td>
        <button onclick="editUserById(${u.id})" class="btn btn-outline btn-sm">✏️</button>
        <button onclick="toggleActif(${u.id})" class="btn btn-outline btn-sm">${u.actif == 1 ? '⏸' : '▶️'}</button>
        <button onclick="delUser(${u.id})" class="btn btn-danger btn-sm">🗑</button>
      </td>
    </tr>`).join('') : '<tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted)">Aucun utilisateur</td></tr>';
}
function editUserById(id) { const u = allUsers.find(x => x.id === id); if (u) editUser(u); }
function openAddUser() {
  document.getElementById('modal-user-title').textContent = 'Ajouter un utilisateur';
  document.getElementById('user-id').value = '';
  document.getElementById('user-nom').value = '';
  document.getElementById('user-email').value = '';
  document.getElementById('user-role').value = 'client_terme';
  document.getElementById('user-avatar').value = '';
  document.getElementById('user-client').value = '';
  document.getElementById('user-pv').value = '';
  document.getElementById('user-password').value = '';
  document.getElementById('user-pwd-label').textContent = 'Mot de passe *';
  onRoleChange();
  LABO.openModal('modal-user');
}
function editUser(u) {
  document.getElementById('modal-user-title').textContent = 'Modifier utilisateur';
  document.getElementById('user-id').value = u.id;
  document.getElementById('user-nom').value = u.nom;
  document.getElementById('user-email').value = u.email;
  document.getElementById('user-role').value = u.role;
  document.getElementById('user-avatar').value = u.avatar || '';
  document.getElementById('user-client').value = u.client_id || '';
  document.getElementById('user-pv').value = u.point_vente_id || '';
  document.getElementById('user-password').value = '';
  document.getElementById('user-pwd-label').textContent = 'Nouveau mot de passe (optionnel)';
  onRoleChange();
  LABO.openModal('modal-user');
}
async function saveUser() {
  const id = document.getElementById('user-id').value;
  const nom = document.getElementById('user-nom').value.trim();
  const email = document.getElementById('user-email').value.trim();
  const password = document.getElementById('user-password').value;
  if (!nom || !email) { LABO.toast('Nom et email requis', 'error'); return; }
  if (!id && !password) { LABO.toast('Mot de passe requis pour un nouvel utilisateur', 'error'); return; }
  const payload = {
    id, nom, email,
    role: document.getElementById('user-role').value,
    avatar: document.getElementById('user-avatar').value,
    client_id: document.getElementById('user-client').value || null,
    point_vente_id: document.getElementById('user-pv').value || null,
    actif: 1
  };
  if (password) payload.password = password;
  const r = await LABO.api(id ? 'user_update' : 'user_create', payload);
  if (r.ok) { LABO.closeModal('modal-user'); LABO.toast('Enregistré ✓'); loadUsers(); }
  else LABO.toast(r.error || 'Erreur', 'error');
}
async function toggleActif(id) {
  const r = await LABO.api('user_toggle_actif', { id });
  if (r.ok) { LABO.toast('Statut modifié'); loadUsers(); } else LABO.toast(r.error || 'Erreur', 'error');
}
async function delUser(id) {
  if (!LABO.confirmDelete()) return;
  const r = await LABO.api('user_delete', { id });
  if (r.ok) { LABO.toast('Supprimé'); loadUsers(); } else LABO.toast(r.error || 'Erreur', 'error');
}
async function changerMdp() {
  const r = await LABO.api('me_change_password', {
    current_password: document.getElementById('mdp-actuel').value,
    new_password: document.getElementById('mdp-nouveau').value
  });
  if (r.ok) { LABO.closeModal('modal-mdp'); LABO.toast('Mot de passe changé ✓'); document.getElementById('mdp-actuel').value=''; document.getElementById('mdp-nouveau').value=''; }
  else LABO.toast(r.error || 'Erreur', 'error');
}

(async function () { await loadRefs(); loadUsers(); })();
</script>
<?php require_once '../includes/footer.php'; ?>
