<?php
require_once 'config.php';
requireLogin();
$page_title = 'Soumettre un programme';

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $url  = trim($_POST['url'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $org  = trim($_POST['organisation'] ?? '');
    $type = $_POST['type'] ?? 'grant';
    $amt  = trim($_POST['amount'] ?? '');
    $dl   = trim($_POST['deadline'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $sec  = trim($_POST['sectors'] ?? '');
    $geo  = trim($_POST['geo'] ?? '');
    $notes= trim($_POST['notes'] ?? '');

    if (!$url || !$name || !$org) {
        $error = 'Veuillez renseigner au minimum l\'URL, le nom du programme et l\'organisation.';
    } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
        $error = 'L\'URL fournie n\'est pas valide. Exemple: https://exemple.com/programme';
    } else {
        $db = getDB();
        $stmt = $db->prepare('INSERT INTO fm_submissions (user_id, url, name, organisation, type, amount, deadline, description, sectors, geo, notes, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending")');
        $stmt->execute([
            $_SESSION['fm_user_id'], $url, $name, $org, $type,
            $amt ?: null, $dl ?: null, $desc ?: null,
            $sec ?: null, $geo ?: null, $notes ?: null
        ]);
        auditLog('submit_program', 'submission', (int)$db->lastInsertId(), $name);
        $success = true;
    }
}

include 'header.php';
?>

<div style="max-width:720px">
  <div style="margin-bottom:24px">
    <h1 style="font-size:22px;font-weight:700;color:#fff;margin-bottom:6px">Soumettre un programme</h1>
    <p style="color:var(--muted);font-size:14px">Vous avez d&eacute;couvert un fonds ou programme non r&eacute;f&eacute;renc&eacute; ? Soumettez-le ici. Notre &eacute;quipe le validera et l&rsquo;ajoutera &agrave; la plateforme.</p>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success" style="font-size:14px;padding:16px">
      &#10003; <strong>Merci !</strong> Votre soumission a &eacute;t&eacute; envoy&eacute;e avec succ&egrave;s.<br>
      Notre &eacute;quipe va l'analyser et l'int&eacute;grer &agrave; la plateforme sous peu.<br><br>
      <a href="my_submissions.php" style="color:var(--accent)">Voir mes soumissions &rarr;</a>
      &nbsp;&nbsp;|&nbsp;&nbsp;
      <a href="submit.php" style="color:var(--accent)">Soumettre un autre programme</a>
    </div>
  <?php else: ?>

  <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>

  <!-- Explainer -->
  <div style="background:rgba(0,212,255,.05);border:1px solid rgba(0,212,255,.15);border-radius:10px;padding:14px 16px;margin-bottom:24px;display:flex;gap:12px;align-items:flex-start">
    <span style="font-size:20px;flex-shrink:0">&#128161;</span>
    <div style="font-size:13px;color:var(--label);line-height:1.6">
      <strong style="color:var(--accent)">Comment ça marche ?</strong><br>
      1. Collez l&rsquo;URL du programme et remplissez les informations que vous connaissez.<br>
      2. Notre équipe valide, complète si besoin, et publie le programme sur la plateforme.<br>
      3. Vous serez notifié(e) quand votre soumission est approuvée.
    </div>
  </div>

  <div class="card">
    <form method="POST" action="submit.php">
      <?= csrfField() ?>

      <!-- URL -->
      <div class="field">
        <label>URL du programme <span style="color:var(--accent)">*</span></label>
        <input type="url" name="url" placeholder="https://exemple.com/appel-a-candidatures" required value="<?= h($_POST['url'] ?? '') ?>">
        <div style="font-size:11px;color:var(--muted);margin-top:4px">Lien direct vers la page de candidature ou d&rsquo;information</div>
      </div>

      <!-- Nom + Org -->
      <div class="form-grid">
        <div class="field">
          <label>Nom du programme <span style="color:var(--accent)">*</span></label>
          <input type="text" name="name" placeholder="Ex: Google for Startups Africa" required value="<?= h($_POST['name'] ?? '') ?>">
        </div>
        <div class="field">
          <label>Organisation <span style="color:var(--accent)">*</span></label>
          <input type="text" name="organisation" placeholder="Ex: Google, AFD, ANAVA..." required value="<?= h($_POST['organisation'] ?? '') ?>">
        </div>
      </div>

      <!-- Type + Montant -->
      <div class="form-grid">
        <div class="field">
          <label>Type de programme</label>
          <select name="type">
            <option value="fund" <?= (($_POST['type']??'') === 'fund')?'selected':'' ?>>Fonds VC / Investissement</option>
            <option value="accelerator" <?= (($_POST['type']??'') === 'accelerator')?'selected':'' ?>>Acc&eacute;l&eacute;rateur</option>
            <option value="grant" <?= (($_POST['type']??'grant') === 'grant')?'selected':'' ?>>Subvention / Grant</option>
            <option value="competition" <?= (($_POST['type']??'') === 'competition')?'selected':'' ?>>Comp&eacute;tition / Prix</option>
            <option value="incubator" <?= (($_POST['type']??'') === 'incubator')?'selected':'' ?>>Incubateur</option>
          </select>
        </div>
        <div class="field">
          <label>Montant / Ticket (si connu)</label>
          <input type="text" name="amount" placeholder="Ex: 50K EUR, 500K USD..." value="<?= h($_POST['amount'] ?? '') ?>">
        </div>
      </div>

      <!-- Deadline + Geo -->
      <div class="form-grid">
        <div class="field">
          <label>Deadline (si connue)</label>
          <input type="text" name="deadline" placeholder="Ex: 30 Juin 2026, Rolling..." value="<?= h($_POST['deadline'] ?? '') ?>">
        </div>
        <div class="field">
          <label>G&eacute;ographie</label>
          <input type="text" name="geo" placeholder="Ex: Tunisie, Afrique, Global..." value="<?= h($_POST['geo'] ?? '') ?>">
        </div>
      </div>

      <!-- Secteurs -->
      <div class="field">
        <label>Secteurs concern&eacute;s</label>
        <input type="text" name="sectors" placeholder="Ex: Fintech, Agritech, IA, Tous secteurs..." value="<?= h($_POST['sectors'] ?? '') ?>">
        <div style="font-size:11px;color:var(--muted);margin-top:4px">S&eacute;parez les secteurs par des virgules</div>
      </div>

      <!-- Description -->
      <div class="field">
        <label>Description courte (si connue)</label>
        <textarea name="description" placeholder="D&eacute;crivez bri&egrave;vement ce programme : qui peut candidater, ce qu&rsquo;il offre, etc."><?= h($_POST['description'] ?? '') ?></textarea>
      </div>

      <!-- Notes admin -->
      <div class="field">
        <label>Notes pour l&rsquo;&eacute;quipe (optionnel)</label>
        <textarea name="notes" placeholder="Informations suppl&eacute;mentaires, contexte, pourquoi vous recommandez ce programme..." style="min-height:60px"><?= h($_POST['notes'] ?? '') ?></textarea>
      </div>

      <div style="display:flex;gap:12px;margin-top:8px">
        <button type="submit" class="btn btn-primary">Soumettre le programme &rarr;</button>
        <a href="dashboard.php" class="btn btn-secondary">Annuler</a>
      </div>

    </form>
  </div>
  <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
