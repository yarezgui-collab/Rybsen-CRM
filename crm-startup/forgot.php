<?php
require_once 'config.php';
require_once 'mailer.php';
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
        $row->execute([hash('sha256', $token)]);
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
        // Rate limit par session : max 3 demandes / 15 min (anti flood email)
        $now = time();
        $_SESSION['reset_requests'] = array_filter($_SESSION['reset_requests'] ?? [], function ($t) use ($now) {
            return $now - $t < 900;
        });
        if (count($_SESSION['reset_requests']) >= 3) {
            $msg = 'Si cet email existe, un lien de réinitialisation a été envoyé. Vérifiez vos spams.';
        } else {
            $_SESSION['reset_requests'][] = $now;
            $row = $db->prepare('SELECT id, is_active, reset_token_expires FROM fm_users WHERE email=? LIMIT 1');
            $row->execute([$email]);
            $row = $row->fetch();
            // Rate limit par compte : pas de renvoi si un lien a été émis il y a moins de 5 min
            $recently_sent = $row && $row['reset_token_expires']
                && strtotime($row['reset_token_expires']) - $now > 3300;
            if ($row && !$recently_sent) {
                $tok     = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', $now + 3600);
                // Stocker uniquement le hash : une fuite de BDD n'expose pas le lien
                $db->prepare('UPDATE fm_users SET reset_token=?, reset_token_expires=? WHERE id=?')
                   ->execute([hash('sha256', $tok), $expires, $row['id']]);
                stn_send_reset_link($email, $tok);
                auditLog('password_reset_request', 'user', $row['id'], $email);
            }
            // Always show same message to prevent user enumeration
            $msg = 'Si cet email existe, un lien de réinitialisation a été envoyé. Vérifiez vos spams.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $reset_mode ? 'Nouveau mot de passe' : 'Mot de passe oublié' ?> — Startup.TN</title>
<?php include 'auth_style.php'; ?>
</head>
<body>
<div class="auth-center">
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
        <button type="submit" class="btn-auth">Enregistrer le nouveau mot de passe &rarr;</button>
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
        <button type="submit" class="btn-auth">Envoyer le lien &rarr;</button>
      </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="back-link"><a href="index.php">&larr; Retour &agrave; la connexion</a></div>
</div>
</div>

<?php if ($reset_mode) include 'pw_strength.php'; ?>
</body>
</html>
