<?php
require_once '../config.php';
$pageTitle = 'Clients & Prospects';
$activePage = 'clients';
require_once '../includes/header.php';
?>
<div class="section-card">
  <div class="section-header">
    <div class="section-title">🏭 Pipeline Commercial — Clients & Prospects</div>
    <div class="section-actions"><button class="btn btn-primary" onclick="openAdd()">+ Prospect</button></div>
  </div>
  <div class="filters-bar">
    <input type="text" id="search-cli" placeholder="🔍 Rechercher...">
    <select id="filter-stade"><option value="">Tous stades</option><option>Prospect</option><option>Devis envoyé</option><option>Négociation</option><option>Bon de commande</option><option>Installé</option><option>Perdu</option></select>
    <select id="filter-secteur"><option value="">Tous secteurs</option><option>Offset / Imprimerie</option><option>Textile</option><option>Agri-food</option><option>Pharmaceutique</option><option>Autre</option></select>
    <select id="filter-source"><option value="">Toutes sources</option><option>MGM France</option><option>Direct</option><option>Apporteur affaires</option><option>Salon</option><option>LinkedIn</option></select>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Entreprise</th><th>Pays / Secteur</th><th>Source</th><th>Contact</th><th>Stade</th><th>Prix HT</th><th>Proba.</th><th>Closing prévu</th><th>Actions</th></tr></thead>
      <tbody id="cli-body"><tr><td colspan="9" style="text-align:center;padding:30px;color:#999">Chargement...</td></tr></tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="modal-detail">
  <div class="modal" style="max-width:720px">
    <div class="modal-header">
      <div class="modal-title" id="detail-title">Détail</div>
      <button class="modal-close" onclick="RYBSEN.closeModal('modal-detail')">✕</button>
    </div>
    <div class="modal-body" id="detail-body"></div>
  </div>
</div>

<div class="modal-overlay" id="modal-cli">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="modal-cli-title">Ajouter un prospect</div>
      <button class="modal-close" onclick="RYBSEN.closeModal('modal-cli')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="cli-id">
      <div class="form-grid">
        <div class="form-group"><label>Entreprise *</label><input type="text" id="cli-nom" placeholder="Nom de l'entreprise"></div>
        <div class="form-group"><label>Pays</label><input type="text" id="cli-pays" placeholder="France"></div>
        <div class="form-group"><label>Ville</label><input type="text" id="cli-ville"></div>
        <div class="form-group"><label>Secteur</label>
          <select id="cli-secteur"><option>Offset / Imprimerie</option><option>Textile</option><option>Agri-food</option><option>Pharmaceutique</option><option>Autre</option></select>
        </div>
        <div class="form-group"><label>Source</label>
          <select id="cli-source"><option>MGM France</option><option>Direct</option><option>Apporteur affaires</option><option>Salon</option><option>LinkedIn</option><option>Recommandation</option><option>Autre</option></select>
        </div>
        <div class="form-group"><label>Stade</label>
          <select id="cli-stade"><option>Prospect</option><option>Devis envoyé</option><option>Négociation</option><option>Bon de commande</option><option>Installé</option><option>Perdu</option><option>En pause</option></select>
        </div>
        <div class="form-group"><label>Contact nom</label><input type="text" id="cli-cnom"></div>
        <div class="form-group"><label>Contact email</label><input type="email" id="cli-cemail"></div>
        <div class="form-group"><label>Téléphone</label><input type="tel" id="cli-tel"></div>
        <div class="form-group"><label>Version AquaClean</label>
          <select id="cli-version"><option>V1</option><option>V2</option></select>
        </div>
        <div class="form-group"><label>Prix HT (€)</label><input type="number" id="cli-prix" value="30000"></div>
        <div class="form-group"><label>Probabilité closing %</label><input type="number" id="cli-proba" min="0" max="100" value="20"></div>
        <div class="form-group"><label>ROI estimé (mois)</label><input type="number" id="cli-roi" value="12"></div>
        <div class="form-group"><label>Closing prévu</label><input type="date" id="cli-dclosing"></div>
        <div class="form-group"><label>Machine attribuée</label><input type="text" id="cli-machine" placeholder="AQC-005"></div>
        <div class="form-group full"><label>Notes</label><textarea id="cli-notes"></textarea></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="RYBSEN.closeModal('modal-cli')">Annuler</button>
      <button class="btn btn-primary" onclick="saveCli()">Enregistrer</button>
    </div>
  </div>
