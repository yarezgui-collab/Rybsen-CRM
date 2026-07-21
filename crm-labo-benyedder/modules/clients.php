<?php
require_once '../config.php';
requireRole(['admin','labo']);
$user = currentUser();
$estAdmin = $user['role'] === 'admin';
$pageTitle = 'Gestionnaire de clients';
$activePage = 'clients';
require_once '../includes/header.php';
?>
<div class="section-card">
  <div class="section-header">
    <div class="section-title">🏪 Gestionnaire de clients</div>
    <div class="section-actions"><button class="btn btn-primary" onclick="openAdd()">+ Nouveau client</button></div>
  </div>
  <div class="filters-bar">
    <input type="text" id="search" placeholder="🔍 Nom, code, ville, téléphone...">
    <select id="f-canal" onchange="render()">
      <option value="">Tous les canaux</option>
      <option value="terme">Client à terme</option>
      <option value="point_vente">Point de vente</option>
      <option value="franchise">Franchise</option>
    </select>
    <select id="f-actif" onchange="render()">
      <option value="">Actifs et inactifs</option>
      <option value="1" selected>Actifs</option>
      <option value="0">Inactifs</option>
    </select>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Code</th><th>Nom</th><th>Canaux</th><th>Paiement</th><th>Téléphone</th><th>Encours</th><th>Accès</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody id="body"><tr><td colspan="9" style="text-align:center;padding:30px;color:var(--text-muted)">Chargement...</td></tr></tbody>
    </table>
  </div>
  <div style="padding:10px 4px;color:var(--text-muted);font-size:13px" id="count"></div>
</div>

<!-- Modal fiche client -->
<div class="modal-overlay" id="modal-client">
  <div class="modal" style="max-width:720px">
    <div class="modal-header"><div class="modal-title" id="modal-title">Fiche client</div><button class="modal-close" onclick="LABO.closeModal('modal-client')">✕</button></div>
    <div class="modal-body">
      <input type="hidden" id="c-id">
      <div class="form-grid">
        <div class="form-group"><label>Code client</label><input type="text" id="c-code" placeholder="Réf unique (facultatif)"></div>
        <div class="form-group"><label>Nom / Raison sociale *</label><input type="text" id="c-nom"></div>
      </div>

      <div style="margin-top:14px"><label style="font-weight:600;color:var(--wheat,#6B4A2F)">Interfaces de commande autorisées *</label>
        <div style="display:flex;flex-wrap:wrap;gap:16px;margin-top:8px">
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer"><input type="checkbox" id="c-canal-pv"> Point de vente (caisse)</label>
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer"><input type="checkbox" id="c-canal-terme"> Client à terme</label>
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer"><input type="checkbox" id="c-canal-franchise"> Franchise</label>
        </div>
      </div>

      <div class="form-grid" style="margin-top:14px">
        <div class="form-group"><label>Mode de paiement</label>
          <select id="c-mode" onchange="onModeChange()">
            <option value="comptant">Comptant (espèces)</option>
            <option value="terme">À terme (crédit)</option>
          </select>
        </div>
        <div class="form-group" id="wrap-delai"><label>Délai de paiement (jours)</label><input type="number" id="c-delai" value="30" min="0"></div>
        <div class="form-group"><label>Plafond d'encours (DT)</label><input type="number" step="0.001" id="c-plafond" placeholder="illimité si vide"></div>
        <div class="form-group"><label>Remise habituelle (%)</label><input type="number" step="0.1" id="c-remise" value="0"></div>
      </div>

      <div style="margin-top:14px;font-weight:600;color:var(--wheat,#6B4A2F);font-size:13px">Coordonnées</div>
      <div class="form-grid" style="margin-top:6px">
        <div class="form-group"><label>Personne de contact</label><input type="text" id="c-contact"></div>
        <div class="form-group"><label>Téléphone</label><input type="text" id="c-tel"></div>
        <div class="form-group"><label>Email</label><input type="email" id="c-email"></div>
        <div class="form-group"><label>Ville</label><input type="text" id="c-ville"></div>
        <div class="form-group full"><label>Adresse</label><input type="text" id="c-adresse"></div>
        <div class="form-group"><label>Matricule fiscal</label><input type="text" id="c-mf"></div>
        <div class="form-group"><label>N° RC</label><input type="text" id="c-rc"></div>
        <div class="form-group full"><label>Notes</label><input type="text" id="c-notes"></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="LABO.closeModal('modal-client')">Annuler</button>
      <button class="btn btn-primary" onclick="save()">Enregistrer</button>
    </div>
  </div>
