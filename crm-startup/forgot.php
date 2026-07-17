<?php
require_once 'config.php';
if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

$db    = getDB();
$error = '';
$msg   = '';

// ── Step 2 : Reset password from token ──────────────────────────
$token = trim($_GET['token'] ?? '');
$reset_mode = ($token !== '');

if ($reset_mode && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $new_pass = $_POST['new_password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (!$new_pass) {
        $error = 'Veuillez saisir un nouveau mot de passe.';
    } elseif (strlen($new_pass) < 8) {
        $error = 'Minimum 8 caractères.';
    } elseif ($new_pass !== $confirm) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        $row = $db->prepare('SELECT id FROM fm_users WHERE reset_token=? AND reset_token_expires > NOW() LIMIT 1');
        $row->execute([$token]);
        $row = $row->fetch();
        if (!$row) {
            $error = 'Ce lien est invalide ou expiré. Demandez un nouveau lien.';
        } else {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $db->prepare('UPDATE fm_users SET password=?, reset_token=NULL, reset_token_expires=NULL WHERE id=?')
               ->execute([$hash, $row['id']]);
            auditLog('reset_password', 'user', $row['id']);
            header('Location: index.php?msg=reset_done');
            exit;
        }
    }
}

// ── Step 1 : Request reset email ─────────────────────────────────
if (!$reset_mode && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email = trim($_POST['email'] ?? '');
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse email invalide.';
    } else {
        $row = $db->prepare('SELECT id, is_active FROM fm_users WHERE email=? LIMIT 1');
        $row->execute([$email]);
        $row = $row->fetch();
        if ($row) {
            $tok     = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600);
            $db->prepare('UPDATE fm_users SET reset_token=?, reset_token_expires=? WHERE id=?')
               ->execute([$tok, $expires, $row['id']]);
            sendResetEmail($email, $tok);
            auditLog('password_reset_request', 'user', $row['id'], $email);
        }
        // Always show same message to prevent user enumeration
        $msg = 'Si cet email existe, un lien de réinitialisation a été envoyé. Vérifiez vos spams.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $reset_mode ? 'Nouveau mot de passe' : 'Mot de passe oublié' ?> &mdash; Startup.TN</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#0d1117;--surface:#161b27;--card:#1c2333;--border:#2a3349;
  --accent:#38bdf8;--text:#f0f4f8;--muted:#A8B8CC;--label:#D2DFED;
  --error:#f87171;
  --font:'Inter',-apple-system,'Segoe UI',sans-serif;--mono:'DM Mono','Courier New',monospace;
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:var(--font);background:var(--bg);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.wrap{width:100%;max-width:420px}
.logo-block{text-align:center;margin-bottom:28px}
.logo-icon{font-size:36px;margin-bottom:10px}
.logo-title{font-family:var(--mono);font-size:22px;font-weight:700;color:var(--accent);letter-spacing:-1px}
.logo-title span{color:var(--text)}
.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:32px}
.card h2{font-size:18px;font-weight:600;margin-bottom:8px;color:#fff}
.card p{font-size:13px;color:var(--muted);line-height:1.6;margin-bottom:20px}
.field{margin-bottom:18px}
.field label{display:block;font-size:12px;color:var(--label);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;font-family:var(--mono)}
.field input{
  width:100%;padding:11px 14px;
  background:var(--surface);border:1px solid var(--border);border-radius:8px;
  color:var(--text);font-family:var(--font);font-size:14px;outline:none;transition:border-color .2s;
}
.field input:focus{border-color:var(--accent)}
.btn{
  width:100%;padding:13px;
  background:var(--accent);color:#000;
  border:none;border-radius:8px;
  font-family:var(--font);font-size:15px;font-weight:700;
  cursor:pointer;transition:opacity .15s;margin-top:4px
}
.btn:hover{opacity:.85}
.error-msg{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);color:#f87171;padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px}
.info-msg{background:rgba(56,189,248,.08);border:1px solid rgba(56,189,248,.2);color:var(--accent);padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px}
.back-link{text-align:center;margin-top:16px;font-size:13px;color:var(--muted)}
.back-link a{color:var(--accent);text-decoration:none}

/* Password strength */
.pw-strength{margin-top:6px;height:4px;border-radius:2px;background:var(--border);overflow:hidden}
.pw-strength-bar{height:100%;border-radius:2px;width:0;transition:width .3s,background .3s}
.pw-hint{font-size:11px;color:var(--muted);margin-top:4px}
</style>
</head>
<body>
<div class="wrap">
  <div class="logo-block">
    <div class="logo-icon">&#127481;&#127475;</div>
    <div class="logo-title">Startup<span>.TN</span></div>
  </div>

  <div class="card">
    <?php if ($reset_mode): ?>
      <h2>&#128274; Nouveau mot de passe</h2>
      <p>Choisissez un nouveau mot de passe pour votre compte.</p>

      <?php if ($error): ?><div class="error-msg"><?= h($error) ?></div><?php endif; ?>

      <form method="POST">
        <?= csrfField() ?>
        <div class="field">
          <label>Nouveau mot de passe</label>
          <input type="password" name="new_password" id="pw1" placeholder="Min. 8 caract&egrave;res" required autofocus>
          <div class="pw-strength"><div class="pw-strength-bar" id="pw-bar"></div></div>
          <div class="pw-hint" id="pw-hint">Min. 8 caractères</div>
        </div>
        <div class="field">
          <label>Confirmer</label>
          <input type="password" name="confirm_password" id="pw2" placeholder="R&eacute;p&eacute;tez" required>
          <div class="pw-hint" id="pw2-hint" style="color:transparent">—</div>
        </div>
        <button type="submit" class="btn">Enregistrer le nouveau mot de passe &rarr;</button>
      </form>

    <?php else: ?>
      <h2>&#128274; Mot de passe oubli&eacute; ?</h2>
      <p>Entrez votre adresse email. Si un compte existe, vous recevrez un lien de r&eacute;initialisation valable 1 heure.</p>

      <?php if ($error): ?><div class="error-msg"><?= h($error) ?></div><?php endif; ?>
      <?php if ($msg): ?><div class="info-msg">&#10003; <?= h($msg) ?></div><?php endif; ?>

      <?php if (!$msg): ?>
      <form method="POST">
        <?= csrfField() ?>
        <div class="field">
          <label>Email</label>
          <input type="email" name="email" placeholder="startup@exemple.tn" required autofocus>
        </div>
        <button type="submit" class="btn">Envoyer le lien &rarr;</button>
      </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="back-link"><a href="index.php">&larr; Retour &agrave; la connexion</a></div>
</div>

<?php if ($reset_mode): ?>
<script>
(function() {
  var pw1 = document.getElementById('pw1');
  var pw2 = document.getElementById('pw2');
  var bar = document.getElementById('pw-bar');
  var hint = document.getElementById('pw-hint');
  var hint2 = document.getElementById('pw2-hint');

  function score(pw) {
    var s = 0;
    if (pw.length >= 8)  s++;
    if (pw.length >= 12) s++;
    if (/[A-Z]/.test(pw)) s++;
    if (/[0-9]/.test(pw)) s++;
    if (/[^A-Za-z0-9]/.test(pw)) s++;
    return s;
  }
  pw1.addEventListener('input', function() {
    var s = score(this.value);
    var colors = ['#f87171','#fb923c','#facc15','#34d399','#34d399'];
    var labels = ['Très faible','Faible','Moyen','Fort','Très fort'];
    bar.style.width = this.value ? Math.min(100, s * 20) + '%' : '0';
    bar.style.background = colors[Math.max(0, s-1)];
    hint.textContent = this.value ? labels[Math.max(0, s-1)] : 'Min. 8 caractères';
    hint.style.color = this.value ? colors[Math.max(0, s-1)] : 'var(--muted)';
    checkMatch();
  });
  pw2.addEventListener('input', checkMatch);
  function checkMatch() {
    if (!pw2.value) { hint2.style.color = 'transparent'; return; }
    if (pw1.value === pw2.value) {
      hint2.textContent = '✓ Mots de passe identiques'; hint2.style.color = '#34d399';
    } else {
      hint2.textContent = '✗ Ne correspondent pas'; hint2.style.color = '#f87171';
    }
  }
})();
</script>
<?php endif; ?>
</body>
</html>
