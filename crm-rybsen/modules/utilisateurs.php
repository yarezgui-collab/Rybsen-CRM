<?php
require_once '../config.php';
$pageTitle = 'Gestion des Utilisateurs';
$activePage = 'utilisateurs';
require_once '../includes/header.php';
$me = currentUser();
if ($me['role'] !== 'admin') {
  echo '<div class="alert-box urgent"><span>🚫</span><div>Accès réservé aux administrateurs.</div></div>';
  require_once '../includes/footer.php';
  exit;
}
?>
<div class="kpi-grid" id="user-kpi-grid">
  <div class="kpi-card navy">
    <div class="kpi-label">Total comptes</div>
    <div class="kpi-value" id="kpi-total-users">—</div>
    <div class="kpi-sub">utilisateurs enregistrés</div>
  </div>
  <div class="kpi-card teal">
    <div class="kpi-label">Comptes actifs</div>
    <div class="kpi-value" id="kpi-actifs">—</div>
    <div class="kpi-sub">connexion autorisée</div>
  </div>
  <div class="kpi-card gold">
    <div class="kpi-label">Administrateurs</div>
    <div class="kpi-value" id="kpi-admins">—</div>
    <div class="kpi-sub">accès complet</div>
  </div>
  <div class="kpi-card red">
    <div class="kpi-label">Comptes désactivés</div>
    <div class="kpi-value" id="kpi-inactifs">—</div>
    <div class="kpi-sub">connexion bloquée</div>
  </div>
</div>

<div class="section-card">
  <div class="section-header">
    <div class="section-title">👥 Gestion des Utilisateurs & Permissions</div>
    <div class="section-actions">
      <button class="btn btn-primary" onclick="openAdd()">+ Nouveau compte</button>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th></th><th>Nom</th><th>Email</th><th>Rôle</th><th>Statut</th><th>Créé le</th><th>Actions</th></tr></thead>
      <tbody id="users-body"><tr><td colspan="7" style="text-align:center;padding:30px;color:#999">Chargement...</td></tr></tbody>
    </table>
  </div>
</div>

<div class="section-card">
  <div class="section-header"><div class="section-title">🔐 Niveaux de permission</div></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Rôle</th><th>Description</th><th>Droits</th></tr></thead>
      <tbody>
        <tr><td><span class="badge badge-gold">Admin</span></td><td>Accès complet à la plateforme</td><td>Lecture, écriture, suppression, gestion des utilisateurs</td></tr>
        <tr><td><span class="badge badge-teal">Manager</span></td><td>Gestion opérationnelle quotidienne</td><td>Lecture, écriture, suppression sur tous les modules métier</td></tr>
        <tr><td><span class="badge badge-grey">Viewer</span></td><td>Consultation uniquement</td><td>Lecture seule, aucune modification</td></tr>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="modal-user">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="modal-user-title">Nouveau compte</div>
      <button class="modal-close" onclick="RYBSEN.closeModal('modal-user')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="user-id">
      <div class="form-grid">
        <div class="form-group"><label>Nom complet *</label><input type="text" id="user-nom" placeholder="Prénom Nom"></div>
        <div class="form-group"><label>Email *</label><input type="email" id="user-email" placeholder="prenom@rybsen.fr"></div>
        <div class="form-group"><label>Rôle</label>
          <select id="user-role"><option value="viewer">Viewer</option><option value="manager">Manager</option><option value="admin">Admin</option></select>
        </div>
        <div class="form-group"><label>Statut</label>
          <select id="user-actif"><option value="1">Actif</option><option value="0">Désactivé</option></select>
        </div>
        <div class="form-group"><label>Avatar (2 lettres)</label><input type="text" id="user-avatar" maxlength="2" placeholder="YR" style="text-transform:uppercase"></div>
        <div class="form-group"><label id="pwd-label">Mot de passe *</label><input type="password" id="user-password" placeholder="••••••••"></div>
        <div class="form-group full" id="pwd-hint" style="display:none"><small style="color:#999">Laisser vide pour conserver le mot de passe actuel</small></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="RYBSEN.closeModal('modal-user')">Annuler</button>
      <button class="btn btn-primary" onclick="saveUser()">Enregistrer</button>
    </div>
  </div>
</div>

<script>
let allUsers = [];
const roleColors = {'admin':'badge-gold','manager':'badge-teal','viewer':'badge-grey'};

async function loadUsers() {
  allUsers = await RYBSEN.api('users_list');
  if (allUsers.error) { RYBSEN.toast(allUsers.error, 'error'); return; }
  renderUsers();
  updateKPIs();
}