</div>

<?php if ($estAdmin): ?>
<!-- Modal accès self-service -->
<div class="modal-overlay" id="modal-acces">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Accès self-service</div><button class="modal-close" onclick="LABO.closeModal('modal-acces')">✕</button></div>
    <div class="modal-body">
      <div class="alert-box info">Permet à ce client de se connecter et de passer ses commandes lui-même (facturées). Laissez-le vide pour ne pas donner d'accès.</div>
      <input type="hidden" id="acc-client-id">
      <div class="form-group"><label>Email de connexion</label><input type="email" id="acc-email"></div>
      <div class="form-group" style="margin-top:12px"><label>Mot de passe</label><input type="text" id="acc-password" placeholder="Communiquez-le au client"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="LABO.closeModal('modal-acces')">Annuler</button>
      <button class="btn btn-primary" onclick="creerAcces()">Créer / réinitialiser l'accès</button>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
const estAdmin = <?= $estAdmin ? 'true':'false' ?>;
let all = [];
const canalBadges = c => {
  let b='';
  if (c.canal_point_vente==1) b+='<span class="badge badge-teal">PV</span> ';
  if (c.canal_terme==1) b+='<span class="badge badge-navy">Terme</span> ';
  if (c.canal_franchise==1) b+='<span class="badge badge-gold">Franchise</span> ';
  return b||'<span class="badge badge-grey">—</span>';
};

