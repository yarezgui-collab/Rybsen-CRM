<?php
require_once 'config.php';
require_once 'includes/security.php';
sendSecurityHeaders(true);
secureSessionStart();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $db = getDB();
        $ip = clientIp();
        $wait = max(throttleCheck($db, 'crm', $email), throttleCheck($db, 'crm', 'ip:' . $ip));
        if ($wait > 0) {
            $error = 'Trop de tentatives. Réessayez dans ' . ceil($wait / 60) . ' min.';
        } else {
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND actif = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                throttleReset($db, 'crm', $email);
                throttleReset($db, 'crm', 'ip:' . $ip);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'nom' => $user['nom'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'avatar' => $user['avatar']
                ];
                header('Location: index.php');
                exit;
            }
            throttleFail($db, 'crm', $email);
            throttleFail($db, 'crm', 'ip:' . $ip);
            $error = 'Email ou mot de passe incorrect.';
        }
    }
}

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RYBSEN CRM — Connexion</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  :root {
    --navy: #1A3A52;
    --teal: #4A9B8F;
    --gold: #E8A44C;
    --cream: #FAFAF7;
    --teal-light: rgba(74,155,143,0.12);
  }
  body {
    min-height: 100vh;
    background: var(--navy);
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: Arial, Helvetica, sans-serif;
    position: relative;
    overflow: hidden;
  }
  body::before {
    content: '';
    position: absolute;
    width: 600px; height: 600px;
    background: radial-gradient(circle, rgba(74,155,143,0.15) 0%, transparent 70%);
    top: -100px; right: -100px;
    border-radius: 50%;
  }
  body::after {
    content: '';
    position: absolute;
    width: 400px; height: 400px;
    background: radial-gradient(circle, rgba(232,164,76,0.08) 0%, transparent 70%);
    bottom: -50px; left: -50px;
    border-radius: 50%;
  }
  .login-card {
    background: var(--cream);
    border-radius: 16px;
    padding: 48px 40px;
    width: 100%;
    max-width: 420px;
    position: relative;
    z-index: 1;
    box-shadow: 0 24px 80px rgba(0,0,0,0.3);
  }
  .brand {
    text-align: center;
    margin-bottom: 36px;
  }
  .brand-logo {
    width: 56px; height: 56px;
    background: var(--navy);
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 16px;
    font-size: 24px;
  }
  .brand-name {
    font-family: Georgia, serif;
    font-size: 22px;
    font-weight: bold;
    color: var(--navy);
    letter-spacing: 3px;
  }
  .brand-sub {
    font-size: 11px;
    color: var(--teal);
    letter-spacing: 2px;
    text-transform: uppercase;
    margin-top: 4px;
  }
  .form-group {
    margin-bottom: 20px;
  }
  label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: var(--navy);
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 8px;
  }
  input[type="email"], input[type="password"] {
    width: 100%;
    padding: 14px 16px;
    border: 1.5px solid #D5D5D0;
    border-radius: 10px;
    font-size: 15px;
    font-family: Arial;
    background: white;
    color: var(--navy);
    transition: border-color 0.2s;
    outline: none;
  }
  input:focus {
    border-color: var(--teal);
    box-shadow: 0 0 0 3px rgba(74,155,143,0.15);
  }
  .btn-login {
    width: 100%;
    padding: 15px;
    background: var(--navy);
    color: var(--cream);
    border: none;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    letter-spacing: 1px;
    cursor: pointer;
    margin-top: 8px;
    transition: background 0.2s, transform 0.1s;
  }
  .btn-login:hover { background: #243f5a; }
  .btn-login:active { transform: scale(0.99); }
  .error {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
    border-radius: 8px;
    padding: 12px 14px;
    font-size: 13px;
    margin-bottom: 20px;
  }
  .slogan {
    text-align: center;
    margin-top: 28px;
    font-size: 11px;
    color: #999;
    letter-spacing: 2px;
    text-transform: uppercase;
  }
  .slogan span { color: var(--teal); font-weight: 600; }
</style>
</head>
<body>
<div class="login-card">
  <div class="brand">
    <div class="brand-logo">💧</div>
    <div class="brand-name">RYBSEN</div>
    <div class="brand-sub">CRM — Plateforme de Pilotage</div>
  </div>
  <?php if (!empty($error)): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="POST">
    <div class="form-group">
      <label>Adresse email</label>
      <input type="email" name="email" placeholder="yrezgui@rybsen.fr" required autocomplete="email">
    </div>
    <div class="form-group">
      <label>Mot de passe</label>
      <input type="password" name="password" placeholder="••••••••" required autocomplete="current-password">
    </div>
    <button type="submit" class="btn-login">SE CONNECTER</button>
  </form>
  <div class="slogan"><span>BE THE FLOW</span></div>
</div>
</body>
</html>
