<?php
require_once '../config.php';
requireRole(['admin','technicien','magasinier']);
$user = currentUser();
$peutEditer = in_array($user['role'], ['admin','magasinier'], true);
$estAdmin = $user['role'] === 'admin';
$pageTitle = 'Pièces détachées';
$activePage = 'pieces';
require_once '../includes/header.php';
?>
<div class="section-card">
  <div class="section-header">
    <div class="section-title">⚙️ Catalogue & stock de pièces</div>
    <?php if ($peutEditer): ?><div class="section-actions"><button class="btn btn-primary" onclick="openAdd()">+ Nouvelle pièce</button></div><?php endif; ?>
  </div>
  <div class="filters-bar">
    <input type="text" id="search" placeholder="🔍 Référence, désignation, catégorie, compatibilité…">
    <select id="f-stock" onchange="render()">
      <option value="">Tout le stock</option>
      <option value="bas">Sous seuil</option>
      <option value="zero">En rupture</option>
    </select>
    <select id="f-actif" onchange="render()">
      <option value="1" selected>Actives</option>
      <option value="0">Inactives</option>
      <option value="">Toutes</option>
    </select>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Référence</th><th>Désignation</th><th>Catégorie</th><th>Empl.</th><th>Stock</th><th>Seuil</th><th>PU vente</th><th>Actions</th></tr></thead>
      <tbody id="body"><tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-muted)">Chargement…</td></tr></tbody>
    </table>
  </div>
  <div style="padding:10px 14px;color:var(--text-muted);font-size:13px" id="count"></div>
</div>

<!-- Modal édition -->
<div class="modal-overlay" id="modal-piece">
  <div class="modal" style="max-width:720px">
    <div class="modal-header"><div class="modal-title" id="modal-title">Pièce</div><button class="modal-close" onclick="CTP.closeModal('modal-piece')">✕</button></div>
    <div class="modal-body">
      <input type="hidden" id="p-id">
      <div class="form-grid">
        <div class="form-group"><label>Référence *</label><input type="text" id="p-ref"></div>
        <div class="form-group"><label>Désignation *</label><input type="text" id="p-desig"></div>
        <div class="form-group"><label>Catégorie</label><input type="text" id="p-cat" placeholder="Optique, laser, mécanique…"></div>
        <div class="form-group"><label>Emplacement</label><input type="text" id="p-empl" placeholder="Rayon / bac"></div>
        <div class="form-group full"><label>Compatibilité (modèles)</label><input type="text" id="p-compat" placeholder="Trendsetter, Magnus…"></div>
        <div class="form-group"><label>Fournisseur</label><input type="text" id="p-fourn"></div>
        <div class="form-group"><label>Statut</label><select id="p-actif"><option value="1">Active</option><option value="0">Inactive</option></select></div>
        <div class="form-group"><label>Prix achat (TND)</label><input type="number" step="0.001" id="p-achat" value="0"></div>
        <div class="form-group"><label>Prix vente (TND)</label><input type="number" step="0.001" id="p-vente" value="0"></div>
        <div class="form-group"><label>Seuil d'alerte</label><input type="number" id="p-seuil" value="0" min="0"></div>
        <div class="form-group" id="wrap-stock-init"><label>Stock initial</label><input type="number" id="p-stock" value="0" min="0"></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="CTP.closeModal('modal-piece')">Annuler</button>
      <button class="btn btn-primary" onclick="save()">Enregistrer</button>
    </div>
  </div>
</div>

