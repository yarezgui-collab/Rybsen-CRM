<?php
require_once 'config.php';
require_once 'mailer.php';

// Already logged in → go to dashboard
if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

// Must arrive with pending_verify_uid in session
$uid   = (int)($_SESSION['pending_verify_uid']  ?? 0);
$email = $_SESSION['pending_verify_email'] ?? '';
if (!$uid || !$email) {
    header('Location: index.php');
    exit;
}

$db     = getDB();
$error  = '';
$msg    = '';
$msgOk  = false;
$sent   = (int)($_SESSION['pending_verify_sent'] ?? 1); // 0 = l'envoi automatique a échoué

const RESEND_COOLDOWN = 60;   // secondes entre deux renvois
const CODE_TTL        = 1800; // durée de validité du code (30 min)

// ── Resend code ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend'])) {
    verifyCsrf();
    $lastSent = (int)($_SESSION['pending_verify_time'] ?? 0);
    $wait = RESEND_COOLDOWN - (time() - $lastSent);
    if ($lastSent && $wait > 0) {
        $error = 'Veuillez patienter ' . $wait . ' seconde(s) avant de redemander un code.';
    } else {
        $code    = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $sent_at = time();
        $expires = date('Y-m-d H:i:s', $sent_at + CODE_TTL);
        $db->prepare('UPDATE fm_users SET email_verif_code=?, email_verif_expires=? WHERE id=?')
           ->execute([hash('sha256', $code), $expires, $uid]);
        $mail_ok = stn_send_verification_code($email, $code);
        $_SESSION['verify_attempts']     = 0;
        $_SESSION['pending_verify_time'] = $sent_at;
        $_SESSION['pending_verify_sent'] = $mail_ok ? 1 : 0;
        $sent = $mail_ok ? 1 : 0;
        if ($mail_ok) {
            $msg = 'Un nouveau code a été envoyé à ' . $email . '.';
            $msgOk = true;
        } else {
            $error = "L'envoi de l'email a échoué côté serveur. Réessayez dans un instant ou contactez l'administrateur si le problème persiste.";
        }
    }
}

