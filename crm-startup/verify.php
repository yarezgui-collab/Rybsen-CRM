<?php
require_once 'config.php';

// Already logged in → go to dashboard
if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

// Must arrive with pending_verify_uid in session
$uid   = (int)($_SESSION['pending_verify_uid']  ?? 0);
$email = $_SESSION['pending_verify_email'] ?? '';
if (!$uid || !$email) {
    header('Location: index.php');
    exit;
}

$db    = getDB();
$error = '';
$msg   = '';

// ── Resend code ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend'])) {
    verifyCsrf();
    // Rate-limit: only resend if last code was sent > 60s ago
    $row = $db->prepare('SELECT email_verif_expires FROM fm_users WHERE id=? LIMIT 1');
    $row->execute([$uid]);
    $row = $row->fetch();
    $last_sent = $row ? strtotime($row['email_verif_expires'] ?? 0) - 1800 : 0;
    if (time() - $last_sent < 60) {
        $msg = 'Veuillez patienter avant de redemander un code.';
    } else {
        $code    = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', time() + 1800);
        $db->prepare('UPDATE fm_users SET email_verif_code=?, email_verif_expires=? WHERE id=?')
           ->execute([$code, $expires, $uid]);
        sendVerificationEmail($email, $code);
        $msg = 'Un nouveau code a été envoyé à ' . h($email) . '.';
    }
}

// ── Verify code ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify'])) {
    verifyCsrf();
    $code_input = trim($_POST['code'] ?? '');
    $row = $db->prepare('SELECT email_verif_code, email_verif_expires FROM fm_users WHERE id=? LIMIT 1');
    $row->execute([$uid]);
    $row = $row->fetch();

    if (!$row || !$row['email_verif_code']) {
        $error = 'Code invalide. Demandez un nouveau code.';
    } elseif (strtotime($row['email_verif_expires']) < time()) {
        $error = 'Ce code a expiré. Cliquez sur "Renvoyer le code".';
    } elseif ($code_input !== $row['email_verif_code']) {
        $error = 'Code incorrect. Vérifiez l\'email envoyé à ' . h($email) . '.';
    } else {
        // Mark email as verified, clear code
        $db->prepare('UPDATE fm_users SET email_verified=1, email_verif_code=NULL, email_verif_expires=NULL WHERE id=?')
           ->execute([$uid]);

        // Notify admin to activate the account
        $startup = $db->prepare('SELECT startup_name FROM fm_users WHERE id=? LIMIT 1');
        $startup->execute([$uid]);
        $startup = $startup->fetch();
        sendAdminNewUserNotif($email, $startup['startup_name'] ?? '');

        auditLog('email_verified', 'user', $uid, $email);

        // Clear session keys, redirect to login with success message
        unset($_SESSION['pending_verify_uid'], $_SESSION['pending_verify_email']);
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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#0d1117;--surface:#161b27;--card:#1c2333;--border:#2a3349;
  --accent:#38bdf8;--text:#f0f4f8;--muted:#A8B8CC;--label:#D2DFED;
  --error:#f87171;--success:#34d399;
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
.card p{font-size:13px;color:var(--muted);line-height:1.6;margin-bottom:24px}
.field{margin-bottom:18px}
.field label{display:block;font-size:12px;color:var(--label);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;font-family:var(--mono)}
.code-input{
  width:100%;padding:14px;
  background:var(--surface);border:1px solid var(--border);border-radius:8px;
  color:var(--text);font-family:var(--mono);font-size:28px;font-weight:700;
  text-align:center;letter-spacing:10px;outline:none;transition:border-color .2s;
}
.code-input:focus{border-color:var(--accent)}
.btn{
  width:100%;padding:13px;
  background:var(--accent);color:#000;
  border:none;border-radius:8px;
  font-family:var(--font);font-size:15px;font-weight:700;
  cursor:pointer;transition:opacity .15s;margin-bottom:10px
}
.btn:hover{opacity:.85}
.btn-ghost{
  width:100%;padding:10px;
  background:none;color:var(--muted);
  border:1px solid var(--border);border-radius:8px;
  font-family:var(--font);font-size:13px;font-weight:500;
  cursor:pointer;transition:all .15s
}
.btn-ghost:hover{border-color:var(--accent);color:var(--accent)}
.error-msg{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);color:#f87171;padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px}
.info-msg{background:rgba(56,189,248,.08);border:1px solid rgba(56,189,248,.2);color:var(--accent);padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px}
.email-badge{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;background:rgba(56,189,248,.08);border:1px solid rgba(56,189,248,.2);border-radius:20px;font-size:12px;color:var(--accent);font-family:var(--mono);margin-bottom:16px}
.back-link{text-align:center;margin-top:16px;font-size:13px;color:var(--muted)}
.back-link a{color:var(--accent);text-decoration:none}
</style>
</head>
<body>
<div class="wrap">
  <div class="logo-block">
    <div class="logo-icon">&#127481;&#127475;</div>
    <div class="logo-title">Startup<span>.TN</span></div>
  </div>

  <div class="card">
    <h2>&#9993; Vérification email</h2>
    <p>Un code à 6 chiffres a été envoyé à :</p>
    <div class="email-badge">&#128231; <?= h($email) ?></div>

    <?php if ($error): ?><div class="error-msg"><?= h($error) ?></div><?php endif; ?>
    <?php if ($msg): ?><div class="info-msg"><?= $msg ?></div><?php endif; ?>

    <form method="POST">
      <?= csrfField() ?>
      <div class="field">
        <label>Code de vérification</label>
        <input type="text" name="code" class="code-input"
          placeholder="000000" maxlength="6" pattern="\d{6}" inputmode="numeric"
          autocomplete="one-time-code" autofocus required>
      </div>
      <button type="submit" name="verify" class="btn">Vérifier &rarr;</button>
    </form>

    <form method="POST">
      <?= csrfField() ?>
      <button type="submit" name="resend" class="btn-ghost">Renvoyer le code</button>
    </form>

    <p style="font-size:11px;color:var(--muted);margin-top:16px;text-align:center">Le code est valable 30 minutes. Vérifiez vos spams si vous ne le trouvez pas.</p>
  </div>

  <div class="back-link"><a href="index.php">&larr; Retour &agrave; la connexion</a></div>
</div>
</body>
</html>