<!-- Modal mouvement de stock -->
<div class="modal-overlay" id="modal-stock">
  <div class="modal">
    <div class="modal-header"><div class="modal-title" id="stock-title">Mouvement de stock</div><button class="modal-close" onclick="CTP.closeModal('modal-stock')">✕</button></div>
    <div class="modal-body">
      <input type="hidden" id="s-id">
      <div id="s-info" style="margin-bottom:12px"></div>
      <div class="form-grid">
        <div class="form-group"><label>Type</label><select id="s-type">
          <option value="entree">Entrée (+)</option><option value="sortie">Sortie (−)</option><option value="ajustement">Ajustement</option></select></div>
        <div class="form-group"><label>Quantité</label><input type="number" id="s-qte" value="1" min="1"></div>
        <div class="form-group full"><label>Motif</label><input type="text" id="s-motif" placeholder="Réception, casse, correction inventaire…"></div>
      </div>
      <div class="section-title" style="margin:16px 0 8px">Derniers mouvements</div>
      <div class="table-wrap"><table>
        <thead><tr><th>Date</th><th>Type</th><th>Qté</th><th>Stock</th><th>Motif</th><th>Par</th></tr></thead>
        <tbody id="mvt-body"></tbody>
      </table></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="CTP.closeModal('modal-stock')">Fermer</button>
      <button class="btn btn-teal" onclick="ajuster()">Valider le mouvement</button>
    </div>
  </div>
</div>

