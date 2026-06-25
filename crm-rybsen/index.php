<?php
require_once 'config.php';
$pageTitle = 'Tableau de bord';
$activePage = 'dashboard';
require_once 'includes/header.php';
?>

<!-- ALERTES BREVET -->
<?php
$db = getDB();
$brevets = $db->query("SELECT * FROM taches WHERE alerte_brevet=1 AND statut!='Terminé' ORDER BY deadline ASC")->fetchAll();
foreach ($brevets as $b):
  $jours = ceil((strtotime($b['deadline']) - time()) / 86400);
  $cls = $jours <= 30 ? 'urgent' : 'gold';
?>
<div class="alert-box <?= $cls ?>">
  <span>⚠️</span>
  <div><strong><?= htmlspecialchars($b['titre']) ?></strong> — <?= abs($jours) ?> jour<?= abs($jours)>1?'s':'' ?> <?= $jours >= 0 ? 'restant(s)' : 'de retard' ?> · Deadline : <strong><?= date('d/m/Y', strtotime($b['deadline'])) ?></strong></div>
</div>
<?php endforeach; ?>

<!-- KPI GRID -->
<div class="kpi-grid" id="kpi-grid">
  <div class="kpi-card teal">
    <div class="kpi-label">Investisseurs chauds</div>
    <div class="kpi-value" id="kpi-inv-chauds">—</div>
    <div class="kpi-sub" id="kpi-inv-total">— contacts total</div>
  </div>
  <div class="kpi-card gold">
    <div class="kpi-label">Relances aujourd'hui</div>
    <div class="kpi-value" id="kpi-relances">—</div>
    <div class="kpi-sub">investisseurs à contacter</div>
  </div>
  <div class="kpi-card navy">
    <div class="kpi-label">Machines installées</div>
    <div class="kpi-value" id="kpi-machines">—</div>
    <div class="kpi-sub">unités AquaClean actives</div>
  </div>
  <div class="kpi-card red">
    <div class="kpi-label">Tâches urgentes</div>
    <div class="kpi-value" id="kpi-urgentes">—</div>
    <div class="kpi-sub" id="kpi-cand">— candidatures actives</div>
  </div>
</div>

<div class="dash-grid">

<!-- RELANCES À FAIRE -->
<div class="section-card">
  <div class="section-header">
    <div class="section-title">🔥 À relancer aujourd'hui</div>
    <a href="/modules/investisseurs.php" class="btn btn-outline btn-sm">Voir tout</a>
  </div>
  <div class="table-wrap">
    <table id="tbl-relances">
      <thead><tr><th>Investisseur</th><th>Organisation</th><th>Chaleur</th><th>Action</th></tr></thead>
      <tbody id="relances-body">
        <tr><td colspan="4" class="empty-state"><div class="empty-icon">✅</div><p>Aucune relance en retard</p></td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- TÂCHES URGENTES -->
<div class="section-card">
  <div class="section-header">
    <div class="section-title">🔴 Tâches prioritaires</div>
    <a href="/modules/taches.php" class="btn btn-outline btn-sm">Gérer</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Tâche</th><th>Module</th><th>Deadline</th><th>✓</th></tr></thead>
      <tbody id="tasks-body">
        <tr><td colspan="4" style="text-align:center;padding:20px;color:#999">Chargement...</td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- PIPELINE COMMERCIAL -->
<div class="section-card">
  <div class="section-header">
    <div class="section-title">🏭 Pipeline commercial</div>
    <a href="/modules/clients.php" class="btn btn-outline btn-sm">Voir tout</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Entreprise</th><th>Pays</th><th>Stade</th><th>Proba.</th></tr></thead>
      <tbody id="pipeline-body">
        <tr><td colspan="4" style="text-align:center;padding:20px;color:#999">Chargement...</td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- CANDIDATURES ACTIVES -->
<div class="section-card">
  <div class="section-header">
    <div class="section-title">📋 Candidatures en cours</div>
    <a href="/modules/candidatures.php" class="btn btn-outline btn-sm">Voir tout</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Programme</th><th>Montant</th><th>Statut</th><th>Priorité</th></tr></thead>
      <tbody id="cand-body">
        <tr><td colspan="4" style="text-align:center;padding:20px;color:#999">Chargement...</td></tr>
      </tbody>
    </table>
  </div>
</div>

</div><!-- /dash-grid -->

<div class="flow-slogan">BE THE FLOW — RYBSEN © 2026</div>

<script>
const stadeColors = {
  'Prospect': 'badge-grey', 'Devis envoyé': 'badge-navy', 'Négociation': 'badge-gold',
  'Bon de commande': 'badge-teal', 'Installé': 'badge-green', 'Perdu': 'badge-red', 'En pause': 'badge-grey'
};
const candColors = {
  'Accepté': 'badge-green', 'Soumis': 'badge-navy', 'En attente décision': 'badge-gold',
  'Refusé': 'badge-red', 'Reporté': 'badge-grey', 'En cours remboursement': 'badge-teal', 'À préparer': 'badge-grey'
};

