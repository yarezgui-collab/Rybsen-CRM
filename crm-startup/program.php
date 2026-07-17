<?php
require_once 'config.php';
requireLogin();

$db  = getDB();
$pid = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT p.*, u.startup_name AS submitted_by_name
    FROM fm_programs p
    LEFT JOIN fm_users u ON p.submitted_by = u.id
    WHERE p.id = ? LIMIT 1");
$stmt->execute([$pid]);
$p = $stmt->fetch();

if (!$p || ($p['status'] !== 'active' && !isAdmin())) {
    header('Location: dashboard.php');
    exit;
}

$page_title = $p['name'];

$type_labels = [
    'fund' => 'Fonds VC', 'accelerator' => 'Accélérateur', 'grant' => 'Subvention',
    'competition' => 'Compétition', 'incubator' => 'Incubateur',
];
$ptype = $p['type'] ?: 'grant';
$dt = $p['deadline_date'] ? calcDeadlineType($p['deadline_date']) : ($p['deadline_type'] ?: 'open');
$days_left = null;
if ($p['deadline_date'] && strtotime($p['deadline_date']) >= strtotime(date('Y-m-d'))) {
    $days_left = (int)ceil((strtotime($p['deadline_date']) - time()) / 86400);
}
$p_sectors = array_filter(array_map('trim', explode(',', $p['sectors'] ?? '')));

include 'header.php';
?>

<div style="max-width:860px;margin:0 auto">
  <a href="dashboard.php" style="display:inline-flex;align-items:center;gap:6px;color:var(--muted);text-decoration:none;font-size:13px;margin-bottom:16px">&larr; Retour aux programmes</a>

  <?php if ($p['status'] !== 'active'): ?>
    <div class="alert alert-warn">&#9888; Ce programme est archivé (visible car vous êtes admin).</div>
  <?php endif; ?>

  <!-- EN-TÊTE -->
  <div class="card" style="margin-bottom:16px">
    <div style="display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap">
      <div style="font-size:44px;line-height:1"><?= $p['emoji'] ? h($p['emoji']) : '&#128176;' ?></div>
      <div style="flex:1;min-width:220px">
        <h1 style="margin-bottom:4px"><?= h($p['name']) ?></h1>
        <div style="font-size:14px;color:var(--muted);margin-bottom:10px"><?= h($p['organisation']) ?></div>
        <div style="display:flex;gap:6px;flex-wrap:wrap">
          <span class="badge badge-<?= h($ptype) ?>"><?= h($type_labels[$ptype] ?? ucfirst($ptype)) ?></span>
          <?php if (!empty($p['tunisia_focus'])): ?><span class="badge badge-tn">&#127481;&#127475; Focus Tunisie</span><?php endif; ?>
          <?php foreach ($p_sectors as $s): ?><span class="badge badge-tn"><?= h($s) ?></span><?php endforeach; ?>
        </div>
      </div>
      <?php if ($p['link']): ?>
      <a href="<?= h($p['link']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary">Candidater &rarr;</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- DEADLINE -->
  <?php if ($dt === 'urgent' && $days_left !== null): ?>
  <div class="urgency-strip">
    <span class="urgency-dot"></span>
    <span class="urgency-text">Deadline dans <strong><?= $days_left <= 0 ? "moins de 24h" : $days_left . ' jour(s)' ?></strong> — candidatez rapidement.</span>
  </div>
  <?php endif; ?>

  <!-- INFOS CLÉS -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:16px">
    <div class="card" style="padding:16px">
      <div style="font-size:10px;color:var(--subtle);text-transform:uppercase;letter-spacing:1px;font-family:var(--mono);margin-bottom:4px">Deadline</div>
      <div class="dl-<?= h($dt) ?>" style="font-size:15px">
        <?php if ($days_left !== null): ?>
          <?= date('d/m/Y', strtotime($p['deadline_date'])) ?> (J-<?= $days_left ?>)
        <?php else: ?>
          <?= h($p['deadline'] ?: 'Rolling / continu') ?>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($p['amount']): ?>
    <div class="card" style="padding:16px">
      <div style="font-size:10px;color:var(--subtle);text-transform:uppercase;letter-spacing:1px;font-family:var(--mono);margin-bottom:4px">Montant / Ticket</div>
      <div style="font-family:var(--mono);font-size:15px;color:var(--accent);font-weight:600"><?= h($p['amount']) ?></div>
    </div>
    <?php endif; ?>
    <?php if ($p['geo']): ?>
    <div class="card" style="padding:16px">
      <div style="font-size:10px;color:var(--subtle);text-transform:uppercase;letter-spacing:1px;font-family:var(--mono);margin-bottom:4px">Zone géographique</div>
      <div style="font-size:15px;color:var(--text-sec)"><?= h($p['geo']) ?></div>
    </div>
    <?php endif; ?>
    <?php if ($p['stage_target']): ?>
    <div class="card" style="padding:16px">
      <div style="font-size:10px;color:var(--subtle);text-transform:uppercase;letter-spacing:1px;font-family:var(--mono);margin-bottom:4px">Stade ciblé</div>
      <div style="font-size:15px;color:var(--text-sec)"><?= h($p['stage_target']) ?></div>
    </div>
    <?php endif; ?>
  </div>

  <!-- DESCRIPTION -->
  <?php if ($p['description']): ?>
  <div class="card" style="margin-bottom:16px">
    <div class="card-section-label">À propos du programme</div>
    <p style="font-size:14.5px;line-height:1.75;white-space:pre-line"><?= h($p['description']) ?></p>
  </div>
  <?php endif; ?>

  <!-- ÉLIGIBILITÉ -->
  <?php if ($p['tn_eligible']): ?>
  <div class="card" style="margin-bottom:16px">
    <div class="card-section-label">Éligibilité startups tunisiennes</div>
    <p style="font-size:14px;line-height:1.7"><?= h($p['tn_eligible']) ?></p>
  </div>
  <?php endif; ?>

  <!-- MÉTA + CTA -->
  <div class="card" style="display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap">
    <div style="font-size:12px;color:var(--muted)">
      <?php if ($p['submitted_by_name']): ?>
        Proposé par la communauté (<?= h($p['submitted_by_name']) ?>) &middot;
      <?php endif; ?>
      Référencé le <?= date('d/m/Y', strtotime($p['created_at'])) ?>
    </div>
    <?php if ($p['link']): ?>
    <a href="<?= h($p['link']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary">Accéder au site officiel &rarr;</a>
    <?php endif; ?>
  </div>
</div>

<?php include 'footer.php'; ?>
