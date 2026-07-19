<?php
// Page-relais pour un module pas encore développé.
// Le fichier appelant doit définir $pageTitle, $activePage, et optionnellement $icon / $description
// avant d'inclure ce fichier.
require_once __DIR__ . '/header.php';
?>
<div class="section-card">
  <div class="section-header">
    <div class="section-title"><?= $icon ?? '🛠️' ?> <?= htmlspecialchars($pageTitle) ?></div>
  </div>
  <div class="empty-placeholder">
    <p class="empty-placeholder-main">Module en construction — prochaine étape de développement.</p>
    <?php if (!empty($description)): ?>
    <p class="empty-placeholder-desc"><?= htmlspecialchars($description) ?></p>
    <?php endif; ?>
  </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