async function loadDashboard() {
  // Stats
  const stats = await RYBSEN.api('dashboard_stats');
  document.getElementById('kpi-inv-chauds').textContent = stats.investisseurs_chauds;
  document.getElementById('kpi-inv-total').textContent = stats.investisseurs_total + ' contacts total';
  document.getElementById('kpi-relances').textContent = stats.relances_today;
  document.getElementById('kpi-machines').textContent = stats.machines_installees;
  document.getElementById('kpi-urgentes').textContent = stats.taches_urgentes;
  document.getElementById('kpi-cand').textContent = stats.candidatures_en_cours + ' candidatures actives';

  const e = RYBSEN.escape.bind(RYBSEN);

  // Relances
  const inv = await RYBSEN.api('inv_list');
  const today = new Date().toISOString().split('T')[0];
  const relances = inv.filter(i => i.date_prochain_contact && i.date_prochain_contact <= today && !['Investi','Refusé'].includes(i.statut));
  const rb = document.getElementById('relances-body');
  if (relances.length === 0) {
    rb.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:20px;color:#999">✅ Aucune relance en retard</td></tr>';
  } else {
    rb.innerHTML = relances.slice(0, 6).map(i => {
      const chaleurClass = i.score_chaleur === '🔥 Chaud' ? 'badge-red' : i.score_chaleur === '🟡 Tiède' ? 'badge-gold' : 'badge-grey';
      return `<tr>
        <td><strong>${e(i.nom)}</strong><br><small style="color:#999">${e(i.email) || ''}</small></td>
        <td>${e(i.organisation) || '—'}</td>
        <td><span class="badge ${chaleurClass}">${e(i.score_chaleur)}</span></td>
        <td><a href="/modules/investisseurs.php" class="btn btn-teal btn-sm">Ouvrir</a></td>
      </tr>`;
    }).join('');
  }

  // Tasks
  const tasks = await RYBSEN.api('task_list');
  const urgent = tasks.filter(t => t.statut !== 'Terminé').slice(0, 6);
  document.getElementById('tasks-body').innerHTML = urgent.length === 0
    ? '<tr><td colspan="4" style="text-align:center;padding:20px;color:#999">✅ Aucune tâche urgente</td></tr>'
    : urgent.map(t => {
        const d = t.deadline ? Math.ceil((new Date(t.deadline) - new Date()) / 86400000) : null;
        const dStr = d !== null ? (d < 0 ? `<span style="color:red">${Math.abs(d)}j retard</span>` : `${d}j`) : '—';
        return `<tr>
          <td>${e(t.priorite)} ${e(t.titre)}</td>
          <td><span class="badge badge-grey">${e(t.module_lie)}</span></td>
          <td>${dStr}</td>
          <td><button onclick="markDone(${t.id})" class="btn btn-outline btn-sm">✓</button></td>
        </tr>`;
      }).join('');

  // Pipeline
  const clients = await RYBSEN.api('cli_list');
  const pipeline = clients.filter(c => !['Installé','Perdu'].includes(c.stade)).slice(0, 5);
  document.getElementById('pipeline-body').innerHTML = pipeline.length === 0
    ? '<tr><td colspan="4" style="text-align:center;padding:20px;color:#999">Aucun prospect actif</td></tr>'
    : pipeline.map(c => `<tr>
        <td><strong>${e(c.nom_entreprise)}</strong></td>
        <td>${e(c.pays) || '—'}</td>
        <td><span class="badge ${stadeColors[c.stade] || 'badge-grey'}">${e(c.stade)}</span></td>
        <td>${c.probabilite_closing}%</td>
      </tr>`).join('');

  // Candidatures
  const cands = await RYBSEN.api('cand_list');
  const active = cands.filter(c => ['Soumis','En attente décision','À préparer'].includes(c.statut)).slice(0, 5);
  document.getElementById('cand-body').innerHTML = active.length === 0
    ? '<tr><td colspan="4" style="text-align:center;padding:20px;color:#999">Aucune candidature active</td></tr>'
    : active.map(c => `<tr>
        <td><strong>${e(c.programme)}</strong><br><small style="color:#999">${e(c.organisme) || ''}</small></td>
        <td>${c.montant_demande ? new Intl.NumberFormat('fr-FR').format(c.montant_demande) + ' ' + e(c.devise) : '—'}</td>
        <td><span class="badge ${candColors[c.statut] || 'badge-grey'}">${e(c.statut)}</span></td>
        <td>${e(c.priorite)}</td>
      </tr>`).join('');
}

async function markDone(id) {
  const r = await RYBSEN.api('task_done', { id });
  if (r.ok) { RYBSEN.toast('Tâche marquée terminée ✓'); loadDashboard(); }
}

loadDashboard();
</script>

<?php require_once 'includes/footer.php'; ?>