async function load() { all = await LABO.api('client_list'); render(); }
function render() {
  const e=LABO.escape, q=document.getElementById('search').value.toLowerCase();
  const fc=document.getElementById('f-canal').value, fa=document.getElementById('f-actif').value;
  const rows = all.filter(c => {
    if (q && !((c.nom||'').toLowerCase().includes(q) || (c.code_externe||'').toLowerCase().includes(q) || (c.ville||'').toLowerCase().includes(q) || (c.telephone||'').toLowerCase().includes(q))) return false;
    if (fc==='terme' && c.canal_terme!=1) return false;
    if (fc==='point_vente' && c.canal_point_vente!=1) return false;
    if (fc==='franchise' && c.canal_franchise!=1) return false;
    if (fa!=='' && String(c.actif)!==fa) return false;
    return true;
  });
  document.getElementById('body').innerHTML = rows.length ? rows.map(c => `
    <tr style="cursor:pointer" onclick="if(event.target.closest('button'))return; edit(${c.id})">
      <td>${e(c.code_externe)||'—'}</td>
      <td><strong>${e(c.nom)}</strong></td>
      <td>${canalBadges(c)}</td>
      <td>${c.mode_paiement_defaut==='terme'?('<span class="badge badge-gold">Terme '+c.delai_paiement_jours+'j</span>'):'<span class="badge badge-green">Comptant</span>'}</td>
      <td>${e(c.telephone)||'—'}</td>
      <td class="num">${parseFloat(c.encours)>0?('<span style="color:var(--red);font-weight:700">'+LABO.formatCurrency(c.encours)+'</span>'):'—'}</td>
      <td>${c.a_un_acces==1?'<span class="badge badge-green">Oui</span>':'—'}</td>
      <td><span class="badge ${c.actif==1?'badge-green':'badge-grey'}">${c.actif==1?'Actif':'Inactif'}</span></td>
      <td>
        <button class="btn btn-outline btn-sm" onclick="edit(${c.id})">✏️</button>
        ${estAdmin?`<button class="btn btn-outline btn-sm" onclick="ouvrirAcces(${c.id})" title="Accès self-service">🔑</button>`:''}
        <button class="btn btn-outline btn-sm" onclick="toggleActif(${c.id})">${c.actif==1?'⏸':'▶️'}</button>
      </td>
    </tr>`).join('') : '<tr><td colspan="9" style="text-align:center;padding:30px;color:var(--text-muted)">Aucun client</td></tr>';
  document.getElementById('count').textContent = rows.length + ' client(s) affiché(s) sur ' + all.length;
}
function onModeChange(){ document.getElementById('wrap-delai').style.display = document.getElementById('c-mode').value==='terme'?'':'none'; }
function fill(c){
  c=c||{};
  document.getElementById('c-id').value=c.id||'';
  document.getElementById('c-code').value=c.code_externe||'';
  document.getElementById('c-nom').value=c.nom||'';
  document.getElementById('c-canal-pv').checked=c.canal_point_vente==1;
  document.getElementById('c-canal-terme').checked=c.canal_terme==1;
  document.getElementById('c-canal-franchise').checked=c.canal_franchise==1;
  document.getElementById('c-mode').value=c.mode_paiement_defaut||'comptant';
  document.getElementById('c-delai').value=c.delai_paiement_jours!=null?c.delai_paiement_jours:30;
  document.getElementById('c-plafond').value=c.plafond_encours!=null?c.plafond_encours:'';
  document.getElementById('c-remise').value=c.remise_pct!=null?c.remise_pct:0;
  document.getElementById('c-contact').value=c.contact_nom||'';
  document.getElementById('c-tel').value=c.telephone||'';
  document.getElementById('c-email').value=c.email||'';
  document.getElementById('c-ville').value=c.ville||'';
  document.getElementById('c-adresse').value=c.adresse||'';
  document.getElementById('c-mf').value=c.matricule_fiscal||'';
  document.getElementById('c-rc').value=c.rc||'';
  document.getElementById('c-notes').value=c.notes||'';
  onModeChange();
}
function openAdd(){ document.getElementById('modal-title').textContent='Nouveau client'; fill({canal_point_vente:1}); LABO.openModal('modal-client'); }
function edit(id){ const c=all.find(x=>x.id===id); if(!c)return; document.getElementById('modal-title').textContent='Modifier — '+c.nom; fill(c); LABO.openModal('modal-client'); }
async function save(){
  const p={
    id:document.getElementById('c-id').value,
    code_externe:document.getElementById('c-code').value,
    nom:document.getElementById('c-nom').value.trim(),
    canal_point_vente:document.getElementById('c-canal-pv').checked?1:0,
    canal_terme:document.getElementById('c-canal-terme').checked?1:0,
    canal_franchise:document.getElementById('c-canal-franchise').checked?1:0,
    mode_paiement_defaut:document.getElementById('c-mode').value,
    delai_paiement_jours:document.getElementById('c-delai').value||30,
    plafond_encours:document.getElementById('c-plafond').value,
    remise_pct:document.getElementById('c-remise').value||0,
    contact_nom:document.getElementById('c-contact').value,
    telephone:document.getElementById('c-tel').value,
    email:document.getElementById('c-email').value,
    ville:document.getElementById('c-ville').value,
    adresse:document.getElementById('c-adresse').value,
    matricule_fiscal:document.getElementById('c-mf').value,
    rc:document.getElementById('c-rc').value,
    notes:document.getElementById('c-notes').value,
    actif:1
  };
  if(!p.nom){ LABO.toast('Nom requis','error'); return; }
  const r=await LABO.api('client_save',p);
  if(r.ok){ LABO.closeModal('modal-client'); LABO.toast('Enregistré ✓'); load(); }
  else LABO.toast(r.error||'Erreur','error');
}
async function toggleActif(id){ const r=await LABO.api('client_toggle_actif',{id}); if(r.ok) load(); else LABO.toast(r.error||'Erreur','error'); }

function ouvrirAcces(id){
  const c=all.find(x=>x.id===id);
  document.getElementById('acc-client-id').value=id;
  document.getElementById('acc-email').value=c && c.email ? c.email : '';
  document.getElementById('acc-password').value='';
  LABO.openModal('modal-acces');
}
async function creerAcces(){
  const r=await LABO.api('client_creer_acces',{
    client_id:document.getElementById('acc-client-id').value,
    email:document.getElementById('acc-email').value.trim(),
    password:document.getElementById('acc-password').value
  });
  if(r.ok){ LABO.closeModal('modal-acces'); LABO.toast('Accès créé ✓'); load(); }
  else LABO.toast(r.error||'Erreur','error');
}
document.getElementById('search').addEventListener('input', render);
load();
</script>
<?php require_once '../includes/footer.php'; ?>