</div>

<script>
let allCli = [];
const stadeColors={'Prospect':'badge-grey','Devis envoyé':'badge-navy','Négociation':'badge-gold','Bon de commande':'badge-teal','Installé':'badge-green','Perdu':'badge-red','En pause':'badge-grey'};
const stagesPipeline = ['Prospect','Devis envoyé','Négociation','Bon de commande','Installé'];

async function loadCli(){allCli=await RYBSEN.api('cli_list');renderCli(allCli);}

function renderCli(data){
  const q=document.getElementById('search-cli').value.toLowerCase();
  const s=document.getElementById('filter-stade').value;
  const sec=document.getElementById('filter-secteur').value;
  const src=document.getElementById('filter-source').value;
  const filtered=data.filter(c=>(!q||(c.nom_entreprise+c.pays+c.contact_nom||'').toLowerCase().includes(q))&&(!s||c.stade===s)&&(!sec||c.secteur===sec)&&(!src||c.source===src));
  const body=document.getElementById('cli-body');
  if(!filtered.length){body.innerHTML='<tr><td colspan="9" style="text-align:center;padding:30px;color:#999">Aucun prospect</td></tr>';return;}
  body.innerHTML=filtered.map(c=>`<tr style="cursor:pointer" onclick='if(event.target.closest("button"))return; openDetail(${JSON.stringify(c)})'>
    <td><strong>${c.nom_entreprise}</strong>${c.machine_attribuee?`<br><small style="color:#4A9B8F">${c.machine_attribuee}</small>`:''}</td>
    <td>${c.pays||'—'}<br><small style="color:#999">${c.secteur}</small></td>
    <td><span class="badge badge-grey">${c.source}</span></td>
    <td>${c.contact_nom||'—'}${c.contact_email?`<br><small><a href="mailto:${c.contact_email}" style="color:#4A9B8F">${c.contact_email}</a></small>`:''}</td>
    <td><span class="badge ${stadeColors[c.stade]||'badge-grey'}">${c.stade}</span></td>
    <td>${c.prix_ht?new Intl.NumberFormat('fr-FR').format(c.prix_ht)+' €':'—'}</td>
    <td><div style="display:flex;align-items:center;gap:6px"><div class="progress-bar" style="width:60px"><div class="progress-fill" style="width:${c.probabilite_closing}%"></div></div>${c.probabilite_closing}%</div></td>
    <td>${c.date_closing_prevu?new Date(c.date_closing_prevu).toLocaleDateString('fr-FR'):'—'}</td>
    <td><button onclick='editCli(${JSON.stringify(c)})' class="btn btn-outline btn-sm">✏️</button>
        <button onclick="delCli(${c.id})" class="btn btn-danger btn-sm">🗑</button></td>
  </tr>`).join('');
}

function openDetail(c) {
  const isLost = c.stade === 'Perdu' || c.stade === 'En pause';
  const pipelineStage = isLost ? stagesPipeline[0] : (stagesPipeline.includes(c.stade) ? c.stade : stagesPipeline[0]);
  document.getElementById('detail-body').innerHTML = `
    ${RYBSEN.renderPipeline(stagesPipeline, pipelineStage, isLost)}
    <div class="kpi-grid" style="margin-bottom:16px">
      <div class="kpi-card teal"><div class="kpi-label">Prix HT</div><div class="kpi-value" style="font-size:22px">${c.prix_ht?new Intl.NumberFormat('fr-FR').format(c.prix_ht)+' €':'—'}</div></div>
      <div class="kpi-card gold"><div class="kpi-label">Probabilité</div><div class="kpi-value" style="font-size:22px">${c.probabilite_closing}%</div></div>
      <div class="kpi-card navy"><div class="kpi-label">ROI estimé</div><div class="kpi-value" style="font-size:22px">${c.roi_estime_mois||'—'} mois</div></div>
    </div>
    <div class="form-grid">
      <div class="form-group"><label>Entreprise</label><div>${c.nom_entreprise}</div></div>
      <div class="form-group"><label>Pays / Secteur</label><div>${c.pays||'—'} · ${c.secteur}</div></div>
      <div class="form-group"><label>Contact</label><div>${c.contact_nom||'—'}</div></div>
      <div class="form-group"><label>Email</label><div>${c.contact_email||'—'}</div></div>
      <div class="form-group"><label>Source</label><div>${c.source}</div></div>
      <div class="form-group"><label>Machine attribuée</label><div>${c.machine_attribuee||'—'}</div></div>
      <div class="form-group full"><label>Notes</label><div>${c.notes||'—'}</div></div>
    </div>
  `;
  document.getElementById('detail-title').textContent = c.nom_entreprise;
  RYBSEN.openModal('modal-detail');
}

