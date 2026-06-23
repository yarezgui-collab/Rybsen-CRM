<?php
require_once '../config.php';
$pageTitle = 'Candidatures & Programmes';
$activePage = 'candidatures';
require_once '../includes/header.php';
?>
<div class="section-card">
  <div class="section-header">
    <div class="section-title">📋 Candidatures & Programmes de financement</div>
    <div class="section-actions">
      <button class="btn btn-primary" onclick="openAdd()">+ Candidature</button>
    </div>
  </div>
  <div class="filters-bar">
    <input type="text" id="search-cand" placeholder="🔍 Rechercher...">
    <select id="filter-cand-statut"><option value="">Tous statuts</option><option>À préparer</option><option>Soumis</option><option>En attente décision</option><option>Accepté</option><option>Refusé</option><option>En cours remboursement</option></select>
    <select id="filter-cand-type"><option value="">Tous types</option><option>Subvention</option><option>Prêt</option><option>Accélération</option><option>Prix/Concours</option><option>Investissement public</option></select>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Priorité</th><th>Programme</th><th>Organisme</th><th>Montant</th><th>Statut</th><th>Soumis le</th><th>Réponse prévue</th><th>Actions</th></tr></thead>
      <tbody id="cand-body"><tr><td colspan="8" style="text-align:center;padding:30px;color:#999">Chargement...</td></tr></tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="modal-cand">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="modal-cand-title">Ajouter une candidature</div>
      <button class="modal-close" onclick="RYBSEN.closeModal('modal-cand')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="cand-id">
      <div class="form-grid">
        <div class="form-group"><label>Programme *</label><input type="text" id="cand-prog" placeholder="MAIR / Smart Capital"></div>
        <div class="form-group"><label>Organisme</label><input type="text" id="cand-org" placeholder="Smart Capital Tunisie"></div>
        <div class="form-group"><label>Type</label>
          <select id="cand-type"><option>Subvention</option><option>Prêt</option><option>Accélération</option><option>Prix/Concours</option><option>Investissement public</option><option>Autre</option></select>
        </div>
        <div class="form-group"><label>Pays</label><input type="text" id="cand-pays" placeholder="Tunisie"></div>
        <div class="form-group"><label>Montant demandé</label><input type="number" id="cand-montant" placeholder="200000"></div>
        <div class="form-group"><label>Devise</label>
          <select id="cand-devise"><option>TND</option><option>EUR</option><option>USD</option></select>
        </div>
        <div class="form-group"><label>Priorité</label>
          <select id="cand-prio"><option>🔴 Urgent</option><option>🟡 Important</option><option>🟢 Normal</option></select>
        </div>
        <div class="form-group"><label>Statut</label>
          <select id="cand-statut"><option>À préparer</option><option>Soumis</option><option>En attente décision</option><option>Accepté</option><option>Refusé</option><option>Reporté</option><option>En cours remboursement</option></select>
        </div>
        <div class="form-group"><label>Date soumission</label><input type="date" id="cand-dsub"></div>
        <div class="form-group"><label>Réponse prévue</label><input type="date" id="cand-drep"></div>
        <div class="form-group"><label>Contact référent</label><input type="text" id="cand-contact" placeholder="Nom du contact"></div>
        <div class="form-group"><label>Email contact</label><input type="email" id="cand-email"></div>
        <div class="form-group full"><label>Notes</label><textarea id="cand-notes"></textarea></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="RYBSEN.closeModal('modal-cand')">Annuler</button>
      <button class="btn btn-primary" onclick="saveCand()">Enregistrer</button>
    </div>
  </div>
</div>

<script>
let allCand = [];
const statColors = {'Accepté':'badge-green','Soumis':'badge-navy','En attente décision':'badge-gold','Refusé':'badge-red','Reporté':'badge-grey','En cours remboursement':'badge-teal','À préparer':'badge-grey'};

async function loadCand() {
  allCand = await RYBSEN.api('cand_list');
  renderCand(allCand);
}

