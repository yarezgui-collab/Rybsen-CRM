<?php
/** RYBSEN DATA ROOM — Layout partagé (head/foot) */

function drHead(string $title, bool $fullscreen = false): void {
    global $DR_LANG;
    $langToggle = $DR_LANG === 'fr' ? 'en' : 'fr';
    $qs = $_GET; $qs['lang'] = $langToggle;
    $toggleUrl = e(strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($qs));
?>
<!DOCTYPE html>
<html lang="<?= $DR_LANG ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> — RYBSEN</title>
<style>
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
:root {
  --navy:#0E2433; --navy-2:#1A3A52; --cyan:#17B3CC; --cyan-dark:#0E8FA3;
  --gold:#E8A44C; --ink:#12222E; --muted:#5E7180; --line:#E3EAEE;
  --bg:#F4F7F9; --card:#FFFFFF; --ok:#16a34a; --err:#dc2626;
}
html { -webkit-text-size-adjust:100%; }
body {
  font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
  background:var(--bg); color:var(--ink); font-size:15px; line-height:1.55;
  min-height:100vh; display:flex; flex-direction:column;
}
a { color:var(--cyan-dark); }
.dr-topbar {
  background:linear-gradient(120deg, var(--navy) 0%, var(--navy-2) 100%);
  color:#fff; padding:14px 28px; display:flex; align-items:center; gap:14px;
  border-bottom:3px solid var(--cyan);
}
.dr-brand { display:flex; align-items:center; gap:11px; text-decoration:none; }
.dr-brand-mark {
  width:36px; height:36px; border-radius:9px; background:var(--cyan);
  display:flex; align-items:center; justify-content:center; font-size:17px; color:#fff; font-weight:800;
}
.dr-brand-name { font-size:16px; font-weight:800; letter-spacing:3px; color:#fff; }
.dr-brand-sub  { font-size:10px; letter-spacing:2px; color:var(--cyan); text-transform:uppercase; }
.dr-topbar-right { margin-left:auto; display:flex; align-items:center; gap:12px; }
.dr-lang {
  color:#fff; text-decoration:none; font-size:12px; font-weight:700;
  border:1px solid rgba(255,255,255,.3); border-radius:20px; padding:5px 14px;
}
.dr-lang:hover { border-color:var(--cyan); color:var(--cyan); }
.dr-logout { color:rgba(255,255,255,.7); text-decoration:none; font-size:13px; }
.dr-logout:hover { color:#fff; }
.dr-main { flex:1; width:100%; <?= $fullscreen ? '' : 'max-width:1080px; margin:0 auto; padding:34px 22px;' ?> }
.dr-footer {
  text-align:center; font-size:11.5px; color:var(--muted);
  padding:20px; border-top:1px solid var(--line); background:#fff;
}
.dr-card {
  background:var(--card); border:1px solid var(--line); border-radius:14px;
  box-shadow:0 3px 16px rgba(14,36,51,.06);
}
.btn-dr {
  display:inline-flex; align-items:center; justify-content:center; gap:8px;
  background:linear-gradient(120deg, var(--cyan) 0%, var(--cyan-dark) 100%);
  color:#fff; border:none; border-radius:10px; padding:13px 26px;
  font-size:14px; font-weight:700; cursor:pointer; letter-spacing:.3px;
  transition:opacity .15s, transform .1s; text-decoration:none;
}
.btn-dr:hover { opacity:.92; }
.btn-dr:active { transform:scale(.99); }
.btn-dr:disabled { background:#B9C6CE; cursor:not-allowed; }
.dr-alert { border-radius:10px; padding:12px 16px; font-size:13.5px; margin-bottom:18px; }
.dr-alert.err { background:#FEF2F2; color:var(--err); border:1px solid #FECACA; }
.dr-alert.ok  { background:#F0FDF4; color:#166534; border:1px solid #BBF7D0; }
.dr-field { margin-bottom:18px; }
.dr-field label {
  display:block; font-size:11px; font-weight:700; text-transform:uppercase;
  letter-spacing:1.4px; color:var(--muted); margin-bottom:7px;
}
.dr-field input[type=email], .dr-field input[type=password], .dr-field input[type=text],
.dr-field select, .dr-field textarea {
  width:100%; padding:13px 15px; border:1.5px solid var(--line); border-radius:10px;
  font-size:15px; font-family:inherit; color:var(--ink); background:#fff; outline:none;
  transition:border-color .15s, box-shadow .15s;
}
.dr-field input:focus, .dr-field select:focus, .dr-field textarea:focus {
  border-color:var(--cyan); box-shadow:0 0 0 3px rgba(23,179,204,.14);
}
@media (max-width:640px){ .dr-main{ padding:20px 14px; } .dr-topbar{ padding:12px 16px; } }
</style>
</head>
<body>
<header class="dr-topbar">
  <a class="dr-brand" href="/dataroom/">
    <div class="dr-brand-mark">R</div>
    <div>
      <div class="dr-brand-name">RYBSEN</div>
      <div class="dr-brand-sub"><?= e(t('title')) ?></div>
    </div>
  </a>
  <div class="dr-topbar-right">
    <a class="dr-lang" href="<?= $toggleUrl ?>"><?= $DR_LANG === 'fr' ? 'EN 🇬🇧' : 'FR 🇫🇷' ?></a>
    <?php if (!empty($_SESSION['dr_acces_id'])): ?>
      <a class="dr-logout" href="/dataroom/logout.php"><?= e(t('logout')) ?></a>
    <?php endif; ?>
  </div>
</header>
<main class="dr-main">
<?php
}

function drFoot(): void {
?>
</main>
<footer class="dr-footer">
  <strong>RYBSEN SARL</strong> — Centre Millenium, La Marsa, Tunisie · Patent FR3070137<br>
  <?= e(t('footer_notice')) ?>
</footer>
</body>
</html>
<?php
}
