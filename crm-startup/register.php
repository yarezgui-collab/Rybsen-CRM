<?php
require_once 'config.php';
if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

$error = '';

$sectors_list = [
  'IA / AI','Fintech','Cleantech','Agritech','Healthtech','Edtech',
  'Watertech','Greentech','Deep Tech','Logistique','E-commerce',
  'Cybersécurité','Life Sciences','Impact Social','Tourisme Tech','SaaS / B2B','Autre'
];
$stages = [
  'idee'         => 'Idée / Pré-création (pas encore de produit)',
  'bootstrapping'=> 'Bootstrapping (auto-financé, revenus propres)',
  'pre-seed'     => 'Pre-Seed (premiers angels / F&F, < 500K EUR)',
  'seed'         => 'Seed (fonds seed / accélérateurs, 500K – 3M EUR)',
  'series-a'     => 'Série A (VCs institutionnels, 3M – 15M EUR)',
  'series-b'     => 'Série B (expansion, 15M – 50M EUR)',
  'series-c'     => 'Série C (scale international, 50M – 150M EUR)',
  'growth'       => 'Growth / Late Stage (+ 150M EUR)',
  'pre-ipo'      => 'Pré-IPO / Exit',
];
$revenue_ranges = ['pre-revenue'=>'Pré-revenus','0-10k'=>'0 – 10K EUR','10k-50k'=>'10K – 50K EUR','50k-200k'=>'50K – 200K EUR','200k-1m'=>'200K – 1M EUR','1m+'=>'+ 1M EUR'];
$funding_types  = ['Investissement equity','Subvention / Grant','Prêt','Convertible note','Crowdfunding','Bootstrap','Tout type'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_final'])) {
    verifyCsrf();
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2= $_POST['password2'] ?? '';
    $name     = trim($_POST['startup_name'] ?? '');

    if (!$email || !$password || !$name) {
        $error = 'Veuillez remplir les champs obligatoires (nom, email, mot de passe).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email invalide.';
    } elseif (strlen($password) < 8) {
        $error = 'Mot de passe minimum 8 caractères.';
    } elseif ($password !== $password2) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        $db  = getDB();
        $chk = $db->prepare('SELECT id FROM fm_users WHERE email=? LIMIT 1');
        $chk->execute([$email]);
        if ($chk->fetch()) {
            $error = 'Cet email est déjà utilisé.';
        } else {
            $hash   = password_hash($password, PASSWORD_DEFAULT);
            $ft_val = implode(', ', array_filter((array)($_POST['funding_type'] ?? [])));
            $stmt   = $db->prepare('INSERT INTO fm_users
              (email, password, startup_name, sector, stage, website, city,
               founded_year, founders_count, ceo_name, elevator_pitch, problem,
               solution, has_tech_team, revenue_range, users_count,
               funding_raised, funding_target, funding_type, looking_for,
               role, is_active, email_verified)
              VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,0)');
            $stmt->execute([
                $email, $hash, $name,
                isset($_POST['sectors']) ? implode(',', array_map('trim', (array)$_POST['sectors'])) : null,
                $_POST['stage'] ?? 'seed',
                trim($_POST['website'] ?? '') ?: null,
                trim($_POST['city'] ?? '') ?: null,
                trim($_POST['founded_year'] ?? '') ?: null,
                (int)($_POST['founders_count'] ?? 1),
                trim($_POST['ceo_name'] ?? '') ?: null,
                trim($_POST['elevator_pitch'] ?? '') ?: null,
                trim($_POST['problem'] ?? '') ?: null,
                trim($_POST['solution'] ?? '') ?: null,
                isset($_POST['has_tech_team']) ? 1 : 0,
                $_POST['revenue_range'] ?? 'pre-revenue',
                trim($_POST['users_count'] ?? '') ?: null,
                trim($_POST['funding_raised'] ?? '') ?: null,
                trim($_POST['funding_target'] ?? '') ?: null,
                $ft_val ?: null,
                trim($_POST['looking_for'] ?? '') ?: null,
                'startup',
            ]);
            $new_uid = (int)$db->lastInsertId();

            // Generate 6-digit verification code, store hashed with 30-min expiry
            $code    = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expires = date('Y-m-d H:i:s', time() + 1800);
            $db->prepare('UPDATE fm_users SET email_verif_code=?, email_verif_expires=? WHERE id=?')
               ->execute([hash('sha256', $code), $expires, $new_uid]);

            sendVerificationEmail($email, $code);
            auditLog('register', 'user', $new_uid, $email);

            // Pass uid to verify.php via session
            $_SESSION['pending_verify_uid']   = $new_uid;
            $_SESSION['pending_verify_email'] = $email;
            header('Location: verify.php');
            exit;
        }
    }
}