// ── Verify code ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify'])) {
    verifyCsrf();
    $code_input = trim($_POST['code'] ?? '');
    $row = $db->prepare('SELECT email_verif_code, email_verif_expires FROM fm_users WHERE id=? LIMIT 1');
    $row->execute([$uid]);
    $row = $row->fetch();

    // Anti brute-force : max 5 tentatives, puis le code est invalidé
    $_SESSION['verify_attempts'] = ($_SESSION['verify_attempts'] ?? 0) + 1;
    if ($_SESSION['verify_attempts'] > 5) {
        $db->prepare('UPDATE fm_users SET email_verif_code=NULL, email_verif_expires=NULL WHERE id=?')
           ->execute([$uid]);
        $error = 'Trop de tentatives. Ce code est invalidé — cliquez sur "Renvoyer le code".';
    } elseif (!$row || !$row['email_verif_code']) {
        $error = 'Code invalide. Demandez un nouveau code.';
    } elseif (strtotime($row['email_verif_expires']) < time()) {
        $error = 'Ce code a expiré. Cliquez sur "Renvoyer le code".';
    } elseif (!hash_equals($row['email_verif_code'], hash('sha256', $code_input))) {
        $error = 'Code incorrect. Vérifiez l\'email envoyé à ' . $email . '.';
    } else {
        // Mark email as verified, clear code
        $db->prepare('UPDATE fm_users SET email_verified=1, email_verif_code=NULL, email_verif_expires=NULL WHERE id=?')
           ->execute([$uid]);

        // Notify admin to activate the account
        $startup = $db->prepare('SELECT startup_name FROM fm_users WHERE id=? LIMIT 1');
        $startup->execute([$uid]);
        $startup = $startup->fetch();
        stn_send_admin_new_user($email, $startup['startup_name'] ?? '');

        auditLog('email_verified', 'user', $uid, $email);

        // Clear session keys, redirect to login with success message
        unset($_SESSION['pending_verify_uid'], $_SESSION['pending_verify_email'], $_SESSION['verify_attempts']);
        header('Location: index.php?msg=verified');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vérifier votre email &mdash; Startup.TN</title>
<?php include 'auth_style.php'; ?>
<style>
/* Spécifique verify : champ code 6 chiffres + badge email */
.code-input{
  width:100%;padding:14px;
  background:var(--surface);border:1px solid var(--border);border-radius:8px;
  color:var(--text);font-family:var(--mono);font-size:28px;font-weight:700;
  text-align:center;letter-spacing:10px;outline:none;transition:border-color .2s;
}
.code-input:focus{border-color:var(--accent)}
.email-badge{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;background:rgba(56,189,248,.08);border:1px solid rgba(56,189,248,.2);border-radius:20px;font-size:12px;color:var(--accent);font-family:var(--mono);margin-bottom:16px}
</style>
</head>
<body>
<div class="auth-center">
<div class="wrap">
  <div class="logo-block">
    <div class="logo-icon">&#127481;&#127475;</div>
    <div class="logo-title">Startup<span>.TN</span></div>
  </div>

  <?php
    $codeAge         = time() - (int)($_SESSION['pending_verify_time'] ?? time());
    $expiryRemaining = max(0, CODE_TTL - $codeAge);
    $resendRemaining = max(0, RESEND_COOLDOWN - $codeAge);
  ?>
  <div class="card">
    <h2>&#9993; Vérification email</h2>
    <p>Un code à 6 chiffres a été envoyé à :</p>
    <div class="email-badge">&#128231; <?= h($email) ?></div>

    <?php if (!$sent && !$error && !$msg): ?>
      <div class="error-msg">&#9888; Nous n'avons pas pu envoyer l'email automatiquement. Cliquez sur <strong>« Renvoyer le code »</strong> ci-dessous.</div>
    <?php endif; ?>
    <?php if ($error): ?><div class="error-msg"><?= h($error) ?></div><?php endif; ?>
    <?php if ($msg): ?><div class="<?= $msgOk ? 'success-msg' : 'info-msg' ?>"><?= h($msg) ?></div><?php endif; ?>

    <form method="POST">
      <?= csrfField() ?>
      <div class="field">
        <label>Code de vérification</label>
        <input type="text" name="code" class="code-input"
          placeholder="000000" maxlength="6" pattern="\d{6}" inputmode="numeric"
          autocomplete="one-time-code" autofocus required>
      </div>
      <button type="submit" name="verify" class="btn-auth" style="margin-bottom:10px">Vérifier &rarr;</button>
    </form>

    <!-- Compte à rebours de validité du code -->
    <div id="expiry-line" style="text-align:center;font-size:12.5px;color:var(--muted);margin:4px 0 14px">
      <span id="expiry-ok">Ce code expire dans <strong id="expiry-timer" style="font-family:var(--mono);color:var(--accent)"></strong></span>
      <span id="expiry-done" style="display:none;color:var(--error)">Ce code a expiré — cliquez sur « Renvoyer le code ».</span>
    </div>

    <form method="POST">
      <?= csrfField() ?>
      <button type="submit" name="resend" id="resend-btn" class="btn-ghost">Renvoyer le code</button>
    </form>

    <p style="font-size:11px;color:var(--muted);margin-top:16px;text-align:center">Le code est valable 30 minutes. Vérifiez vos spams (et l'onglet « Promotions ») si vous ne le trouvez pas.</p>
  </div>

  <script>
  (function () {
    var expiryRemaining = <?= (int)$expiryRemaining ?>;
    var resendRemaining = <?= (int)$resendRemaining ?>;
    var timerEl   = document.getElementById('expiry-timer');
    var okEl      = document.getElementById('expiry-ok');
    var doneEl    = document.getElementById('expiry-done');
    var resendBtn = document.getElementById('resend-btn');
    var resendLabel = 'Renvoyer le code';

    function fmt(s) {
      var m = Math.floor(s / 60), sec = s % 60;
      return m + ':' + (sec < 10 ? '0' : '') + sec;
    }
    function tickExpiry() {
      if (expiryRemaining <= 0) {
        okEl.style.display = 'none';
        doneEl.style.display = '';
        return;
      }
      timerEl.textContent = fmt(expiryRemaining);
      expiryRemaining--;
      setTimeout(tickExpiry, 1000);
    }
    tickExpiry();

    function tickResend() {
      if (resendRemaining <= 0) {
        resendBtn.disabled = false;
        resendBtn.style.opacity = '';
        resendBtn.style.cursor = '';
        resendBtn.textContent = resendLabel;
        return;
      }
      resendBtn.disabled = true;
      resendBtn.style.opacity = '.55';
      resendBtn.style.cursor = 'not-allowed';
      resendBtn.textContent = resendLabel + ' (' + resendRemaining + 's)';
      resendRemaining--;
      setTimeout(tickResend, 1000);
    }
    tickResend();
  })();
  </script>

  <div class="back-link"><a href="index.php">&larr; Retour &agrave; la connexion</a></div>
</div>
</div>
</body>
</html>
