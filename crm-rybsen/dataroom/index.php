<?php
/** RYBSEN DATA ROOM — Connexion investisseur */
require_once __DIR__ . '/_dr.php';
require_once __DIR__ . '/_layout.php';

$db = getDB();
$error = '';

// Déjà connecté → router selon état NDA
if (drCurrentAccess($db)) {
    header('Location: ' . (intval(drCurrentAccess($db)['nda_signe']) ? '/dataroom/room.php' : '/dataroom/nda.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $ip       = clientIp();

    if ($email && $password) {
        $wait = max(throttleCheck($db, 'dataroom', $email), throttleCheck($db, 'dataroom', 'ip:' . $ip));
        if ($wait > 0) {
            $error = sprintf(t('throttled'), ceil($wait / 60));
        } else {
            $stmt = $db->prepare("SELECT * FROM dataroom_acces WHERE email=?");
            $stmt->execute([$email]);
            $acc = $stmt->fetch();

            if ($acc && intval($acc['actif']) && password_verify($password, $acc['password_hash'])) {
                if ($acc['date_expiration'] && strtotime($acc['date_expiration'] . ' 23:59:59') < time()) {
                    drLog($db, intval($acc['id']), 'acces_refuse', null, 'Accès expiré');
                    $error = t('expired');
                } else {
                    session_regenerate_id(true);
                    throttleReset($db, 'dataroom', $email);
                    throttleReset($db, 'dataroom', 'ip:' . $ip);
                    $_SESSION['dr_acces_id'] = intval($acc['id']);
                    $_SESSION['dr_csrf'] = bin2hex(random_bytes(32));
                    if (!isset($_SESSION['dr_lang'])) $_SESSION['dr_lang'] = $acc['langue'] ?: 'fr';
                    $db->prepare("UPDATE dataroom_acces SET derniere_connexion=CURRENT_TIMESTAMP WHERE id=?")
                       ->execute([$acc['id']]);
                    drLog($db, intval($acc['id']), 'login');
                    header('Location: ' . (intval($acc['nda_signe']) ? '/dataroom/room.php' : '/dataroom/nda.php'));
                    exit;
                }
            } else {
                throttleFail($db, 'dataroom', $email);
                throttleFail($db, 'dataroom', 'ip:' . $ip);
                drLog($db, $acc ? intval($acc['id']) : null, 'login_echec', null, 'Email: ' . substr($email, 0, 100));
                $error = t('bad_creds');
            }
        }
    } else {
        $error = t('bad_creds');
    }
}

drHead(t('title'));
?>
<div style="max-width:440px;margin:6vh auto 0">
  <div class="dr-card" style="padding:40px 36px">
    <div style="text-align:center;margin-bottom:30px">
      <div style="width:58px;height:58px;border-radius:15px;background:linear-gradient(135deg,var(--cyan),var(--cyan-dark));display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:26px">🔐</div>
      <div style="font-size:21px;font-weight:800;color:var(--navy-2)"><?= e(t('title')) ?></div>
      <div style="font-size:12.5px;color:var(--muted);margin-top:5px"><?= e(t('subtitle')) ?></div>
    </div>

    <?php if ($error): ?><div class="dr-alert err"><?= e($error) ?></div><?php endif; ?>

    <form method="POST" autocomplete="off">
      <div class="dr-field">
        <label><?= e(t('email')) ?></label>
        <input type="email" name="email" required autocomplete="username" value="<?= e($_POST['email'] ?? '') ?>">
      </div>
      <div class="dr-field">
        <label><?= e(t('password')) ?></label>
        <input type="password" name="password" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn-dr" style="width:100%"><?= e(t('login')) ?></button>
    </form>
  </div>
  <div style="text-align:center;margin-top:18px;font-size:11px;color:var(--muted);letter-spacing:2px;text-transform:uppercase">
    Green Printing is our goal — Benefits are yours
  </div>
</div>
<?php drFoot(); ?>
