<?php
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND actif = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user'] = [
                'id' => $user['id'],
                'nom' => $user['nom'],
                'email' => $user['email'],
                'role' => $user['role'],
                'client_id' => $user['client_id'],
                'avatar' => $user['avatar']
            ];
            header('Location: index.php');
            exit;
        }
        $error = 'Email ou mot de passe incorrect.';
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
<title>CTP Maintenance — Connexion</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  :root {
    --charcoal: #23282D;
    --kodak-red: #DA291C;
    --kodak-yellow: #FFB81C;
    --panel: #F4F5F7;
  }
  body {
    min-height: 100vh;
    background: var(--charcoal);
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
    width: 620px; height: 620px;
    background: radial-gradient(circle, rgba(255,184,28,0.18) 0%, transparent 70%);
    top: -140px; right: -120px;
    border-radius: 50%;
  }
  body::after {
    content: '';
    position: absolute;
    width: 420px; height: 420px;
    background: radial-gradient(circle, rgba(218,41,28,0.16) 0%, transparent 70%);
    bottom: -80px; left: -60px;
    border-radius: 50%;
  }
  .login-card {
    background: #fff;
    border-radius: 16px;
    padding: 44px 40px;
    width: 100%;
    max-width: 420px;
    position: relative;
    z-index: 1;
    box-shadow: 0 24px 80px rgba(0,0,0,0.4);
  }
  .brand { text-align: center; margin-bottom: 32px; }
  .brand-logo {
    width: 58px; height: 58px;
    background: var(--kodak-yellow);
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 16px;
    font-size: 26px;
  }
  .brand-name {
    font-family: Georgia, serif;
    font-size: 21px;
    font-weight: bold;
    color: var(--charcoal);
    letter-spacing: 2px;
  }
  .brand-sub {
    font-size: 11px;
    color: var(--kodak-red);
    letter-spacing: 2px;
    text-transform: uppercase;
    margin-top: 5px;
    font-weight: 700;
  }
  .form-group { margin-bottom: 20px; }
  label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: var(--charcoal);
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 8px;
  }
  input[type="email"], input[type="password"] {
    width: 100%;
    padding: 14px 16px;
    border: 1.5px solid #D8DCE1;
    border-radius: 10px;
    font-size: 15px;
    font-family: Arial;
    background: #fff;
    color: var(--charcoal);
    transition: border-color 0.2s;
    outline: none;
  }
  input:focus {
    border-color: var(--kodak-red);
    box-shadow: 0 0 0 3px rgba(218,41,28,0.15);
  }
  .btn-login {
    width: 100%;
    padding: 15px;
    background: var(--kodak-red);
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 700;
    letter-spacing: 1px;
    cursor: pointer;
    margin-top: 8px;
    transition: background 0.2s, transform 0.1s;
  }
  .btn-login:hover { background: #b8221a; }
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
    margin-top: 26px;
    font-size: 11px;
    color: #9AA1AA;
    letter-spacing: 2px;
    text-transform: uppercase;
  }
  .slogan span { color: var(--kodak-red); font-weight: 700; }
</style>
</head>
<body>
<div class="login-card">
  <div class="brand">
    <div class="brand-logo">🖨️</div>
    <div class="brand-name">CTP MAINTENANCE</div>
    <div class="brand-sub">PrePresse · Filiale Kodak</div>
  </div>
  <?php if (!empty($error)): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="POST">
    <div class="form-group">
      <label>Adresse email</label>
      <input type="email" name="email" placeholder="admin@ctp.rybsen.com" required autocomplete="email">
    </div>
    <div class="form-group">
      <label>Mot de passe</label>
      <input type="password" name="password" placeholder="••••••••" required autocomplete="current-password">
    </div>
    <button type="submit" class="btn-login">SE CONNECTER</button>
  </form>
  <div class="slogan"><span>PARC CTP</span> · MAINTENANCE · PIÈCES</div>
</div>
</body>
</html>
