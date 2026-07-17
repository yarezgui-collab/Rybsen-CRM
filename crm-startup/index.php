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
    verifyCsrf();
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
                // Anti fixation de session : nouvel ID à chaque authentification
                session_regenerate_id(true);
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
<title>Connexion — Startup.TN</title>
<?php include 'auth_style.php'; ?>
</head>
<body>
<div class="auth-center">
<div class="wrap">
  <div class="logo-block">
    <div class="logo-icon">&#127481;&#127475;</div>
    <div class="logo-title">Startup<span>.TN</span></div>
    <div class="logo-sub">Plateforme de veille financement pour startups tunisiennes</div>
  </div>
  <div class="card">
    <h2 style="margin-bottom:24px">Connexion</h2>
    <?php if ($error): ?>
      <div class="error-msg"><?= h($error) ?></div>
    <?php endif; ?>
    <?php if ($msg === 'session_expired'): ?>
      <div class="info-msg">Votre session a expiré. Veuillez vous reconnecter.</div>
    <?php elseif ($msg === 'registered'): ?>
      <div class="success-msg">Compte créé ! Vérifiez votre email puis attendez l'activation par l'administrateur.</div>
    <?php elseif ($msg === 'verified'): ?>
      <div class="success-msg">&#10003; Email vérifié ! Votre compte sera activé par l'administrateur sous peu.</div>
    <?php elseif ($msg === 'reset_done'): ?>
      <div class="success-msg">&#10003; Mot de passe modifié avec succès. Connectez-vous ci-dessous.</div>
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
      <button type="submit" class="btn-auth">Se connecter &rarr;</button>
    </form>
    <div style="text-align:right;margin-top:8px">
      <a href="forgot.php" style="font-size:13px;color:var(--muted);text-decoration:none">Mot de passe oublié ?</a>
    </div>
    <div class="divider">ou</div>
    <div class="back-link" style="margin-top:0">
      Pas encore de compte ? <a href="register.php">Créer un compte startup</a>
    </div>
  </div>
</div>
</div>
</body>
</html>
