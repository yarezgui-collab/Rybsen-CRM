<?php
require_once 'config.php';

// Déjà connecté → redirect
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$msg   = $_GET['msg'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        // Protection brute force : max 5 tentatives par 15 min
        $attempts = checkLoginAttempts($email);
        if ($attempts >= 5) {
            $error = 'Trop de tentatives. Réessayez dans 15 minutes.';
        } else {
            $db   = getDB();
            $stmt = $db->prepare('SELECT * FROM fm_users WHERE email = ? AND is_active = 1 LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                resetLoginAttempts($email);
                $_SESSION['fm_user_id'] = $user['id'];
                $_SESSION['fm_role']    = $user['role'];
                $_SESSION['fm_name']    = $user['startup_name'];
                $_SESSION['fm_email']   = $user['email'];
                $db->prepare('UPDATE fm_users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);
                auditLog('login', 'user', $user['id']);
                header('Location: dashboard.php');
                exit;
            } else {
                recordLoginFailure($email);
                $remaining = 5 - checkLoginAttempts($email);
                $error = 'Email ou mot de passe incorrect.' . ($remaining <= 2 ? ' (' . $remaining . ' tentative(s) restante(s))' : '');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Connexion &mdash; Startup.TN</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root {
  --bg:#0d1117; --surface:#161b27; --card:#1c2333; --border:#2a3349;
  --accent:#38bdf8; --text:#f0f4f8; --muted:#A8B8CC; --label:#D2DFED;
  --error:#f87171; --success:#34d399;
  --font:'Inter',-apple-system,'Segoe UI',sans-serif; --mono:'DM Mono','Courier New',monospace;
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:var(--font);background:var(--bg);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.login-wrap{width:100%;max-width:420px}
.logo-block{text-align:center;margin-bottom:32px}
.logo-icon{font-size:40px;margin-bottom:12px}
.logo-title{font-family:var(--mono);font-size:24px;font-weight:700;color:var(--accent);letter-spacing:-1px}
.logo-title span{color:var(--text)}
.logo-sub{font-size:14px;color:var(--muted);margin-top:6px}
.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:32px}
.card h2{font-size:18px;font-weight:600;margin-bottom:24px;color:#fff}
.field{margin-bottom:18px}
.field label{display:block;font-size:12px;color:var(--label);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;font-family:var(--mono)}
.field input{
  width:100%;padding:11px 14px;
  background:var(--surface);border:1px solid var(--border);border-radius:8px;
  color:var(--text);font-family:var(--font);font-size:14px;outline:none;
  transition:border-color .2s
}
.field input:focus{border-color:var(--accent)}
.btn-login{
  width:100%;padding:13px;
  background:var(--accent);color:#000;
  border:none;border-radius:8px;
  font-family:var(--font);font-size:15px;font-weight:700;
  cursor:pointer;transition:opacity .15s;margin-top:8px
}
.btn-login:hover{opacity:.85}
.error-msg{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);color:#f87171;padding:10px 14px;border-radius:8px;font-size:14px;margin-bottom:16px}
.info-msg{background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.25);color:#fbbf24;padding:10px 14px;border-radius:8px;font-size:14px;margin-bottom:16px}
.register-link{text-align:center;margin-top:16px;font-size:14px;color:var(--muted)}
.register-link a{color:var(--accent);text-decoration:none}
.register-link a:hover{text-decoration:underline}
.divider{text-align:center;color:var(--muted);font-size:12px;margin:20px 0;position:relative}
.divider::before,.divider::after{content:'';position:absolute;top:50%;width:40%;height:1px;background:var(--border)}
.divider::before{left:0}.divider::after{right:0}
</style>
</head>
<body>
<div class="login-wrap">
  <div class="logo-block">
    <div class="logo-icon">&#127481;&#127475;</div>
    <div class="logo-title">Startup<span>.TN</span></div>
    <div class="logo-sub">Plateforme de veille financement pour startups tunisiennes</div>
  </div>
  <div class="card">
    <h2>Connexion</h2>
    <?php if ($error): ?>
      <div class="error-msg"><?= h($error) ?></div>
    <?php endif; ?>
    <?php if ($msg === 'session_expired'): ?>
      <div class="info-msg">Votre session a expiré. Veuillez vous reconnecter.</div>
    <?php elseif ($msg === 'registered'): ?>
      <div class="info-msg" style="background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.25);color:#34d399">Compte créé ! Vérifiez votre email puis attendez l'activation par l'administrateur.</div>
    <?php elseif ($msg === 'verified'): ?>
      <div class="info-msg" style="background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.25);color:#34d399">&#10003; Email vérifié ! Votre compte sera activé par l'administrateur sous peu.</div>
    <?php elseif ($msg === 'reset_done'): ?>
      <div class="info-msg" style="background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.25);color:#34d399">&#10003; Mot de passe modifié avec succès. Connectez-vous ci-dessous.</div>
    <?php endif; ?>
    <form method="POST" action="index.php">
      <div class="field">
        <label>Email</label>
        <input type="email" name="email" placeholder="startup@exemple.tn" required autofocus value="<?= h($_POST['email'] ?? '') ?>">
      </div>
      <div class="field">
        <label>Mot de passe</label>
        <input type="password" name="password" placeholder="••••••••" required>
      </div>
      <?php echo csrfField(); ?>
      <button type="submit" class="btn-login">Se connecter &rarr;</button>
    </form>
    <div style="text-align:right;margin-top:8px">
      <a href="forgot.php" style="font-size:13px;color:var(--muted);text-decoration:none">Mot de passe oublié ?</a>
    </div>
    <div class="divider">ou</div>
    <div class="register-link">
      Pas encore de compte ? <a href="register.php">Créer un compte startup</a>
    </div>
  </div>
</div>
</body>
</html>
