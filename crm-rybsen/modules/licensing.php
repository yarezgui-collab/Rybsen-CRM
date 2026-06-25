<?php
require_once '../config.php';
$pageTitle = 'Licensing Constructeurs';
$activePage = 'licensing';
require_once '../includes/header.php';
?>
<div class="alert-box info">
  <span>ℹ️</span>
  <div><strong>Prérequis brevet :</strong> KBA, Heidelberg, Baldwin, Komori et RMGT nécessitent la confirmation du statut EP3444017 (epo.org) avant toute approche. Komori et RMGT nécessitent en plus une extension PCT.</div>
</div>
<div class="section-card">
  <div class="section-header">
    <div class="section-title">🔩 Licensing — 7 Constructeurs Cibles</div>
    <div class="section-actions"><button class="btn btn-primary" onclick="openAdd()">+ Constructeur</button></div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Priorité</th><th>Constructeur</th><th>Pays</th><th>Parc mondial</th><th>Contact</th><th>Statut</th><th>Prérequis brevet</th><th>Actions</th></tr></thead>
      <tbody id="lic-body"><tr><td colspan="8" style="text-align:center;padding:30px;color:#999">Chargement...</td></tr></tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="modal-lic">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="modal-lic-title">Ajouter un constructeur</div>
      <button class="modal-close" onclick="RYBSEN.closeModal('modal-lic')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="lic-id">
      <div class="form-grid">
        <div class="form-group"><label>Constructeur *</label><input type="text" id="lic-nom"></div>
        <div class="form-group"><label>Pays</label><input type="text" id="lic-pays"></div>
        <div class="form-group"><label>Parc machines mondial</label><input type="number" id="lic-parc"></div>
        <div class="form-group"><label>Priorité</label><select id="lic-prio"><option>🔴 Priorité 1</option><option>🟡 Priorité 2</option><option>🟢 Priorité 3</option></select></div>
        <div class="form-group"><label>Statut</label><select id="lic-statut"><option>Cible identifiée</option><option>Approche initiale</option><option>Intérêt confirmé</option><option>Négociation</option><option>Accord signé</option><option>Refusé</option></select></div>
        <div class="form-group"><label>Contact nom</label><input type="text" id="lic-cnom"></div>
        <div class="form-group"><label>Contact email</label><input type="email" id="lic-cemail"></div>
        <div class="form-group"><label>Prérequis brevet</label><select id="lic-brevet"><option value="0">Non requis</option><option value="1">EP3444017 requis</option></select></div>
        <div class="form-group full"><label>Notes / Stratégie</label><textarea id="lic-notes"></textarea></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="RYBSEN.closeModal('modal-lic')">Annuler</button>
      <button class="btn btn-primary" onclick="saveLic()">Enregistrer</button>
    </div>
  </div>
</div>

<script>
let allLic=[];
const statColors={'Cible identifiée':'badge-grey','Approche initiale':'badge-navy','Intérêt confirmé':'badge-teal','Négociation':'badge-gold','Accord signé':'badge-green','Refusé':'badge-red'};
async function loadLic(){allLic=await RYBSEN.api('lic_list');renderLic();}
function renderLic(){
  const body=document.getElementById('lic-body');
  body.innerHTML=allLic.map(l=>`<tr>
    <td>${l.priorite}</td>
    <td><strong>${l.constructeur}</strong></td>
    <td>${l.pays||'—'}</td>
    <td>${l.parc_machines_mondial?new Intl.NumberFormat('fr-FR').format(l.parc_machines_mondial):'—'}</td>
    <td>${l.contact_nom||'—'}${l.contact_email?`<br><small><a href="mailto:${l.contact_email}" style="color:#4A9B8F">${l.contact_email}</a></small>`:''}</td>
    <td><span class="badge ${statColors[l.statut]||'badge-grey'}">${l.statut}</span></td>
    <td>${l.prerequis_brevet=='1'?'<span class="badge badge-gold">⚠️ EP requis</span>':'<span class="badge badge-green">✓ OK</span>'}</td>
    <td><button onclick='editLicById(${l.id})' class="btn btn-outline btn-sm">✏️</button></td>
  </tr>`).join('');
}

function editLicById(id) {
  const l = allLic.find(x => x.id === id);
  if (l) editLic(l);
}
function openAdd(){document.getElementById('modal-lic-title').textContent='Ajouter constructeur';document.getElementById('lic-id').value='';['lic-nom','lic-pays','lic-parc','lic-cnom','lic-cemail','lic-notes'].forEach(i=>document.getElementById(i).value='');RYBSEN.openModal('modal-lic');}
function editLic(l){document.getElementById('modal-lic-title').textContent='Modifier constructeur';document.getElementById('lic-id').value=l.id;document.getElementById('lic-nom').value=l.constructeur;document.getElementById('lic-pays').value=l.pays||'';document.getElementById('lic-parc').value=l.parc_machines_mondial||'';document.getElementById('lic-prio').value=l.priorite;document.getElementById('lic-statut').value=l.statut;document.getElementById('lic-cnom').value=l.contact_nom||'';document.getElementById('lic-cemail').value=l.contact_email||'';document.getElementById('lic-brevet').value=l.prerequis_brevet||0;document.getElementById('lic-notes').value=l.notes||'';RYBSEN.openModal('modal-lic');}
async function saveLic(){const nom=document.getElementById('lic-nom').value.trim();if(!nom){RYBSEN.toast('Nom requis','error');return;}const r=await RYBSEN.api('lic_save',{id:document.getElementById('lic-id').value,constructeur:nom,pays:document.getElementById('lic-pays').value,parc_machines_mondial:document.getElementById('lic-parc').value||0,priorite:document.getElementById('lic-prio').value,statut:document.getElementById('lic-statut').value,contact_nom:document.getElementById('lic-cnom').value,contact_email:document.getElementById('lic-cemail').value,prerequis_brevet:document.getElementById('lic-brevet').value,notes:document.getElementById('lic-notes').value});if(r.ok){RYBSEN.closeModal('modal-lic');RYBSEN.toast('Enregistré ✓');loadLic();}else RYBSEN.toast(r.error||'Erreur','error');}
loadLic();
</script>
<?php require_once '../includes/footer.php'; ?>
