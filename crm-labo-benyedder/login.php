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
                'point_vente_id' => $user['point_vente_id'],
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
<title>CRM Labo Ben Yedder — Connexion</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  :root {
    --wheat: #6B4A2F;
    --amber: #BD6A1E;
    --cream: #FAF6EF;
    --amber-light: rgba(189,106,30,0.12);
  }
  body {
    min-height: 100vh;
    background: var(--wheat);
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
    background: radial-gradient(circle, rgba(189,106,30,0.18) 0%, transparent 70%);
    top: -100px; right: -100px;
    border-radius: 50%;
  }
  body::after {
    content: '';
    position: absolute;
    width: 400px; height: 400px;
    background: radial-gradient(circle, rgba(250,246,239,0.10) 0%, transparent 70%);
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
    background: var(--wheat);
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 16px;
    font-size: 24px;
  }
  .brand-name {
    font-family: Georgia, serif;
    font-size: 20px;
    font-weight: bold;
    color: var(--wheat);
    letter-spacing: 1px;
  }
  .brand-sub {
    font-size: 11px;
    color: var(--amber);
    letter-spacing: 2px;
    text-transform: uppercase;
    margin-top: 4px;
  }
  .form-group { margin-bottom: 20px; }
  label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: var(--wheat);
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 8px;
  }
  input[type="email"], input[type="password"] {
    width: 100%;
    padding: 14px 16px;
    border: 1.5px solid #DDD1C2;
    border-radius: 10px;
    font-size: 15px;
    font-family: Arial;
    background: white;
    color: var(--wheat);
    transition: border-color 0.2s;
    outline: none;
  }
  input:focus {
    border-color: var(--amber);
    box-shadow: 0 0 0 3px rgba(189,106,30,0.15);
  }
  .btn-login {
    width: 100%;
    padding: 15px;
    background: var(--amber);
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
  .btn-login:hover { background: #A35A19; }
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
    color: #9C8A78;
    letter-spacing: 2px;
    text-transform: uppercase;
  }
  .slogan span { color: var(--amber); font-weight: 600; }
</style>
</head>
<body>
<div class="login-card">
  <div class="brand">
    <div class="brand-logo">🥐</div>
    <div class="brand-name">BEN YEDDER</div>
    <div class="brand-sub">CRM Labo — Traiteur &amp; Pâtisserie</div>
  </div>
  <?php if (!empty($error)): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="POST">
    <div class="form-group">
      <label>Adresse email</label>
      <input type="email" name="email" placeholder="admin@benyedder.tn" required autocomplete="email">
    </div>
    <div class="form-group">
      <label>Mot de passe</label>
      <input type="password" name="password" placeholder="••••••••" required autocomplete="current-password">
    </div>
    <button type="submit" class="btn-login">SE CONNECTER</button>
  </form>
  <div class="slogan"><span>LABO</span> · FRANCHISES · POINTS DE VENTE</div>
</div>
</body>
</html>
