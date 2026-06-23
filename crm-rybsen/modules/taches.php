<?php
require_once '../config.php';
$pageTitle = 'Tâches & Alertes';
$activePage = 'taches';
require_once '../includes/header.php';
?>

<div class="section-card">
  <div class="section-header">
    <div class="section-title">✅ Tâches & Alertes prioritaires</div>
    <div class="section-actions">
      <select id="filter-tache-statut" class="btn btn-outline btn-sm" style="border:1.5px solid #E2E8E4;padding:7px 12px;border-radius:8px;font-size:12px">
        <option value="">Tous statuts</option>
        <option value="À faire">À faire</option><option value="En cours">En cours</option>
        <option value="Terminé">Terminé</option>
      </select>
      <button class="btn btn-primary" onclick="openAddTask()">+ Tâche</button>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Priorité</th><th>Tâche</th><th>Module</th><th>Responsable</th><th>Deadline</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody id="tasks-body">
        <tr><td colspan="7" style="text-align:center;padding:30px;color:#999">Chargement...</td></tr>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="modal-task">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="modal-task-title">Nouvelle tâche</div>
      <button class="modal-close" onclick="RYBSEN.closeModal('modal-task')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="task-id">
      <div class="form-grid">
        <div class="form-group full"><label>Titre *</label><input type="text" id="task-titre" placeholder="Description de la tâche"></div>
        <div class="form-group"><label>Module</label>
          <select id="task-module"><option>Levée de fonds</option><option>Commercial</option><option>Fabrication</option><option>Partenaires</option><option>Candidatures</option><option>Admin/Légal</option><option>Marketing</option><option>Autre</option></select>
        </div>
        <div class="form-group"><label>Priorité</label>
          <select id="task-priorite"><option>🔴 Urgent</option><option>🟡 Important</option><option>🟢 Normal</option></select>
        </div>
        <div class="form-group"><label>Deadline</label><input type="date" id="task-deadline"></div>
        <div class="form-group"><label>Statut</label>
          <select id="task-statut"><option>À faire</option><option>En cours</option><option>En attente</option><option>Terminé</option></select>
        </div>
        <div class="form-group full"><label>Notes</label><textarea id="task-notes"></textarea></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="RYBSEN.closeModal('modal-task')">Annuler</button>
      <button class="btn btn-primary" onclick="saveTask()">Enregistrer</button>
    </div>
  </div>
</div>

<script>
let allTasks = [];

async function loadTasks() {
  allTasks = await RYBSEN.api('task_list');
  renderTasks(allTasks);
}

function renderTasks(data) {
  const filter = document.getElementById('filter-tache-statut').value;
  const filtered = filter ? data.filter(t => t.statut === filter) : data;
  const body = document.getElementById('tasks-body');
  if (!filtered.length) { body.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:30px;color:#999">Aucune tâche</td></tr>'; return; }
  body.innerHTML = filtered.map(t => {
    const d = t.deadline ? Math.ceil((new Date(t.deadline)-new Date())/86400000) : null;
    const dStr = d !== null ? (d < 0 ? `<span style="color:#dc2626;font-weight:600">${Math.abs(d)}j retard</span>` : d === 0 ? '<span style="color:#e8a44c;font-weight:600">Aujourd\'hui</span>' : `${d}j`) : '—';
    const brevet = t.alerte_brevet=='1' ? ' ⚠️' : '';
    return `<tr style="${t.statut==='Terminé'?'opacity:0.5':''}">
      <td>${t.priorite}</td>
      <td><strong>${t.titre}${brevet}</strong>${t.notes?`<br><small style="color:#999">${t.notes.substring(0,60)}...</small>`:''}</td>
      <td><span class="badge badge-grey">${t.module_lie}</span></td>
      <td>${t.resp_nom||'—'}</td>
      <td>${dStr}</td>
      <td><span class="badge ${t.statut==='Terminé'?'badge-green':t.statut==='En cours'?'badge-teal':'badge-grey'}">${t.statut}</span></td>
      <td style="white-space:nowrap">
        ${t.statut!=='Terminé'?`<button onclick="markDone(${t.id})" class="btn btn-teal btn-sm">✓ Fait</button>`:''}
        <button onclick='editTaskById(${t.id})' class="btn btn-outline btn-sm">✏️</button>
        <button onclick="delTask(${t.id})" class="btn btn-danger btn-sm">🗑</button>
      </td>
    </tr>`;
  }).join('');
}

function openAddTask() {
  document.getElementById('modal-task-title').textContent = 'Nouvelle tâche';
  document.getElementById('task-id').value = '';
  ['task-titre','task-deadline','task-notes'].forEach(id => document.getElementById(id).value = '');
  RYBSEN.openModal('modal-task');
}

function editTaskById(id) {
  const t = allTasks.find(x => x.id === id);
  if (t) editTask(t);
}

function editTask(t) {
  document.getElementById('modal-task-title').textContent = 'Modifier tâche';
  document.getElementById('task-id').value = t.id;
  document.getElementById('task-titre').value = t.titre;
  document.getElementById('task-module').value = t.module_lie;
  document.getElementById('task-priorite').value = t.priorite;
  document.getElementById('task-deadline').value = t.deadline||'';
  document.getElementById('task-statut').value = t.statut;
  document.getElementById('task-notes').value = t.notes||'';
  RYBSEN.openModal('modal-task');
}

async function saveTask() {
  const titre = document.getElementById('task-titre').value.trim();
  if (!titre) { RYBSEN.toast('Le titre est requis', 'error'); return; }
  const r = await RYBSEN.api('task_save', {
    id: document.getElementById('task-id').value,
    titre, module_lie: document.getElementById('task-module').value,
    priorite: document.getElementById('task-priorite').value,
    deadline: document.getElementById('task-deadline').value||null,
    statut: document.getElementById('task-statut').value,
    notes: document.getElementById('task-notes').value
  });
  if (r.ok) { RYBSEN.closeModal('modal-task'); RYBSEN.toast('Tâche enregistrée ✓'); loadTasks(); }
  else RYBSEN.toast(r.error||'Erreur','error');
}

async function markDone(id) {
  const r = await RYBSEN.api('task_done', { id });
  if (r.ok) { RYBSEN.toast('Tâche terminée ✓'); loadTasks(); }
}

async function delTask(id) {
  if (!RYBSEN.confirmDelete()) return;
  const r = await RYBSEN.api('task_delete', { id });
  if (r.ok) { RYBSEN.toast('Supprimée'); loadTasks(); }
}

document.getElementById('filter-tache-statut').addEventListener('change', () => renderTasks(allTasks));
loadTasks();
</script>
<?php require_once '../includes/footer.php'; ?>