<script>
const peutEditer = <?= $peutEditer ? 'true':'false' ?>;
const estAdmin = <?= $estAdmin ? 'true':'false' ?>;
let all = [];
async function load() { all = await CTP.api('piece_list'); render(); }
function render() {
  const e = CTP.escape;
  const q = document.getElementById('search').value.toLowerCase();
  const fst = document.getElementById('f-stock').value, fa = document.getElementById('f-actif').value;
  const rows = all.filter(p => {
    if (fa !== '' && String(p.actif) !== fa) return false;
    if (fst === 'bas' && !(p.stock <= p.seuil_alerte)) return false;
    if (fst === 'zero' && p.stock > 0) return false;
    if (q && !(`${p.reference} ${p.designation} ${p.categorie||''} ${p.compatibilite||''}`).toLowerCase().includes(q)) return false;
    return true;
  });
  const body = document.getElementById('body');
  if (!rows.length) { body.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-muted)">Aucune pièce</td></tr>'; }
  else body.innerHTML = rows.map(p => {
    const bas = p.stock <= p.seuil_alerte;
    const stockCls = p.stock <= 0 ? 'badge-red' : (bas ? 'badge-gold' : 'badge-green');
    return `<tr>
      <td><strong>${e(p.reference)}</strong></td>
      <td>${e(p.designation)}${p.compatibilite ? '<br><span style="color:var(--text-muted)">'+e(p.compatibilite)+'</span>':''}</td>
      <td>${e(p.categorie) || '—'}</td>
      <td>${e(p.emplacement) || '—'}</td>
      <td><span class="badge ${stockCls}">${p.stock}</span></td>
      <td class="num">${p.seuil_alerte}</td>
      <td class="num">${CTP.formatCurrency(p.prix_vente)}</td>
      <td>
        ${peutEditer ? `<button class="btn btn-teal btn-sm" onclick="openStock(${p.id})">Stock</button>`:''}
        ${peutEditer ? `<button class="btn btn-outline btn-sm" onclick="edit(${p.id})">Modifier</button>`:''}
        ${estAdmin ? `<button class="btn btn-danger btn-sm" onclick="del(${p.id})">Suppr.</button>`:''}
      </td></tr>`;
  }).join('');
  document.getElementById('count').textContent = `${rows.length} pièce(s)`;
}
function fill(p) {
  p = p || {};
  document.getElementById('p-id').value = p.id || '';
  document.getElementById('p-ref').value = p.reference || '';
  document.getElementById('p-desig').value = p.designation || '';
  document.getElementById('p-cat').value = p.categorie || '';
  document.getElementById('p-empl').value = p.emplacement || '';
  document.getElementById('p-compat').value = p.compatibilite || '';
  document.getElementById('p-fourn').value = p.fournisseur || '';
  document.getElementById('p-actif').value = p.actif !== undefined ? p.actif : 1;
  document.getElementById('p-achat').value = p.prix_achat || 0;
  document.getElementById('p-vente').value = p.prix_vente || 0;
  document.getElementById('p-seuil').value = p.seuil_alerte || 0;
  document.getElementById('p-stock').value = p.stock || 0;
  // le stock ne se modifie qu'à la création (ensuite via mouvements tracés)
  document.getElementById('wrap-stock-init').style.display = p.id ? 'none' : '';
}
function openAdd() { document.getElementById('modal-title').textContent = 'Nouvelle pièce'; fill(null); CTP.openModal('modal-piece'); }
async function edit(id) {
  const p = await CTP.api('piece_get', { id });
  if (p.error) return CTP.toast(p.error, 'error');
  document.getElementById('modal-title').textContent = 'Modifier la pièce'; fill(p); CTP.openModal('modal-piece');
}
async function save() {
  const d = {
    id: document.getElementById('p-id').value,
    reference: document.getElementById('p-ref').value.trim(),
    designation: document.getElementById('p-desig').value.trim(),
    categorie: document.getElementById('p-cat').value.trim(),
    emplacement: document.getElementById('p-empl').value.trim(),
    compatibilite: document.getElementById('p-compat').value.trim(),
    fournisseur: document.getElementById('p-fourn').value.trim(),
    actif: document.getElementById('p-actif').value,
    prix_achat: document.getElementById('p-achat').value,
    prix_vente: document.getElementById('p-vente').value,
    seuil_alerte: document.getElementById('p-seuil').value,
    stock: document.getElementById('p-stock').value,
  };
  if (!d.reference) return CTP.toast('Référence requise', 'error');
  if (!d.designation) return CTP.toast('Désignation requise', 'error');
  const r = await CTP.api('piece_save', d);
  if (r.error) return CTP.toast(r.error, 'error');
  CTP.toast('Pièce enregistrée'); CTP.closeModal('modal-piece'); load();
}
async function openStock(id) {
  const p = all.find(x => x.id == id); if (!p) return;
  document.getElementById('s-id').value = id;
  document.getElementById('stock-title').textContent = 'Stock — ' + p.reference;
  document.getElementById('s-info').innerHTML = `<span class="badge badge-navy">${CTP.escape(p.designation)}</span> <span class="badge badge-grey">Stock actuel : ${p.stock}</span>`;
  document.getElementById('s-qte').value = 1; document.getElementById('s-motif').value = '';
  const mvts = await CTP.api('piece_mouvements', { id });
  const e = CTP.escape;
  document.getElementById('mvt-body').innerHTML = Array.isArray(mvts) && mvts.length
    ? mvts.map(m => `<tr><td>${CTP.formatDate(m.created_at)}</td><td>${e(m.type)}</td>
        <td class="num">${m.quantite>0?'+':''}${m.quantite}</td><td class="num">${m.stock_apres}</td>
        <td>${e(m.motif)||'—'}</td><td>${e(m.user_nom)||'—'}</td></tr>`).join('')
    : '<tr><td colspan="6" style="text-align:center;padding:16px;color:var(--text-muted)">Aucun mouvement</td></tr>';
  CTP.openModal('modal-stock');
}
async function ajuster() {
  const id = document.getElementById('s-id').value;
  const type = document.getElementById('s-type').value;
  let qte = Math.abs(parseInt(document.getElementById('s-qte').value, 10) || 0);
  if (qte < 1) return CTP.toast('Quantité invalide', 'error');
  const delta = type === 'sortie' ? -qte : qte;
  const r = await CTP.api('piece_ajuster_stock', { id, delta, type, motif: document.getElementById('s-motif').value.trim() });
  if (r.error) return CTP.toast(r.error, 'error');
  CTP.toast('Stock mis à jour'); await load(); openStock(id);
}
async function del(id) {
  if (!CTP.confirmDelete('Supprimer cette pièce ?')) return;
  const r = await CTP.api('piece_delete', { id });
  if (r.error) return CTP.toast(r.error, 'error');
  CTP.toast('Pièce supprimée'); load();
}
load();
</script>
<?php require_once '../includes/footer.php'; ?>
