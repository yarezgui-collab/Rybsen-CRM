<?php
require_once 'config.php';
requireLogin();
$page_title = 'Mes soumissions';

$db  = getDB();
$uid = (int)$_SESSION['fm_user_id'];

$stmt = $db->prepare('SELECT * FROM fm_submissions WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$uid]);
$subs = $stmt->fetchAll();

$counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
foreach ($subs as $s) {
    if (isset($counts[$s['status']])) $counts[$s['status']]++;
}

$status_labels = [
    'pending'  => ['label' => 'En attente',  'badge' => 'badge-pending'],
    'approved' => ['label' => 'Approuvée',   'badge' => 'badge-approved'],
    'rejected' => ['label' => 'Refusée',     'badge' => 'badge-rejected'],
];
$type_labels = [
    'fund' => 'Fonds VC', 'accelerator' => 'Accélérateur', 'grant' => 'Subvention',
    'competition' => 'Compétition', 'incubator' => 'Incubateur',
];

include 'header.php';
?>

<div class="section-head" style="margin-bottom:20px">
  <div>
    <h1>Mes soumissions</h1>
    <p style="color:var(--muted);font-size:14px;margin-top:4px">Suivez le statut des programmes que vous avez soumis.</p>
  </div>
  <a href="submit.php" class="btn btn-primary">&#43; Soumettre un programme</a>
</div>

<div class="kpi-row">
  <div class="kpi">
    <div class="kpi-value" style="color:var(--accent)"><?= count($subs) ?></div>
    <div class="kpi-label">Total</div>
  </div>
  <div class="kpi">
    <div class="kpi-value" style="color:var(--accent4)"><?= $counts['pending'] ?></div>
    <div class="kpi-label">En attente</div>
  </div>
  <div class="kpi">
    <div class="kpi-value" style="color:var(--accent3)"><?= $counts['approved'] ?></div>
    <div class="kpi-label">Approuvées</div>
  </div>
  <div class="kpi">
    <div class="kpi-value" style="color:var(--accent5)"><?= $counts['rejected'] ?></div>
    <div class="kpi-label">Refusées</div>
  </div>
</div>

<?php if (empty($subs)): ?>
  <div class="card" style="text-align:center;padding:48px 20px">
    <div style="font-size:40px;margin-bottom:12px">&#128228;</div>
    <h3 style="margin-bottom:8px">Aucune soumission pour le moment</h3>
    <p style="font-size:14px;color:var(--muted);margin-bottom:20px">Vous avez repéré un programme de financement non référencé ?<br>Partagez-le avec la communauté.</p>
    <a href="submit.php" class="btn btn-primary">Soumettre mon premier programme &rarr;</a>
  </div>
<?php else: ?>

<div style="display:flex;flex-direction:column;gap:12px">
  <?php foreach ($subs as $s):
    $st = $status_labels[$s['status']] ?? $status_labels['pending'];
  ?>
  <div class="card" style="display:flex;flex-direction:column;gap:10px">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap">
      <div style="flex:1;min-width:200px">
        <div style="font-size:15px;font-weight:700;color:#fff"><?= h($s['name']) ?></div>
        <div style="font-size:12.5px;color:var(--muted);margin-top:2px">
          <?= h($s['organisation']) ?> &middot; <?= h($type_labels[$s['type']] ?? ucfirst($s['type'])) ?>
        </div>
      </div>
      <span class="badge <?= $st['badge'] ?>"><?= $st['label'] ?></span>
    </div>

    <?php if ($s['description']): ?>
    <p style="font-size:13px;color:var(--text-sec);line-height:1.55"><?= h(mb_substr($s['description'], 0, 220)) ?><?= mb_strlen($s['description']) > 220 ? '…' : '' ?></p>
    <?php endif; ?>

    <div style="display:flex;gap:16px;flex-wrap:wrap;font-size:12px;color:var(--muted);align-items:center">
      <span>&#128197; Soumis le <?= date('d/m/Y', strtotime($s['created_at'])) ?></span>
      <?php if ($s['status'] !== 'pending' && !empty($s['reviewed_at'])): ?>
        <span>&#10003; Traité le <?= date('d/m/Y', strtotime($s['reviewed_at'])) ?></span>
      <?php endif; ?>
      <?php if ($s['amount']): ?><span style="font-family:var(--mono);color:var(--accent)"><?= h($s['amount']) ?></span><?php endif; ?>
      <?php if ($s['deadline']): ?><span>&#9200; <?= h($s['deadline']) ?></span><?php endif; ?>
      <a href="<?= h($s['url']) ?>" target="_blank" rel="noopener noreferrer" style="color:var(--accent);text-decoration:none;margin-left:auto">Voir le lien &rarr;</a>
    </div>

    <?php if ($s['status'] === 'approved'): ?>
      <div class="alert alert-success" style="margin:0;padding:10px 14px;font-size:13px">&#127881; Ce programme a été publié sur la plateforme. Merci pour votre contribution !</div>
    <?php elseif ($s['status'] === 'rejected'): ?>
      <div class="alert alert-error" style="margin:0;padding:10px 14px;font-size:13px">Cette soumission n'a pas été retenue. Vous pouvez en proposer d'autres à tout moment.</div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include 'footer.php'; ?>
