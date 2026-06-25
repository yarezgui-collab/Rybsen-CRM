<?php
require_once '../config.php';
$pageTitle = 'Traçabilité Messages';
$activePage = 'messages';
require_once '../includes/header.php';
?>
<div class="section-card">
  <div class="section-header">
    <div class="section-title">📨 Traçabilité des Communications</div>
    <div class="section-actions"><button class="btn btn-primary" onclick="openAdd()">+ Message</button></div>
  </div>
  <div class="filters-bar">
    <input type="text" id="search-msg" placeholder="🔍 Rechercher...">
    <select id="filter-canal"><option value="">Tous canaux</option><option>Email</option><option>LinkedIn</option><option>WhatsApp</option><option>Téléphone</option><option>Réunion</option></select>
    <select id="filter-statut-msg"><option value="">Tous statuts</option><option>Envoyé</option><option>Répondu</option><option>Sans réponse</option><option>À envoyer</option></select>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Destinataire</th><th>Organisation</th><th>Canal</th><th>Objet</th><th>Statut</th><th>Envoyé le</th><th>Réponse le</th><th>Actions</th></tr></thead>
      <tbody id="msg-body"><tr><td colspan="8" style="text-align:center;padding:30px;color:#999">Chargement...</td></tr></tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="modal-msg">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Ajouter un message</div>
      <button class="modal-close" onclick="RYBSEN.closeModal('modal-msg')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="msg-id">
      <div class="form-grid">
        <div class="form-group"><label>Destinataire *</label><input type="text" id="msg-dest"></div>
        <div class="form-group"><label>Organisation</label><input type="text" id="msg-org"></div>
        <div class="form-group"><label>Canal</label><select id="msg-canal"><option>Email</option><option>LinkedIn</option><option>WhatsApp</option><option>Téléphone</option><option>Réunion</option><option>Autre</option></select></div>
        <div class="form-group"><label>Statut</label><select id="msg-statut"><option>Envoyé</option><option>Répondu</option><option>Sans réponse</option><option>À envoyer</option></select></div>
        <div class="form-group full"><label>Objet / Sujet</label><input type="text" id="msg-objet"></div>
        <div class="form-group"><label>Date envoi</label><input type="date" id="msg-denvoi"></div>
        <div class="form-group"><label>Date réponse</label><input type="date" id="msg-drep"></div>
        <div class="form-group"><label>Module lié</label><select id="msg-module"><option>Investisseur</option><option>Client</option><option>Partenaire</option><option>Candidature</option><option>Autre</option></select></div>
        <div class="form-group full"><label>Notes</label><textarea id="msg-notes"></textarea></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="RYBSEN.closeModal('modal-msg')">Annuler</button>
      <button class="btn btn-primary" onclick="saveMsg()">Enregistrer</button>
    </div>
  </div>
</div>

<script>
let allMsg=[];
const statColors={'Envoyé':'badge-navy','Répondu':'badge-green','Sans réponse':'badge-red','À envoyer':'badge-gold'};
async function loadMsg(){allMsg=await RYBSEN.api('msg_list');renderMsg();}
function renderMsg(){
  const q=document.getElementById('search-msg').value.toLowerCase();
  const c=document.getElementById('filter-canal').value;
  const s=document.getElementById('filter-statut-msg').value;
  const filtered=allMsg.filter(m=>(!q||(m.destinataire+m.organisation+m.objet||'').toLowerCase().includes(q))&&(!c||m.canal===c)&&(!s||m.statut===s));
  const body=document.getElementById('msg-body');
  if(!filtered.length){body.innerHTML='<tr><td colspan="8" style="text-align:center;padding:30px;color:#999">Aucun message</td></tr>';return;}
  body.innerHTML=filtered.map(m=>`<tr>
    <td><strong>${m.destinataire}</strong></td>
    <td>${m.organisation||'—'}</td>
    <td><span class="badge badge-navy">${m.canal}</span></td>
    <td>${m.objet||'—'}</td>
    <td><span class="badge ${statColors[m.statut]||'badge-grey'}">${m.statut}</span></td>
    <td>${m.date_envoi?new Date(m.date_envoi).toLocaleDateString('fr-FR'):'—'}</td>
    <td>${m.date_reponse?new Date(m.date_reponse).toLocaleDateString('fr-FR'):'—'}</td>
    <td><button onclick='editMsgById(${m.id})' class="btn btn-outline btn-sm">✏️</button>
        <button onclick="delMsg(${m.id})" class="btn btn-danger btn-sm">🗑</button></td>
  </tr>`).join('');
}

function editMsgById(id) {
  const m = allMsg.find(x => x.id === id);
  if (m) editMsg(m);
}
function openAdd(){document.getElementById('msg-id').value='';['msg-dest','msg-org','msg-objet','msg-denvoi','msg-drep','msg-notes'].forEach(i=>document.getElementById(i).value='');document.getElementById('msg-denvoi').value=new Date().toISOString().split('T')[0];RYBSEN.openModal('modal-msg');}
function editMsg(m){document.getElementById('msg-id').value=m.id;document.getElementById('msg-dest').value=m.destinataire;document.getElementById('msg-org').value=m.organisation||'';document.getElementById('msg-canal').value=m.canal;document.getElementById('msg-statut').value=m.statut;document.getElementById('msg-objet').value=m.objet||'';document.getElementById('msg-denvoi').value=m.date_envoi||'';document.getElementById('msg-drep').value=m.date_reponse||'';document.getElementById('msg-module').value=m.module_lie;document.getElementById('msg-notes').value=m.notes||'';RYBSEN.openModal('modal-msg');}
async function saveMsg(){const dest=document.getElementById('msg-dest').value.trim();if(!dest){RYBSEN.toast('Destinataire requis','error');return;}const r=await RYBSEN.api('msg_save',{id:document.getElementById('msg-id').value,destinataire:dest,organisation:document.getElementById('msg-org').value,canal:document.getElementById('msg-canal').value,statut:document.getElementById('msg-statut').value,objet:document.getElementById('msg-objet').value,date_envoi:document.getElementById('msg-denvoi').value||null,date_reponse:document.getElementById('msg-drep').value||null,module_lie:document.getElementById('msg-module').value,notes:document.getElementById('msg-notes').value});if(r.ok){RYBSEN.closeModal('modal-msg');RYBSEN.toast('Enregistré ✓');loadMsg();}else RYBSEN.toast(r.error||'Erreur','error');}
async function delMsg(id){if(!RYBSEN.confirmDelete())return;const r=await RYBSEN.api('msg_delete',{id});if(r.ok){RYBSEN.toast('Supprimé');loadMsg();}}
['search-msg','filter-canal','filter-statut-msg'].forEach(id=>document.getElementById(id).addEventListener('input',()=>renderMsg()));
loadMsg();
</script>
<?php require_once '../includes/footer.php'; ?>
