<?php
require_once 'config.php';
if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

$error = '';
$success = false;
$step = (int)($_POST['step'] ?? 1);

$sectors_list = [
  'IA / AI','Fintech','Cleantech','Agritech','Healthtech','Edtech',
  'Watertech','Greentech','Deep Tech','Logistique','E-commerce',
  'Cybersécurité','Life Sciences','Impact Social','Tourisme Tech','SaaS / B2B','Autre'
];
$stages = [
  'idee'        => '🌱 Idée / Pré-création (pas encore de produit)',
  'bootstrapping'=> '💪 Bootstrapping (auto-financé, revenus propres)',
  'pre-seed'    => '🚀 Pre-Seed (premiers angels / F&F, < 500K EUR)',
  'seed'        => '🌿 Seed (fonds seed / accélérateurs, 500K – 3M EUR)',
  'series-a'    => '📈 Série A (VCs institutionnels, 3M – 15M EUR)',
  'series-b'    => '🏗️ Série B (expansion, 15M – 50M EUR)',
  'series-c'    => '🌍 Série C (scale international, 50M – 150M EUR)',
  'growth'      => '⚡ Growth / Late Stage (+ 150M EUR)',
  'pre-ipo'     => '🏛️ Pré-IPO / Exit',
];
$revenue_ranges = ['pre-revenue'=>'Pré-revenus','0-10k'=>'0 – 10K EUR','10k-50k'=>'10K – 50K EUR','50k-200k'=>'50K – 200K EUR','200k-1m'=>'200K – 1M EUR','1m+'=>'+ 1M EUR'];
$funding_types = ['Investissement equity','Subvention / Grant','Prêt','Convertible note','Crowdfunding','Bootstrap','Tout type'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_final'])) {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2= $_POST['password2'] ?? '';
    $name     = trim($_POST['startup_name'] ?? '');

    if (!$email || !$password || !$name) { // sector est optionnel (multi)
        $error = 'Veuillez remplir les champs obligatoires (nom, email, mot de passe).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email invalide.';
    } elseif (strlen($password) < 8) {
        $error = 'Mot de passe minimum 8 caractères.';
    } elseif ($password !== $password2) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        $db = getDB();
        $chk = $db->prepare('SELECT id FROM fm_users WHERE email=? LIMIT 1');
        $chk->execute([$email]);
        if ($chk->fetch()) {
            $error = 'Cet email est déjà utilisé.';
        } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $ft = implode(', ', array_filter((array)($_POST['funding_type'] ?? [])));
                $stmt = $db->prepare('INSERT INTO fm_users
                  (email,password,startup_name,sector,stage,website,city,founded_year,founders_count,ceo_name,
                   elevator_pitch,problem,solution,has_tech_team,revenue_range,users_count,
                   funding_raised,funding_target,funding_type,looking_for,role,is_active)
                  VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0)');
                $stmt->execute([
                    $email, $hash,
                    $name,
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
                    $ft ?: null,
                    trim($_POST['looking_for'] ?? '') ?: null,
                    'startup'
                ]);
                auditLog('register', 'user', (int)$db->lastInsertId(), $email);
            $success = true;
        }
    }
}

$v = $_POST; // valeurs précédentes

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cr&eacute;er un compte &mdash; Startup Tunisia</title>
<style>
:root{--bg:#0a0f1e;--surface:#111827;--card:#161f35;--border:#1e2d4a;--accent:#00d4ff;--accent2:#7c3aed;--text:#e2e8f0;--muted:#64748b;--label:#94a3b8;--font:'Segoe UI',system-ui,sans-serif;--mono:'Courier New',monospace}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:var(--font);background:var(--bg);color:var(--text);min-height:100vh;padding:20px}
.wrap{max-width:680px;margin:0 auto}
.logo-block{text-align:center;margin-bottom:24px;padding-top:12px}
.logo-title{font-family:var(--mono);font-size:22px;font-weight:700;color:var(--accent)}
.logo-title span{color:var(--text)}
.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:28px;margin-bottom:16px}
.section-label{font-size:11px;color:var(--accent);text-transform:uppercase;letter-spacing:2px;font-family:var(--mono);margin-bottom:16px;padding-bottom:8px;border-bottom:1px solid var(--border)}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.field{margin-bottom:14px}
.field label{display:block;font-size:11px;color:var(--label);text-transform:uppercase;letter-spacing:1px;margin-bottom:5px;font-family:var(--mono)}
.req{color:var(--accent)}
.field input,.field select,.field textarea{width:100%;padding:10px 13px;background:var(--surface);border:1px solid var(--border);border-radius:8px;color:var(--text);font-family:var(--font);font-size:13px;outline:none;transition:border-color .2s}
.field textarea{resize:vertical;min-height:72px}
.field select{-webkit-appearance:none}
.field input:focus,.field select:focus,.field textarea:focus{border-color:var(--accent)}
.field input::placeholder,.field textarea::placeholder{color:var(--muted)}
.field small{display:block;font-size:11px;color:var(--muted);margin-top:4px}