$v = $_POST; // repopulate form on validation error
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Créer un compte &mdash; Startup.TN</title>
<?php include 'auth_style.php'; ?>
</head>
<body>
<div class="wrap wrap-lg">
  <div class="logo-block">
    <div class="logo-icon">&#127481;&#127475;</div>
    <div class="logo-title">Startup<span>.TN</span></div>
    <div class="logo-sub">Plateforme de veille financement pour startups tunisiennes</div>
  </div>

  <?php if ($error): ?><div class="error-msg">&#9888; <?= h($error) ?></div><?php endif; ?>

  <form method="POST" id="regform" novalidate>
    <?= csrfField() ?>
    <input type="hidden" name="submit_final" value="1">

    <!-- 01 Identité -->
    <div class="card">
      <div class="section-label">01 &mdash; Identit&eacute; de la startup</div>
      <div class="grid2">
        <div class="field">
          <label>Nom de la startup <span class="req">*</span></label>
          <input type="text" name="startup_name" placeholder="Ma Startup SRL" required value="<?= h($v['startup_name']??'') ?>">
        </div>
        <div class="field">
          <label>Ann&eacute;e de cr&eacute;ation</label>
          <input type="number" name="founded_year" placeholder="2023" min="2000" max="<?= date('Y') ?>" value="<?= h($v['founded_year']??'') ?>">
        </div>
        <div class="field">
          <label>Site web</label>
          <input type="url" name="website" placeholder="https://mastartup.tn" value="<?= h($v['website']??'') ?>">
        </div>
        <div class="field">
          <label>Ville</label>
          <input type="text" name="city" placeholder="Tunis, Sfax, Sousse..." value="<?= h($v['city']??'') ?>">
        </div>
      </div>
    </div>

    <!-- 02 Connexion -->
    <div class="card">
      <div class="section-label">02 &mdash; Connexion</div>
      <div class="field">
        <label>Email professionnel <span class="req">*</span></label>
        <input type="email" name="email" placeholder="contact@mastartup.tn" required value="<?= h($v['email']??'') ?>">
        <small>Un code de vérification sera envoyé à cette adresse.</small>
      </div>
      <div class="grid2">
        <div class="field">
          <label>Mot de passe <span class="req">*</span></label>
          <input type="password" name="password" id="pw1" placeholder="Min. 8 caract&egrave;res" required>
          <div class="pw-strength"><div class="pw-strength-bar" id="pw-bar"></div></div>
          <div class="pw-hint" id="pw-hint">Min. 8 caractères</div>
        </div>
        <div class="field">
          <label>Confirmer <span class="req">*</span></label>
          <input type="password" name="password2" id="pw2" placeholder="R&eacute;p&eacute;tez" required>
          <div class="pw-hint" id="pw2-hint" style="color:transparent">—</div>
        </div>
      </div>
    </div>

    <!-- 03 Description -->
    <div class="card">
      <div class="section-label">03 &mdash; Description &amp; positionnement</div>
      <div class="field">
        <label>Secteurs <small style="text-transform:none;letter-spacing:0;color:var(--muted)">(s&eacute;lection multiple)</small></label>
        <?php $v_sectors = array_filter((array)($v['sectors'] ?? [])); ?>
        <div class="toggle-group">
          <?php foreach ($sectors_list as $sec):
            $sid_r = 'rs_'.preg_replace('/[^a-z0-9]/','',strtolower($sec));
          ?>
          <div class="toggle-item">
            <input type="checkbox" name="sectors[]" value="<?= h($sec) ?>"
              id="<?= $sid_r ?>" <?= in_array($sec, $v_sectors)?'checked':'' ?>>
            <label for="<?= $sid_r ?>"><?= h($sec) ?></label>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="grid2">
        <div class="field">
          <label>Stade de d&eacute;veloppement</label>
          <select name="stage">
            <?php foreach ($stages as $k=>$lbl): ?>
            <option value="<?= $k ?>" <?= (($v['stage']??'seed')===$k)?'selected':'' ?>><?= h($lbl) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Nombre de fondateurs</label>
          <input type="number" name="founders_count" min="1" max="10" value="<?= h($v['founders_count']??'1') ?>">
        </div>
      </div>
      <div class="field">
        <label>Nom du CEO / Fondateur principal</label>
        <input type="text" name="ceo_name" placeholder="Prénom Nom" value="<?= h($v['ceo_name']??'') ?>">
      </div>
      <div class="field">
        <label>Elevator pitch <span style="color:var(--muted)">(2-3 phrases)</span></label>
        <textarea name="elevator_pitch" placeholder="Décrivez votre startup en 2-3 phrases..."><?= h($v['elevator_pitch']??'') ?></textarea>
      </div>
      <div class="grid2">
        <div class="field">
          <label>Probl&egrave;me r&eacute;solu</label>
          <textarea name="problem" placeholder="Quel problème résolvez-vous ?" style="min-height:60px"><?= h($v['problem']??'') ?></textarea>
        </div>
        <div class="field">
          <label>Solution propos&eacute;e</label>
          <textarea name="solution" placeholder="Comment vous le résolvez ?" style="min-height:60px"><?= h($v['solution']??'') ?></textarea>
        </div>
      </div>
      <label class="check-row">
        <input type="checkbox" name="has_tech_team" value="1" <?= !empty($v['has_tech_team'])?'checked':'' ?>>
        Nous avons une &eacute;quipe technique d&eacute;di&eacute;e
      </label>
    </div>

    <!-- 04 Traction -->
    <div class="card">
      <div class="section-label">04 &mdash; Traction &amp; m&eacute;triques</div>
      <div class="grid2">
        <div class="field">
          <label>Revenus actuels</label>
          <select name="revenue_range">
            <?php foreach ($revenue_ranges as $k=>$lbl): ?>
            <option value="<?= $k ?>" <?= (($v['revenue_range']??'pre-revenue')===$k)?'selected':'' ?>><?= h($lbl) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Clients / Utilisateurs</label>
          <input type="text" name="users_count" placeholder="Ex: 500 utilisateurs, 12 clients B2B" value="<?= h($v['users_count']??'') ?>">
        </div>
      </div>
      <div class="field">
        <label>Financement d&eacute;j&agrave; lev&eacute;</label>
        <input type="text" name="funding_raised" placeholder="Ex: 50 000 EUR en pre-seed, ou Aucun" value="<?= h($v['funding_raised']??'') ?>">
      </div>
    </div>

    <!-- 05 Besoins -->
    <div class="card">
      <div class="section-label">05 &mdash; Besoins &amp; recherche</div>
      <div class="field">
        <label>Montant recherch&eacute;</label>
        <input type="text" name="funding_target" placeholder="Ex: 100 000 – 300 000 EUR" value="<?= h($v['funding_target']??'') ?>">
      </div>
      <div class="field">
        <label>Type de financement recherch&eacute;</label>
        <div class="toggle-group">
          <?php foreach ($funding_types as $ft_item):
            $fid2 = 'reg_ft_'.preg_replace('/[^a-z0-9]/','',strtolower($ft_item));
          ?>
          <div class="toggle-item">
            <input type="checkbox" name="funding_type[]" value="<?= h($ft_item) ?>" id="<?= $fid2 ?>"
              <?= in_array($ft_item,(array)($v['funding_type']??[]))?'checked':'' ?>>
            <label for="<?= $fid2 ?>"><?= h($ft_item) ?></label>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="field">
        <label>Ce que vous cherchez sur cette plateforme</label>
        <textarea name="looking_for" placeholder="Ex: Trouver des accélérateurs, des subventions non dilutives..."><?= h($v['looking_for']??'') ?></textarea>
      </div>
      <p style="font-size:11px;color:var(--muted);margin-bottom:16px">Votre compte sera activ&eacute; apr&egrave;s v&eacute;rification email et validation par l&rsquo;administrateur.</p>
      <button type="submit" class="btn-auth">Créer mon compte &amp; vérifier mon email &rarr;</button>
    </div>

  </form>

  <div class="back-link"><a href="index.php">&larr; D&eacute;j&agrave; un compte ? Se connecter</a></div>
</div>

<?php include 'pw_strength.php'; ?>
</body>
</html>
