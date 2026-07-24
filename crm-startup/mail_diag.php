<?php
// ============================================================
// mail_diag.php — Diagnostic d'envoi d'emails (admin uniquement)
// Permet de vérifier en production quel transport est utilisé et
// d'envoyer un vrai email de test. N'expose jamais les secrets.
// ============================================================
require_once 'config.php';
require_once 'mailer.php';
requireLogin();
requireAdmin();
$page_title = 'Diagnostic email';

$db = getDB();
$result = null;
$sent   = null;
$target = trim($_POST['to'] ?? ($_SESSION['fm_email'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test'])) {
    verifyCsrf();
    if (!filter_var($target, FILTER_VALIDATE_EMAIL)) {
        $result = 'Adresse email de test invalide.';
    } else {
        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $ok = stn_send_verification_code($target, $code);
        $sent = $ok;
        $result = $ok
            ? "Envoi accepté par le transport. Vérifiez la boîte de réception (et les spams) de $target."
            : "Le transport a refusé l'envoi. Voir la configuration ci-dessous et les logs d'erreur du serveur.";
        auditLog('mail_diag_test', 'user', (int)$_SESSION['fm_user_id'], $target . ($ok ? ' [ok]' : ' [ko]'));
    }
}

// État de configuration (sans jamais révéler les secrets)
$smtpConfigured = defined('SMTP_HOST') && SMTP_HOST && defined('SMTP_USER') && defined('SMTP_PASS');
$transport = $smtpConfigured ? 'SMTP authentifié (' . SMTP_HOST . ':' . (defined('SMTP_PORT') ? (int)SMTP_PORT : 465) . ')' : 'PHP mail() natif';
[$fromAddr, $fromName] = stn_mail_from();

include 'header.php';
?>

<div style="max-width:640px;margin:0 auto">
  <div style="margin-bottom:20px">
    <h1>Diagnostic d'envoi d'emails</h1>
    <p style="color:var(--muted);font-size:14px;margin-top:4px">Vérifiez la configuration et envoyez un email de test réel.</p>
  </div>

  <?php if ($result !== null): ?>
    <div class="alert <?= $sent ? 'alert-success' : 'alert-error' ?>"><?= $sent ? '&#10003; ' : '&#9888; ' ?><?= h($result) ?></div>
  <?php endif; ?>

  <div class="card" style="margin-bottom:16px">
    <div class="card-section-label">Configuration active</div>
    <div style="display:grid;gap:10px">
      <div class="modal-field">
        <div class="modal-field-label">Transport</div>
        <div class="modal-field-value"><?= h($transport) ?></div>
      </div>
      <div class="modal-field">
        <div class="modal-field-label">Expéditeur (From)</div>
        <div class="modal-field-value"><?= h($fromName) ?> &lt;<?= h($fromAddr) ?>&gt;</div>
      </div>
      <div class="modal-field">
        <div class="modal-field-label">Email admin (ADMIN_EMAIL)</div>
        <div class="modal-field-value"><?= defined('ADMIN_EMAIL') && ADMIN_EMAIL ? h(ADMIN_EMAIL) : '<span style="color:var(--accent5)">non défini</span>' ?></div>
      </div>
    </div>
    <?php if (!$smtpConfigured): ?>
      <div class="alert alert-warn" style="margin:16px 0 0">
        &#9888; Aucun SMTP configuré : les emails partent via <code>mail()</code>, souvent filtré ou marqué comme spam sur hébergement mutualisé.
        Pour une délivrabilité fiable, ajoutez dans <code>config.php</code> :
        <pre style="background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:12px;font-size:12px;color:var(--text-sec);overflow-x:auto;margin-top:10px">define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_PORT', 465);
define('SMTP_USER', 'noreply@startup.rybsen.fr');
define('SMTP_PASS', 'le_mot_de_passe_de_la_boite');
define('MAIL_FROM', 'noreply@startup.rybsen.fr');
define('MAIL_FROM_NAME', 'Startup.TN');</pre>
        La boîte <code>noreply@startup.rybsen.fr</code> se crée dans hPanel → Emails.
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-section-label">Envoyer un email de test</div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="send_test" value="1">
      <div class="field">
        <label>Adresse de destination</label>
        <input type="email" name="to" value="<?= h($target) ?>" placeholder="vous@exemple.com" required>
      </div>
      <button type="submit" class="btn btn-primary">Envoyer l'email de test</button>
    </form>
  </div>
</div>

<?php include 'footer.php'; ?>
