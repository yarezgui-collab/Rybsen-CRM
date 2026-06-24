<?php
require_once '../config.php';
$pageTitle = 'Partenaires & Distributeurs';
$activePage = 'partenaires';
require_once '../includes/header.php';
?>
<div class="section-card">
  <div class="section-header">
    <div class="section-title">🤝 Partenaires, Distributeurs & Sous-traitants</div>
    <div class="section-actions"><button class="btn btn-primary" onclick="openAdd()">+ Partenaire</button></div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Partenaire</th><th>Type</th><th>Territoire</th><th>Contact</th><th>Contrat</th><th>Marge</th><th>Volume Obj./Réel</th><th>Statut</th><th>Expiration</th><th>Actions</th></tr></thead>
      <tbody id="part-body"><tr><td colspan="10" style="text-align:center;padding:30px;color:#999">Chargement...</td></tr></tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="modal-part">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="modal-part-title">Ajouter un partenaire</div>
      <button class="modal-close" onclick="RYBSEN.closeModal('modal-part')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="part-id">
      <div class="form-grid">
        <div class="form-group"><label>Nom *</label><input type="text" id="part-nom"></div>
        <div class="form-group"><label>Type</label><select id="part-type"><option>Distributeur</option><option>OEM</option><option>Apporteur affaires</option><option>Fournisseur</option><option>Sous-traitant</option><option>Autre</option></select></div>
        <div class="form-group"><label>Territoire</label><input type="text" id="part-territoire" placeholder="France"></div>
        <div class="form-group"><label>Pays</label><input type="text" id="part-pays"></div>
        <div class="form-group"><label>Contact nom</label><input type="text" id="part-cnom"></div>
        <div class="form-group"><label>Contact email</label><input type="email" id="part-cemail"></div>
        <div class="form-group"><label>Contrat signé</label><select id="part-contrat"><option value="0">Non</option><option value="1">Oui</option></select></div>
        <div class="form-group"><label>Type contrat</label><input type="text" id="part-typecontrat" placeholder="Lettre de Bonne Intention"></div>
        <div class="form-group"><label>Date expiration</label><input type="date" id="part-exp"></div>
        <div class="form-group"><label>Marge %</label><input type="number" id="part-marge" step="0.01" placeholder="25"></div>
        <div class="form-group"><label>Volume objectif</label><input type="number" id="part-vobj" placeholder="10"></div>
        <div class="form-group"><label>Statut</label><select id="part-statut"><option>Actif</option><option>Phase 2</option><option>En négociation</option><option>Suspendu</option><option>Terminé</option></select></div>
        <div class="form-group full"><label>Notes</label><textarea id="part-notes"></textarea></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="RYBSEN.closeModal('modal-part')">Annuler</button>
      <button class="btn btn-primary" onclick="savePart()">Enregistrer</button>
    </div>
  </div>
</div>

<script>
let allPart = [];
async function loadPart(){allPart=await RYBSEN.api('part_list');renderPart();}
function renderPart(){
  const body=document.getElementById('part-body');
  if(!allPart.length){body.innerHTML='<tr><td colspan="10" style="text-align:center;padding:30px;color:#999">Aucun partenaire</td></tr>';return;}
  body.innerHTML=allPart.map(p=>{
    const exp=p.date_expiration?new Date(p.date_expiration):null;
    const expiring=exp&&(exp-new Date())<90*86400000;
    return `<tr>
      <td><strong>${p.nom}</strong></td>
      <td><span class="badge badge-navy">${p.type}</span></td>
      <td>${p.territoire||'—'}<br><small style="color:#999">${p.pays||''}</small></td>
      <td>${p.contact_nom||'—'}${p.contact_email?`<br><small><a href="mailto:${p.contact_email}" style="color:#4A9B8F">${p.contact_email}</a></small>`:''}</td>
      <td>${p.contrat_signe=='1'?'<span class="badge badge-green">✓ Signé</span>':'<span class="badge badge-grey">Non signé</span>'}<br><small style="color:#999">${p.type_contrat||''}</small></td>
      <td>${p.marge_pct?p.marge_pct+'%':'—'}</td>
      <td>${p.volume_objectif||0} / <strong>${p.volume_realise||0}</strong> unités</td>
      <td><span class="badge ${p.statut==='Actif'?'badge-green':p.statut==='Phase 2'?'badge-gold':'badge-grey'}">${p.statut}</span></td>
      <td style="${expiring?'color:#dc2626;font-weight:600':''}">${p.date_expiration?new Date(p.date_expiration).toLocaleDateString('fr-FR'):'—'}</td>
      <td><button onclick='editPartById(${p.id})' class="btn btn-outline btn-sm">✏️</button>
          <button onclick="delPart(${p.id})" class="btn btn-danger btn-sm">🗑</button></td>
    </tr>`;
  }).join('');
}

function editPartById(id) {
  const p = allPart.find(x => x.id === id);
  if (p) editPart(p);
}
function openAdd(){document.getElementById('modal-part-title').textContent='Ajouter un partenaire';document.getElementById('part-id').value='';['part-nom','part-territoire','part-pays','part-cnom','part-cemail','part-typecontrat','part-exp','part-marge','part-vobj','part-notes'].forEach(i=>document.getElementById(i).value='');RYBSEN.openModal('modal-part');}
function editPart(p){document.getElementById('modal-part-title').textContent='Modifier partenaire';document.getElementById('part-id').value=p.id;document.getElementById('part-nom').value=p.nom;document.getElementById('part-type').value=p.type;document.getElementById('part-territoire').value=p.territoire||'';document.getElementById('part-pays').value=p.pays||'';document.getElementById('part-cnom').value=p.contact_nom||'';document.getElementById('part-cemail').value=p.contact_email||'';document.getElementById('part-contrat').value=p.contrat_signe||0;document.getElementById('part-typecontrat').value=p.type_contrat||'';document.getElementById('part-exp').value=p.date_expiration||'';document.getElementById('part-marge').value=p.marge_pct||'';document.getElementById('part-vobj').value=p.volume_objectif||0;document.getElementById('part-statut').value=p.statut;document.getElementById('part-notes').value=p.notes||'';RYBSEN.openModal('modal-part');}
async function savePart(){const nom=document.getElementById('part-nom').value.trim();if(!nom){RYBSEN.toast('Nom requis','error');return;}const r=await RYBSEN.api('part_save',{id:document.getElementById('part-id').value,nom,type:document.getElementById('part-type').value,territoire:document.getElementById('part-territoire').value,pays:document.getElementById('part-pays').value,contact_nom:document.getElementById('part-cnom').value,contact_email:document.getElementById('part-cemail').value,contrat_signe:document.getElementById('part-contrat').value,type_contrat:document.getElementById('part-typecontrat').value,date_expiration:document.getElementById('part-exp').value||null,marge_pct:document.getElementById('part-marge').value||0,volume_objectif:document.getElementById('part-vobj').value||0,statut:document.getElementById('part-statut').value,notes:document.getElementById('part-notes').value});if(r.ok){RYBSEN.closeModal('modal-part');RYBSEN.toast('Enregistré ✓');loadPart();}else RYBSEN.toast(r.error||'Erreur','error');}
async function delPart(id){if(!RYBSEN.confirmDelete())return;const r=await RYBSEN.api('part_delete',{id});if(r.ok){RYBSEN.toast('Supprimé');loadPart();}}
loadPart();
</script>
<?php require_once '../includes/footer.php'; ?>