function openAdd(){document.getElementById('modal-cli-title').textContent='Ajouter un prospect';document.getElementById('cli-id').value='';document.getElementById('cli-prix').value='30000';document.getElementById('cli-proba').value='20';document.getElementById('cli-roi').value='12';['cli-nom','cli-pays','cli-ville','cli-cnom','cli-cemail','cli-tel','cli-machine','cli-dclosing','cli-notes'].forEach(i=>document.getElementById(i).value='');RYBSEN.openModal('modal-cli');}

function editCli(c){document.getElementById('modal-cli-title').textContent='Modifier prospect';document.getElementById('cli-id').value=c.id;document.getElementById('cli-nom').value=c.nom_entreprise;document.getElementById('cli-pays').value=c.pays||'';document.getElementById('cli-ville').value=c.ville||'';document.getElementById('cli-secteur').value=c.secteur;document.getElementById('cli-source').value=c.source;document.getElementById('cli-stade').value=c.stade;document.getElementById('cli-cnom').value=c.contact_nom||'';document.getElementById('cli-cemail').value=c.contact_email||'';document.getElementById('cli-tel').value=c.contact_tel||'';document.getElementById('cli-version').value=c.version_aquaclean||'V1';document.getElementById('cli-prix').value=c.prix_ht||30000;document.getElementById('cli-proba').value=c.probabilite_closing||0;document.getElementById('cli-roi').value=c.roi_estime_mois||12;document.getElementById('cli-dclosing').value=c.date_closing_prevu||'';document.getElementById('cli-machine').value=c.machine_attribuee||'';document.getElementById('cli-notes').value=c.notes||'';RYBSEN.openModal('modal-cli');}

async function saveCli(){const nom=document.getElementById('cli-nom').value.trim();if(!nom){RYBSEN.toast('Nom requis','error');return;}const r=await RYBSEN.api('cli_save',{id:document.getElementById('cli-id').value,nom_entreprise:nom,pays:document.getElementById('cli-pays').value,ville:document.getElementById('cli-ville').value,secteur:document.getElementById('cli-secteur').value,source:document.getElementById('cli-source').value,stade:document.getElementById('cli-stade').value,contact_nom:document.getElementById('cli-cnom').value,contact_email:document.getElementById('cli-cemail').value,contact_tel:document.getElementById('cli-tel').value,version_aquaclean:document.getElementById('cli-version').value,prix_ht:document.getElementById('cli-prix').value||30000,probabilite_closing:document.getElementById('cli-proba').value||0,roi_estime_mois:document.getElementById('cli-roi').value||12,date_closing_prevu:document.getElementById('cli-dclosing').value||null,machine_attribuee:document.getElementById('cli-machine').value,notes:document.getElementById('cli-notes').value});if(r.ok){RYBSEN.closeModal('modal-cli');RYBSEN.toast('Enregistré ✓');loadCli();}else RYBSEN.toast(r.error||'Erreur','error');}

async function delCli(id){if(!RYBSEN.confirmDelete())return;const r=await RYBSEN.api('cli_delete',{id});if(r.ok){RYBSEN.toast('Supprimé');loadCli();}}

['search-cli','filter-stade','filter-secteur','filter-source'].forEach(id=>document.getElementById(id).addEventListener('input',()=>renderCli(allCli)));
loadCli();
</script>
<?php require_once '../includes/footer.php'; ?>
