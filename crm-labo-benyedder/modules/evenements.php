<?php
require_once '../config.php';
requireRole(['admin']);
$pageTitle = 'Événements spéciaux';
$activePage = 'evenements';
require_once '../includes/header.php';
?>
<div class="section-card">
  <div class="section-header">
    <div class="section-title">🎉 Événements spéciaux</div>
    <div class="section-actions"><button class="btn btn-primary" onclick="openAddEvt()">+ Événement</button></div>
  </div>
  <div class="alert-box info">Calendrier saisonnier (Ramadan, Aïd) et commandes événementielles (mariages, traiteur) — sélectionnable lors de la création d'une commande de type « événementielle ».</div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Nom</th><th>Type</th><th>Du</th><th>Au</th><th>Actions</th></tr></thead>
      <tbody id="evt-body"><tr><td colspan="5" style="text-align:center;padding:30px;color:var(--text-muted)">Chargement...</td></tr></tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="modal-evt">
  <div class="modal">
    <div class="modal-header"><div class="modal-title" id="modal-evt-title">Ajouter un événement</div><button class="modal-close" onclick="LABO.closeModal('modal-evt')">✕</button></div>
    <div class="modal-body">
      <input type="hidden" id="evt-id">
      <div class="form-grid">
        <div class="form-group full"><label>Nom *</label><input type="text" id="evt-nom" placeholder="Ex: Ramadan 2027 ou Mariage Trabelsi"></div>
        <div class="form-group"><label>Type</label>
          <select id="evt-type"><option value="ramadan">Ramadan</option><option value="aid">Aïd</option><option value="mariage">Mariage</option><option value="autre">Autre</option></select>
        </div>
        <div class="form-group"><label>Du</label><input type="date" id="evt-debut"></div>
        <div class="form-group"><label>Au</label><input type="date" id="evt-fin"></div>
        <div class="form-group full"><label>Notes</label><textarea id="evt-notes"></textarea></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="LABO.closeModal('modal-evt')">Annuler</button>
      <button class="btn btn-primary" onclick="saveEvt()">Enregistrer</button>
    </div>
  </div>
</div>

<script>
let allEvt = [];
const typeLabels = { ramadan: 'Ramadan', aid: 'Aïd', mariage: 'Mariage', autre: 'Autre' };
async function loadEvt() {
  allEvt = await LABO.api('evt_list');
  const e = LABO.escape;
  document.getElementById('evt-body').innerHTML = allEvt.length ? allEvt.map(v => `
    <tr>
      <td><strong>${e(v.nom)}</strong></td>
      <td><span class="badge badge-gold">${typeLabels[v.type] || v.type}</span></td>
      <td>${LABO.formatDate(v.date_debut)}</td>
      <td>${LABO.formatDate(v.date_fin)}</td>
      <td>
        <button onclick="editEvtById(${v.id})" class="btn btn-outline btn-sm">✏️</button>
        <button onclick="delEvt(${v.id})" class="btn btn-danger btn-sm">🗑</button>
      </td>
    </tr>`).join('') : '<tr><td colspan="5" style="text-align:center;padding:30px;color:var(--text-muted)">Aucun événement</td></tr>';
}
function editEvtById(id) { const v = allEvt.find(x => x.id === id); if (v) editEvt(v); }
function openAddEvt() {
  document.getElementById('modal-evt-title').textContent = 'Ajouter un événement';
  document.getElementById('evt-id').value = '';
  document.getElementById('evt-nom').value = '';
  document.getElementById('evt-type').value = 'autre';
  document.getElementById('evt-debut').value = '';
  document.getElementById('evt-fin').value = '';
  document.getElementById('evt-notes').value = '';
  LABO.openModal('modal-evt');
}
function editEvt(v) {
  document.getElementById('modal-evt-title').textContent = 'Modifier événement';
  document.getElementById('evt-id').value = v.id;
  document.getElementById('evt-nom').value = v.nom;
  document.getElementById('evt-type').value = v.type;
  document.getElementById('evt-debut').value = v.date_debut;
  document.getElementById('evt-fin').value = v.date_fin;
  document.getElementById('evt-notes').value = v.notes || '';
  LABO.openModal('modal-evt');
}
async function saveEvt() {
  const nom = document.getElementById('evt-nom').value.trim();
  const debut = document.getElementById('evt-debut').value;
  const fin = document.getElementById('evt-fin').value;
  if (!nom || !debut || !fin) { LABO.toast('Nom et dates requis', 'error'); return; }
  const r = await LABO.api('evt_save', {
    id: document.getElementById('evt-id').value,
    nom, type: document.getElementById('evt-type').value,
    date_debut: debut, date_fin: fin,
    notes: document.getElementById('evt-notes').value
  });
  if (r.ok) { LABO.closeModal('modal-evt'); LABO.toast('Enregistré ✓'); loadEvt(); }
  else LABO.toast(r.error || 'Erreur', 'error');
}
async function delEvt(id) {
  if (!LABO.confirmDelete()) return;
  const r = await LABO.api('evt_delete', { id });
  if (r.ok) { LABO.toast('Supprimé'); loadEvt(); }
}
loadEvt();
</script>
<?php require_once '../includes/footer.php'; ?>