function updateKPIs() {
  document.getElementById('kpi-total-users').textContent = allUsers.length;
  document.getElementById('kpi-actifs').textContent = allUsers.filter(u => u.actif == 1).length;
  document.getElementById('kpi-admins').textContent = allUsers.filter(u => u.role === 'admin').length;
  document.getElementById('kpi-inactifs').textContent = allUsers.filter(u => u.actif == 0).length;
}

function renderUsers() {
  const body = document.getElementById('users-body');
  body.innerHTML = allUsers.map(u => `<tr style="${u.actif==0?'opacity:0.5':''}">
    <td><div class="user-avatar" style="width:32px;height:32px;font-size:11px;background:${u.role==='admin'?'#E8A44C':'#4A9B8F'}">${u.avatar}</div></td>
    <td><strong>${u.nom}</strong></td>
    <td>${u.email}</td>
    <td><span class="badge ${roleColors[u.role]||'badge-grey'}">${u.role}</span></td>
    <td>${u.actif==1?'<span class="badge badge-green">Actif</span>':'<span class="badge badge-red">Désactivé</span>'}</td>
    <td>${new Date(u.created_at).toLocaleDateString('fr-FR')}</td>
    <td style="white-space:nowrap">
      <button onclick='editUserById(${u.id})' class="btn btn-outline btn-sm">✏️</button>
      <button onclick="toggleActif(${u.id})" class="btn btn-outline btn-sm">${u.actif==1?'⏸':'▶️'}</button>
      <button onclick="delUser(${u.id})" class="btn btn-danger btn-sm">🗑</button>
    </td>
  </tr>`).join('');
}

function openAdd() {
  document.getElementById('modal-user-title').textContent = 'Nouveau compte';
  document.getElementById('user-id').value = '';
  document.getElementById('pwd-label').textContent = 'Mot de passe *';
  document.getElementById('pwd-hint').style.display = 'none';
  ['user-nom','user-email','user-avatar','user-password'].forEach(i => document.getElementById(i).value = '');
  document.getElementById('user-role').value = 'viewer';
  document.getElementById('user-actif').value = '1';
  RYBSEN.openModal('modal-user');
}

function editUserById(id) {
  const u = allUsers.find(x => x.id === id);
  if (u) editUser(u);
}

function editUser(u) {
  document.getElementById('modal-user-title').textContent = 'Modifier le compte';
  document.getElementById('user-id').value = u.id;
  document.getElementById('user-nom').value = u.nom;
  document.getElementById('user-email').value = u.email;
  document.getElementById('user-role').value = u.role;
  document.getElementById('user-actif').value = u.actif;
  document.getElementById('user-avatar').value = u.avatar;
  document.getElementById('user-password').value = '';
  document.getElementById('pwd-label').textContent = 'Nouveau mot de passe';
  document.getElementById('pwd-hint').style.display = 'block';
  RYBSEN.openModal('modal-user');
}

async function saveUser() {
  const nom = document.getElementById('user-nom').value.trim();
  const email = document.getElementById('user-email').value.trim();
  const id = document.getElementById('user-id').value;
  const password = document.getElementById('user-password').value;
  if (!nom || !email) { RYBSEN.toast('Nom et email requis', 'error'); return; }
  if (!id && !password) { RYBSEN.toast('Mot de passe requis pour un nouveau compte', 'error'); return; }

  const data = {
    nom, email,
    role: document.getElementById('user-role').value,
    actif: document.getElementById('user-actif').value,
    avatar: document.getElementById('user-avatar').value.toUpperCase() || nom.substring(0,2).toUpperCase(),
    password
  };
  const action = id ? 'user_update' : 'user_create';
  if (id) data.id = id;

  const r = await RYBSEN.api(action, data);
  if (r.ok) { RYBSEN.closeModal('modal-user'); RYBSEN.toast('Compte enregistré ✓'); loadUsers(); }
  else RYBSEN.toast(r.error || 'Erreur', 'error');
}

async function toggleActif(id) {
  const r = await RYBSEN.api('user_toggle_actif', { id });
  if (r.ok) { RYBSEN.toast('Statut mis à jour ✓'); loadUsers(); }
  else RYBSEN.toast(r.error || 'Erreur', 'error');
}

async function delUser(id) {
  if (!RYBSEN.confirmDelete('Supprimer définitivement ce compte ?')) return;
  const r = await RYBSEN.api('user_delete', { id });
  if (r.ok) { RYBSEN.toast('Compte supprimé'); loadUsers(); }
  else RYBSEN.toast(r.error || 'Erreur', 'error');
}

loadUsers();
</script>
<?php require_once '../includes/footer.php'; ?>