function renderCand(data) {
  const q = document.getElementById('search-cand').value.toLowerCase();
  const s = document.getElementById('filter-cand-statut').value;
  const t = document.getElementById('filter-cand-type').value;
  const filtered = data.filter(c =>
    (!q||(c.programme+c.organisme+c.notes||'').toLowerCase().includes(q))&&
    (!s||c.statut===s)&&(!t||c.type===t));
  const body = document.getElementById('cand-body');
  if (!filtered.length){body.innerHTML='<tr><td colspan="8" style="text-align:center;padding:30px;color:#999">Aucune candidature</td></tr>';return;}
  body.innerHTML = filtered.map(c => `<tr>
    <td>${c.priorite}</td>
    <td><strong>${c.programme}</strong></td>
    <td>${c.organisme||'—'}</td>
    <td>${c.montant_demande?new Intl.NumberFormat('fr-FR').format(c.montant_demande)+' '+c.devise:'—'}</td>
    <td><span class="badge ${statColors[c.statut]||'badge-grey'}">${c.statut}</span></td>
    <td>${c.date_soumission?new Date(c.date_soumission).toLocaleDateString('fr-FR'):'—'}</td>
    <td>${c.date_reponse_prevue?new Date(c.date_reponse_prevue).toLocaleDateString('fr-FR'):'—'}</td>
    <td><button onclick='editCandById(${c.id})' class="btn btn-outline btn-sm">✏️</button>
        <button onclick="delCand(${c.id})" class="btn btn-danger btn-sm">🗑</button></td>
  </tr>`).join('');
}

function editCandById(id) {
  const c = allCand.find(x => x.id === id);
  if (c) editCand(c);
}

function openAdd(){document.getElementById('modal-cand-title').textContent='Ajouter une candidature';document.getElementById('cand-id').value='';['cand-prog','cand-org','cand-pays','cand-montant','cand-contact','cand-email','cand-dsub','cand-drep','cand-notes'].forEach(i=>document.getElementById(i).value='');RYBSEN.openModal('modal-cand');}

function editCand(c){
  document.getElementById('modal-cand-title').textContent='Modifier candidature';
  document.getElementById('cand-id').value=c.id;
  document.getElementById('cand-prog').value=c.programme;
  document.getElementById('cand-org').value=c.organisme||'';
  document.getElementById('cand-type').value=c.type;
  document.getElementById('cand-pays').value=c.pays||'';
  document.getElementById('cand-montant').value=c.montant_demande||'';
  document.getElementById('cand-devise').value=c.devise||'TND';
  document.getElementById('cand-prio').value=c.priorite;
  document.getElementById('cand-statut').value=c.statut;
  document.getElementById('cand-dsub').value=c.date_soumission||'';
  document.getElementById('cand-drep').value=c.date_reponse_prevue||'';
  document.getElementById('cand-contact').value=c.contact_referent||'';
  document.getElementById('cand-email').value=c.contact_email||'';
  document.getElementById('cand-notes').value=c.notes||'';
  RYBSEN.openModal('modal-cand');
}

async function saveCand(){
  const prog=document.getElementById('cand-prog').value.trim();
  if(!prog){RYBSEN.toast('Le programme est requis','error');return;}
  const r=await RYBSEN.api('cand_save',{id:document.getElementById('cand-id').value,programme:prog,organisme:document.getElementById('cand-org').value,type:document.getElementById('cand-type').value,pays:document.getElementById('cand-pays').value,montant_demande:document.getElementById('cand-montant').value||0,devise:document.getElementById('cand-devise').value,priorite:document.getElementById('cand-prio').value,statut:document.getElementById('cand-statut').value,date_soumission:document.getElementById('cand-dsub').value||null,date_reponse_prevue:document.getElementById('cand-drep').value||null,contact_referent:document.getElementById('cand-contact').value,contact_email:document.getElementById('cand-email').value,notes:document.getElementById('cand-notes').value});
  if(r.ok){RYBSEN.closeModal('modal-cand');RYBSEN.toast('Enregistré ✓');loadCand();}else RYBSEN.toast(r.error||'Erreur','error');
}

async function delCand(id){if(!RYBSEN.confirmDelete())return;const r=await RYBSEN.api('cand_delete',{id});if(r.ok){RYBSEN.toast('Supprimé');loadCand();}}

['search-cand','filter-cand-statut','filter-cand-type'].forEach(id=>document.getElementById(id).addEventListener('input',()=>renderCand(allCand)));
loadCand();
</script>
<?php require_once '../includes/footer.php'; ?>