.check-row{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--label);margin-bottom:8px}
.check-row input{accent-color:var(--accent);width:16px;height:16px}
.ft-grid{display:flex;flex-wrap:wrap;gap:6px;margin-top:4px}
.ft-btn{padding:5px 11px;background:var(--surface);border:1px solid var(--border);border-radius:20px;font-size:11px;color:var(--muted);cursor:pointer;-webkit-appearance:none;transition:all .15s}
.ft-btn.selected{background:rgba(124,58,237,.15);border-color:var(--accent2);color:#a78bfa}
.btn-submit{width:100%;padding:13px;background:var(--accent);color:#000;border:none;border-radius:8px;font-family:var(--font);font-size:15px;font-weight:700;cursor:pointer;transition:opacity .15s;margin-top:8px}
.btn-submit:hover{opacity:.85}
.error-msg{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);color:#f87171;padding:12px 14px;border-radius:8px;font-size:13px;margin-bottom:16px}
.success-box{background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.25);color:#34d399;padding:24px;border-radius:12px;text-align:center;font-size:14px;line-height:1.7}
.back-link{text-align:center;margin-top:16px;font-size:13px;color:var(--muted)}
.back-link a{color:var(--accent);text-decoration:none}
@media(max-width:560px){.grid2{grid-template-columns:1fr}}
.toggle-group{display:flex;flex-wrap:wrap;gap:8px;margin-top:6px}
.toggle-item{position:relative}
.toggle-item input[type="checkbox"]{position:absolute;opacity:0;width:0;height:0}
.toggle-item label{display:flex;align-items:center;gap:6px;padding:8px 14px;background:var(--surface);border:1.5px solid var(--border);border-radius:20px;font-size:13px;font-weight:500;color:#e2e8f0;cursor:pointer;transition:all .15s;min-height:38px;white-space:nowrap;user-select:none;-webkit-user-select:none;text-transform:none;letter-spacing:0}
.toggle-item label::before{content:'';width:14px;height:14px;border:1.5px solid #344060;border-radius:3px;background:var(--bg);flex-shrink:0;transition:all .15s}
.toggle-item input:checked+label{background:rgba(0,212,255,.1);border-color:var(--accent);color:var(--accent)}
.toggle-item input:checked+label::before{background:var(--accent);border-color:var(--accent);background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 10 8' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 4L3.5 6.5L9 1' stroke='%230d1117' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:center;background-size:10px}
.toggle-item label:hover{border-color:var(--accent);color:var(--accent)}
</style>
</head>
<body>
<div class="wrap">
  <div class="logo-block">
    <div class="logo-title">Startup<span>.TN</span> &#127481;&#127475;</div>
    <p style="font-size:13px;color:var(--muted);margin-top:6px">Plateforme de veille financement pour startups tunisiennes</p>
  </div>

  <?php if ($success): ?>
    <div class="success-box">
      &#10003; <strong>Compte créé avec succès !</strong><br><br>
      Un administrateur va valider votre profil avant activation.<br>
      Vous serez notifié(e) dès que votre compte est actif.<br><br>
      <a href="index.php" style="color:var(--accent)">&larr; Retour à la connexion</a>
    </div>
  <?php else: ?>

  <?php if ($error): ?><div class="error-msg">&#9888; <?= h($error) ?></div><?php endif; ?>

  <form method="POST" id="regform">
    <input type="hidden" name="submit_final" value="1">

    <!-- SECTION 1 : Identité -->
    <div class="card">
      <div class="section-label">01 &mdash; Identit&eacute; de la startup</div>
      <div class="grid2">
        <div class="field">
          <label>Nom de la startup <span class="req">*</span></label>
          <input type="text" name="startup_name" placeholder="Ma Startup SRL" required value="<?= h($v['startup_name']??'') ?>">
        </div>
        <div class="field">
          <label>Ann&eacute;e de cr&eacute;ation</label>
          <input type="number" name="founded_year" placeholder="2023" min="2000" max="2026" value="<?= h($v['founded_year']??'') ?>">
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

    <!-- SECTION 2 : Compte -->
    <div class="card">
      <div class="section-label">02 &mdash; Connexion</div>
      <div class="field">
        <label>Email professionnel <span class="req">*</span></label>
        <input type="email" name="email" placeholder="contact@mastartup.tn" required value="<?= h($v['email']??'') ?>">
      </div>
      <div class="grid2">
        <div class="field">
          <label>Mot de passe <span class="req">*</span></label>
          <input type="password" name="password" placeholder="Min. 8 caract&egrave;res" required>
        </div>
        <div class="field">
          <label>Confirmer <span class="req">*</span></label>
          <input type="password" name="password2" placeholder="R&eacute;p&eacute;tez" required>
        </div>
      </div>
    </div>

    <!-- SECTION 3 : Description -->
    <div class="card">
      <div class="section-label">03 &mdash; Description &amp; positionnement</div>
      <div class="field">
        <label>Secteurs <span class="req">*</span> <small style="text-transform:none;letter-spacing:0;color:var(--muted)">(sélection multiple)</small></label>
        <?php $v_sectors = array_filter((array)($v['sectors'] ?? ($v['sector'] ? explode(',', $v['sector']) : []))); ?>
        <div class="toggle-group">
          <?php foreach ($sectors_list as $sec):
            $sid = 'rs_'.preg_replace('/[^a-z0-9]/','',strtolower($sec));
          ?>
          <div class="toggle-item">
            <input type="checkbox" name="sectors[]" value="<?= h($sec) ?>"
              id="<?= $sid ?>" <?= in_array($sec, $v_sectors)?'checked':'' ?>>
            <label for="<?= $sid ?>"><?= h($sec) ?></label>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="grid2">
        <div class="field">
          <label>Stade de d&eacute;veloppement</label>
          <select name="stage">
            <?php foreach ($stages as $k=>$lbl): ?>
            <option value="<?= $k ?>" <?= (($v['stage']??'seed')===$k)?'selected':'' ?>><?= $lbl ?></option>
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
        <label>Elevator pitch <span style="color:var(--muted)">(2-3 phrases max)</span></label>
        <textarea name="elevator_pitch" placeholder="Décrivez votre startup en 2-3 phrases : ce que vous faites, pour qui, et pourquoi c'est unique."><?= h($v['elevator_pitch']??'') ?></textarea>
      </div>
      <div class="field">
        <label>Probl&egrave;me r&eacute;solu</label>
        <textarea name="problem" placeholder="Quel problème concret résolvez-vous ?" style="min-height:60px"><?= h($v['problem']??'') ?></textarea>
      </div>
      <div class="field">
        <label>Solution propos&eacute;e</label>
        <textarea name="solution" placeholder="Comment votre produit/service résout ce problème ?" style="min-height:60px"><?= h($v['solution']??'') ?></textarea>
      </div>
      <div class="check-row">
        <input type="checkbox" name="has_tech_team" id="tech_team" value="1" <?= !empty($v['has_tech_team'])?'checked':'' ?>>
        <label for="tech_team">Nous avons une &eacute;quipe technique d&eacute;di&eacute;e</label>
      </div>
    </div>

    <!-- SECTION 4 : Traction -->
    <div class="card">
      <div class="section-label">04 &mdash; Traction &amp; m&eacute;triques</div>
      <div class="grid2">
        <div class="field">
          <label>Revenus actuels</label>
          <select name="revenue_range">
            <?php foreach ($revenue_ranges as $k=>$lbl): ?>
            <option value="<?= $k ?>" <?= (($v['revenue_range']??'pre-revenue')===$k)?'selected':'' ?>><?= $lbl ?></option>
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

    <!-- SECTION 5 : Besoins -->
    <div class="card">
      <div class="section-label">05 &mdash; Besoins &amp; recherche</div>
      <div class="field">
        <label>Montant recherch&eacute;</label>
        <input type="text" name="funding_target" placeholder="Ex: 100 000 – 300 000 EUR" value="<?= h($v['funding_target']??'') ?>">
      </div>
      <div class="field">
        <label>Type de financement recherch&eacute;</label>
        <div class="toggle-group" id="ft-grid">
          <?php foreach ($funding_types as $ft): $fid2 = 'reg_ft_'.preg_replace('/[^a-z0-9]/','',$ft); ?>
          <div class="toggle-item">
            <input type="checkbox" name="funding_type[]" value="<?= h($ft) ?>" id="<?= $fid2 ?>" <?= in_array($ft,(array)($v['funding_type']??[]))?'checked':'' ?>>
            <label for="<?= $fid2 ?>"><?= h($ft) ?></label>
          </div>
          <?php endforeach; ?>
        </div>
        <?php foreach ($sel_ft as $ft): ?>
          <input type="hidden" name="funding_type[]" value="<?= h($ft) ?>" class="ft-hidden">
        <?php endforeach; ?>
      </div>
      <div class="field">
        <label>Ce que vous cherchez sur cette plateforme</label>
        <textarea name="looking_for" placeholder="Ex: Trouver des accélérateurs adaptés à notre secteur, identifier des subventions non dilutives, trouver des investisseurs seed..."><?= h($v['looking_for']??'') ?></textarea>
      </div>
      <p style="font-size:11px;color:var(--muted);margin-bottom:16px">* Votre compte sera activé après validation par l'administrateur.</p>
      <button type="submit" class="btn-submit">Créer mon compte startup &rarr;</button>
    </div>

  </form>
  <?php endif; ?>

  <div class="back-link"><a href="index.php">&larr; D&eacute;j&agrave; un compte ? Se connecter</a></div>
</div>

</body>
