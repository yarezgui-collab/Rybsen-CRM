<?php
require_once 'config.php';
requireLogin();
$page_title = 'Mes soumissions';

$db = getDB();
$stmt = $db->prepare('SELECT * FROM fm_submissions WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$_SESSION['fm_user_id']]);
$submissions = $stmt->fetchAll();

include 'header.php';
?>

<div style="margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
  <div>
    <h1 style="font-size:22px;font-weight:700;color:#fff;margin-bottom:4px">Mes soumissions</h1>
    <p style="color:var(--muted);font-size:13px">Programmes que vous avez soumis &agrave; notre &eacute;quipe pour validation.</p>
  </div>
  <a href="submit.php" class="btn btn-primary">&#43; Nouveau programme</a>
</div>

<?php if (empty($submissions)): ?>
  <div style="text-align:center;padding:60px;color:var(--muted)">
    <div style="font-size:40px;margin-bottom:12px">&#128203;</div>
    <h3 style="color:var(--label);margin-bottom:8px">Aucune soumission</h3>
    <p style="font-size:13px;margin-bottom:20px">Vous n&rsquo;avez pas encore soumis de programme.</p>
    <a href="submit.php" class="btn btn-primary">Soumettre un programme</a>
  </div>
<?php else: ?>
  <div style="background:var(--card);border:1px solid var(--border);border-radius:12px;overflow:hidden">
    <table>
      <thead>
        <tr>
          <th>Programme</th>
          <th>Organisation</th>
          <th>Type</th>
          <th>Deadline</th>
          <th>Statut</th>
          <th>Soumis le</th>
          <th>URL</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($submissions as $s): ?>
        <tr>
          <td style="font-weight:600;color:#fff"><?= h($s['name'] ?: '—') ?></td>
          <td style="color:var(--muted);font-size:12px"><?= h($s['organisation'] ?: '—') ?></td>
          <td>
            <span class="badge badge-<?= h($s['type']) ?>"><?= h($s['type']) ?></span>
          </td>
          <td style="font-size:12px;color:var(--label)"><?= h($s['deadline'] ?: '—') ?></td>
          <td>
            <?php
              $sc = $s['status'];
              $sc_class = ['pending'=>'badge-pending','approved'=>'badge-approved','rejected'=>'badge-rejected'][$sc] ?? 'badge-pending';
              $sc_label = ['pending'=>'En attente','approved'=>'Approuv&eacute;','rejected'=>'Refus&eacute;'][$sc] ?? $sc;
            ?>
            <span class="badge <?= $sc_class ?>"><?= $sc_label ?></span>
          </td>
          <td style="font-size:11px;color:var(--muted);font-family:var(--mono)"><?= date('d/m/Y', strtotime($s['created_at'])) ?></td>
          <td>
            <a href="<?= h($s['url']) ?>" target="_blank" rel="noopener" style="color:var(--accent);font-size:11px;text-decoration:none" title="<?= h($s['url']) ?>">
              Ouvrir &#8599;
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div style="margin-top:12px;font-size:12px;color:var(--muted)">
    <?= count($submissions) ?> soumission(s) au total &mdash;
    <?= count(array_filter($submissions, fn($s) => $s['status'] === 'pending')) ?> en attente &mdash;
    <?= count(array_filter($submissions, fn($s) => $s['status'] === 'approved')) ?> approuv&eacute;e(s)
  </div>
<?php endif; ?>

<?php include 'footer.php'; ?>
