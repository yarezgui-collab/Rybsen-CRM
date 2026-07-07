<?php
require_once 'config.php';
requireLogin();
$page_title = 'Mon Profil';

$db  = getDB();
$uid = (int)$_SESSION['fm_user_id'];

// Charger le profil complet depuis la BDD
$stmt = $db->prepare('SELECT * FROM fm_users WHERE id = ? LIMIT 1');
$stmt->execute([$uid]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: logout.php');
    exit;
}

$error   = '';
$success = false;

// ── ACTIONS POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // Modifier les infos du profil
    if ($action === 'update_profile') {
        $name     = trim($_POST['startup_name'] ?? '');
        $website  = trim($_POST['website'] ?? '');
        $city     = trim($_POST['city'] ?? '');
        $year     = (int)($_POST['founded_year'] ?? 0);
        $founders = (int)($_POST['founders_count'] ?? 1);
        $ceo      = trim($_POST['ceo_name'] ?? '');
        $sector   = isset($_POST['sectors']) ? implode(',', array_map('trim', (array)$_POST['sectors'])) : '';
        $stage    = $_POST['stage'] ?? '';
        $pitch    = trim($_POST['elevator_pitch'] ?? '');
        $problem  = trim($_POST['problem'] ?? '');
        $solution = trim($_POST['solution'] ?? '');
        $tech     = isset($_POST['has_tech_team']) ? 1 : 0;
        $rev      = $_POST['revenue_range'] ?? 'pre-revenue';
        $users_c  = trim($_POST['users_count'] ?? '');
        $raised   = trim($_POST['funding_raised'] ?? '');
        $target   = trim($_POST['funding_target'] ?? '');
        $ft       = implode(', ', array_filter((array)($_POST['funding_type'] ?? [])));
        $looking  = trim($_POST['looking_for'] ?? '');

        if (!$name) {
            $error = 'Le nom de la startup est obligatoire.';
        } else {
            $db->prepare('UPDATE fm_users SET
                startup_name=?, website=?, city=?, founded_year=?,
                founders_count=?, ceo_name=?, sector=?, stage=?,
                elevator_pitch=?, problem=?, solution=?, has_tech_team=?,
                revenue_range=?, users_count=?, funding_raised=?,
                funding_target=?, funding_type=?, looking_for=?
                WHERE id=?')
               ->execute([
                   $name, $website ?: null, $city ?: null, $year ?: null,
                   $founders, $ceo ?: null, $sector ?: null, $stage ?: null,
                   $pitch ?: null, $problem ?: null, $solution ?: null, $tech,
                   $rev, $users_c ?: null, $raised ?: null,
                   $target ?: null, $ft ?: null, $looking ?: null,
                   $uid
               ]);

            // Mettre à jour la session avec le nouveau nom
            $_SESSION['fm_name'] = $name;

            auditLog('update_profile', 'user', $uid);
            $success = true;

            // Recharger les données
            $stmt->execute([$uid]);
            $user = $stmt->fetch();
        }
    }

    // Changer le mot de passe
    if ($action === 'change_password') {
        $current  = $_POST['current_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        if (!$current || !$new_pass) {
            $error = 'Veuillez remplir tous les champs.';
        } elseif (!password_verify($current, $user['password'])) {
            $error = 'Mot de passe actuel incorrect.';
        } elseif (strlen($new_pass) < 8) {
            $error = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
        } elseif ($new_pass !== $confirm) {
            $error = 'Les nouveaux mots de passe ne correspondent pas.';
        } else {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $db->prepare('UPDATE fm_users SET password=? WHERE id=?')->execute([$hash, $uid]);
            auditLog('change_password_self', 'user', $uid);
            $success = true;
        }
    }

    // Import rapide du profil via JSON collé
    if ($action === 'import_profile_json') {
        $raw = trim($_POST['profile_json'] ?? '');
        $data = json_decode($raw, true);
        if (!$raw) {
            $error = 'Collez un JSON avant de cliquer sur Importer.';
        } elseif (!is_array($data)) {
            $error = 'Le JSON collé est invalide. Vérifiez le format et réessayez.';
        } else {
            // Champs autorisés uniquement - une startup ne peut modifier que SON PROPRE profil
            $allowed = ['startup_name','website','city','founded_year','founders_count','ceo_name',
                        'sector','stage','elevator_pitch','problem','solution','has_tech_team',
                        'revenue_range','users_count','funding_raised','funding_target',
                        'funding_type','looking_for'];
            $set_parts = [];
            $set_values = [];
            foreach ($allowed as $field) {
                if (array_key_exists($field, $data)) {
                    $set_parts[] = "$field = ?";
                    $val = $data[$field];
                    if ($field === 'sector' && is_array($val)) $val = implode(',', $val);
                    if ($field === 'funding_type' && is_array($val)) $val = implode(', ', $val);
                    if ($field === 'has_tech_team') $val = !empty($val) ? 1 : 0;
                    $set_values[] = is_array($val) ? implode(',', $val) : $val;
                }
            }
            if (empty($set_parts)) {
                $error = 'Aucun champ reconnu dans le JSON. Champs acceptés : ' . implode(', ', $allowed);
            } else {
                $set_values[] = $uid;
                $db->prepare('UPDATE fm_users SET ' . implode(', ', $set_parts) . ' WHERE id = ?')
                   ->execute($set_values);
                if (isset($data['startup_name'])) $_SESSION['fm_name'] = $data['startup_name'];
                auditLog('import_profile_json', 'user', $uid);
                $success = true;
                $stmt->execute([$uid]);
                $user = $stmt->fetch();
            }
        }
    }
}

// Stats de la startup
$stats = $db->prepare('SELECT
    COUNT(*) as total_sub,
    SUM(status="approved") as approved,
    SUM(status="pending") as pending,
    SUM(status="rejected") as rejected
    FROM fm_submissions WHERE user_id=?');
$stats->execute([$uid]);
$stats = $stats->fetch();

$sectors_list = [
    'IA / AI','Fintech','Cleantech','Agritech','Healthtech','Edtech',
    'Watertech','Greentech','Deep Tech','Logistique','E-commerce',
    'Cybersécurité','Life Sciences','Impact Social','Tourisme Tech','SaaS / B2B','Autre'
];
$stages_list = [
    'idee'          => 'Idée / Pré-création',
    'bootstrapping' => 'Bootstrapping',
    'pre-seed'      => 'Pre-Seed',
    'seed'          => 'Seed',
    'series-a'      => 'Série A',
    'series-b'      => 'Série B',
    'series-c'      => 'Série C',
    'growth'        => 'Growth / Late Stage',
    'pre-ipo'       => 'Pré-IPO',
];
$revenue_ranges = [
    'pre-revenue'=>'Pré-revenus (0 EUR)',
    '0-10k'=>'0 – 10K EUR',
    '10k-50k'=>'10K – 50K EUR',
    '50k-200k'=>'50K – 200K EUR',
    '200k-1m'=>'200K – 1M EUR',
    '1m+'=>'+ 1M EUR'
];
$funding_types = ['Investissement equity','Subvention / Grant','Prêt','Convertible note','Crowdfunding','Bootstrap','Tout type'];
$sel_ft = array_filter(array_map('trim', explode(',', $user['funding_type'] ?? '')));

include 'header.php';
?>

<!-- EN-TÊTE PROFIL -->
<div style="background:linear-gradient(135deg,#0a0f1e,#0f1a30);border:1px solid var(--border);border-radius:12px;padding:24px 28px;margin-bottom:20px;display:flex;align-items:center;gap:20px;flex-wrap:wrap">
  <div style="width:60px;height:60px;background:linear-gradient(135deg,var(--accent),var(--accent2));border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:26px;flex-shrink:0">
    <?= mb_substr($user['startup_name'], 0, 1) ?>
  </div>
  <div style="flex:1;min-width:0">
    <div style="font-size:22px;font-weight:700;color:#fff;margin-bottom:3px"><?= h($user['startup_name']) ?></div>
    <div style="font-size:13px;color:var(--muted)"><?= h($user['email']) ?> &mdash; <?= h($user['city'] ?: 'Tunisie') ?><?= $user['founded_year'] ? ' &mdash; Fondée en ' . $user['founded_year'] : '' ?></div>
    <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap">
      <?php
      $disp_sectors = array_filter(array_map('trim', explode(',', $user['sector'] ?? '')));
      foreach ($disp_sectors as $ds): ?>
        <span class="badge badge-accelerator"><?= h($ds) ?></span>
      <?php endforeach; ?>
      <?php if ($user['stage'] && isset($stages_list[$user['stage']])): ?><span class="badge badge-fund"><?= h($stages_list[$user['stage']]) ?></span><?php endif; ?>
      <span class="badge <?= $user['is_active'] ? 'badge-active' : 'badge-pending' ?>"><?= $user['is_active'] ? 'Compte actif' : 'En attente' ?></span>
    </div>
  </div>
  <!-- Stats rapides -->
  <div style="display:flex;gap:12px">
    <div style="text-align:center;background:rgba(0,0,0,.3);border:1px solid var(--border);border-radius:8px;padding:10px 16px">
      <div style="font-family:var(--mono);font-size:22px;font-weight:700;color:var(--accent)"><?= $stats['total_sub'] ?></div>
      <div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:1px">Soumissions</div>
    </div>
    <div style="text-align:center;background:rgba(0,0,0,.3);border:1px solid var(--border);border-radius:8px;padding:10px 16px">
      <div style="font-family:var(--mono);font-size:22px;font-weight:700;color:var(--accent3)"><?= $stats['approved'] ?></div>
      <div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:1px">Approuvées</div>
    </div>
    <div style="text-align:center;background:rgba(0,0,0,.3);border:1px solid var(--border);border-radius:8px;padding:10px 16px">
      <div style="font-family:var(--mono);font-size:22px;font-weight:700;color:var(--accent4)"><?= $stats['pending'] ?></div>
      <div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:1px">En attente</div>
    </div>
  </div>
</div>

<?php if ($success): ?>
  <div class="alert alert-success" style="margin-bottom:16px">&#10003; Modifications enregistrées avec succès.</div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert alert-error" style="margin-bottom:16px">&#9888; <?= h($error) ?></div>
<?php endif; ?>

<!-- TABS -->
<div style="display:flex;gap:4px;margin-bottom:20px;border-bottom:1px solid var(--border)">
  <button onclick="showTab('info')" id="tab-info" class="tab-btn active">&#128101; Informations</button>
  <button onclick="showTab('importjson')" id="tab-importjson" class="tab-btn">&#9889; Import rapide</button>
  <button onclick="showTab('security')" id="tab-security" class="tab-btn">&#128274; Sécurité</button>
</div>

<style>
.tab-btn{padding:10px 18px;border-radius:8px 8px 0 0;font-size:13px;font-weight:500;cursor:pointer;border:1px solid transparent;border-bottom:none;margin-bottom:-1px;background:none;color:var(--muted);transition:all .15s;font-family:var(--font-sans,'Segoe UI',sans-serif)}
.tab-btn.active{background:var(--card);border-color:var(--border);border-bottom-color:var(--card);color:#fff}
.tab-content{display:none}
.tab-content.active{display:block}
</style>

<!-- TAB INFORMATIONS -->
<div id="tab-info-content" class="tab-content active">
<form method="POST">
  <input type="hidden" name="action" value="update_profile">
  <?= csrfField() ?>

  <!-- Section 1: Identité -->
  <div class="card" style="margin-bottom:16px">
    <div style="font-size:11px;color:var(--accent);font-family:var(--mono);text-transform:uppercase;letter-spacing:2px;margin-bottom:16px;padding-bottom:8px;border-bottom:1px solid var(--border)">01 — Identité de la startup</div>
    <div class="form-grid">
      <div class="field">
        <label>Nom de la startup *</label>
        <input type="text" name="startup_name" value="<?= h($user['startup_name']) ?>" required>
      </div>
      <div class="field">
        <label>Année de création</label>
        <input type="number" name="founded_year" min="1990" max="2026" value="<?= h($user['founded_year'] ?? '') ?>">
      </div>
      <div class="field">
        <label>Site web</label>
        <input type="url" name="website" placeholder="https://mastartup.tn" value="<?= h($user['website'] ?? '') ?>">
      </div>
      <div class="field">
        <label>Ville</label>
        <input type="text" name="city" placeholder="Tunis, Sfax..." value="<?= h($user['city'] ?? '') ?>">
      </div>
      <div class="field">
        <label>Nombre de fondateurs</label>
        <input type="number" name="founders_count" min="1" max="20" value="<?= h($user['founders_count'] ?? 1) ?>">
      </div>
      <div class="field">
        <label>Nom du CEO / Fondateur principal</label>
        <input type="text" name="ceo_name" placeholder="Prénom Nom" value="<?= h($user['ceo_name'] ?? '') ?>">
      </div>
    </div>
  </div>

  <!-- Section 2: Positionnement -->
  <div class="card" style="margin-bottom:16px">
    <div style="font-size:11px;color:var(--accent);font-family:var(--mono);text-transform:uppercase;letter-spacing:2px;margin-bottom:16px;padding-bottom:8px;border-bottom:1px solid var(--border)">02 — Secteur &amp; stade</div>
    <div class="form-grid">
      <div class="field">
        <label>Secteurs d&rsquo;activit&eacute; <small style="font-size:11px;color:var(--muted);text-transform:none;letter-spacing:0">(s&eacute;lection multiple)</small></label>
        <?php
        $user_sectors = array_map('trim', explode(',', $user['sector'] ?? ''));
        ?>
        <div class="toggle-group" id="sector-pills-profile">
          <?php foreach ($sectors_list as $sec):
            $sec_id = 'ps_' . preg_replace('/[^a-z0-9]/', '', strtolower($sec));
            $is_sel = in_array($sec, $user_sectors);
          ?>
          <div class="toggle-item">
            <input type="checkbox" name="sectors[]" value="<?= h($sec) ?>"
              id="<?= $sec_id ?>" <?= $is_sel ? 'checked' : '' ?>>
            <label for="<?= $sec_id ?>"><?= h($sec) ?></label>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="field">
        <label>Stade de levée de fonds</label>
        <select name="stage" style="width:100%;padding:10px 14px;background:var(--surface);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;outline:none;-webkit-appearance:none">
          <?php foreach ($stages_list as $k=>$v): ?>
          <option value="<?= $k ?>" <?= $user['stage']===$k?'selected':'' ?>><?= h($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="field">
      <label>Elevator pitch <span style="color:var(--muted)">(2-3 phrases)</span></label>
      <textarea name="elevator_pitch" placeholder="Décrivez votre startup en 2-3 phrases..."><?= h($user['elevator_pitch'] ?? '') ?></textarea>
    </div>
    <div class="form-grid">
      <div class="field">
        <label>Problème résolu</label>
        <textarea name="problem" style="min-height:70px" placeholder="Quel problème résolvez-vous ?"><?= h($user['problem'] ?? '') ?></textarea>
      </div>
      <div class="field">
        <label>Solution proposée</label>
        <textarea name="solution" style="min-height:70px" placeholder="Comment vous le résolvez ?"><?= h($user['solution'] ?? '') ?></textarea>
      </div>
    </div>
    <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--label);cursor:pointer">
      <input type="checkbox" name="has_tech_team" value="1" <?= $user['has_tech_team']?'checked':'' ?> style="accent-color:var(--accent);width:16px;height:16px">
      Nous avons une équipe technique dédiée
    </label>
  </div>

  <!-- Section 3: Traction & Besoins -->
  <div class="card" style="margin-bottom:16px">
    <div style="font-size:11px;color:var(--accent);font-family:var(--mono);text-transform:uppercase;letter-spacing:2px;margin-bottom:16px;padding-bottom:8px;border-bottom:1px solid var(--border)">03 — Traction &amp; besoins de financement</div>
    <div class="form-grid">
      <div class="field">
        <label>Revenus actuels</label>
        <select name="revenue_range" style="width:100%;padding:10px 14px;background:var(--surface);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;outline:none;-webkit-appearance:none">
          <?php foreach ($revenue_ranges as $k=>$v): ?>
          <option value="<?= $k ?>" <?= $user['revenue_range']===$k?'selected':'' ?>><?= h($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>Clients / Utilisateurs</label>
        <input type="text" name="users_count" placeholder="Ex: 500 utilisateurs, 12 clients B2B" value="<?= h($user['users_count'] ?? '') ?>">
      </div>
      <div class="field">
        <label>Financement déjà levé</label>
        <input type="text" name="funding_raised" placeholder="Ex: 50 000 EUR, Aucun..." value="<?= h($user['funding_raised'] ?? '') ?>">
      </div>
      <div class="field">
        <label>Montant recherché</label>
        <input type="text" name="funding_target" placeholder="Ex: 300 000 EUR" value="<?= h($user['funding_target'] ?? '') ?>">
      </div>
    </div>
    <div class="field">
      <label>Type de financement recherché</label>
      <div class="toggle-group">
        <?php foreach ($funding_types as $ft): $fid = 'ft_'.preg_replace('/[^a-z0-9]/','',$ft); ?>
        <div class="toggle-item">
          <input type="checkbox" name="funding_type[]" value="<?= h($ft) ?>" id="<?= $fid ?>" <?= in_array($ft,$sel_ft)?'checked':'' ?>>
          <label for="<?= $fid ?>"><?= h($ft) ?></label>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="field">
      <label>Ce que vous cherchez sur cette plateforme</label>
      <textarea name="looking_for" placeholder="Ex: Trouver des accélérateurs, subventions non dilutives..."><?= h($user['looking_for'] ?? '') ?></textarea>
    </div>
  </div>

  <button type="submit" class="btn btn-primary" style="padding:12px 28px;font-size:14px">&#10003; Enregistrer les modifications</button>
</form>
</div>

<!-- TAB IMPORT RAPIDE -->
<div id="tab-importjson-content" class="tab-content">
  <div class="card" style="max-width:680px;margin-bottom:16px">
    <div style="font-size:11px;color:var(--accent);font-family:var(--mono);text-transform:uppercase;letter-spacing:2px;margin-bottom:16px;padding-bottom:8px;border-bottom:1px solid var(--border)">Import rapide de votre profil</div>
    <p style="font-size:13.5px;color:var(--text-sec);line-height:1.7;margin-bottom:14px">
      Vous pouvez remplir tout votre profil en une seule fois en collant un JSON ci-dessous, au lieu de remplir le formulaire champ par champ. Demandez à Claude de le préparer pour vous à partir de la description de votre startup.
    </p>
    <p style="font-size:12px;color:var(--muted);margin-bottom:14px">
      Champs acceptés : <code style="background:var(--surface);padding:2px 5px;border-radius:4px">startup_name, website, city, founded_year, founders_count, ceo_name, sector, stage, elevator_pitch, problem, solution, has_tech_team, revenue_range, users_count, funding_raised, funding_target, funding_type, looking_for</code>. Seuls les champs présents dans le JSON sont mis à jour ; les autres restent inchangés.
    </p>
    <details style="margin-bottom:16px">
      <summary style="cursor:pointer;font-size:12.5px;color:var(--accent)">Voir un exemple de JSON</summary>
      <pre style="background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:12px;font-size:11.5px;color:var(--muted);overflow-x:auto;margin-top:8px">{
  "startup_name": "MaStartup",
  "city": "Tunis",
  "sector": "Fintech,IA / AI",
  "stage": "seed",
  "elevator_pitch": "Description courte en 2-3 phrases.",
  "problem": "Le problème résolu.",
  "solution": "La solution apportée.",
  "has_tech_team": true,
  "funding_target": "200 000 EUR",
  "funding_type": ["Investissement equity", "Subvention / Grant"]
}</pre>
    </details>
    <form method="POST">
      <input type="hidden" name="action" value="import_profile_json">
      <?= csrfField() ?>
      <div class="field">
        <textarea name="profile_json" rows="10" placeholder='{ "startup_name": "...", "sector": "...", ... }'
          style="width:100%;font-family:var(--mono);font-size:12.5px;background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:12px;color:var(--text-sec);resize:vertical"></textarea>
      </div>
      <button type="submit" class="btn btn-primary"
        onclick="return document.querySelector('[name=profile_json]').value.trim() !== '' && confirm('Mettre à jour votre profil avec ce JSON ?')">
        &#9889; Importer mon profil
      </button>
    </form>
  </div>
</div>

<!-- TAB SÉCURITÉ -->
<div id="tab-security-content" class="tab-content">
  <div class="card" style="max-width:480px">
    <div style="font-size:11px;color:var(--accent);font-family:var(--mono);text-transform:uppercase;letter-spacing:2px;margin-bottom:20px;padding-bottom:8px;border-bottom:1px solid var(--border)">Changer le mot de passe</div>
    <form method="POST">
      <input type="hidden" name="action" value="change_password">
      <?= csrfField() ?>
      <div class="field">
        <label>Mot de passe actuel</label>
        <input type="password" name="current_password" placeholder="••••••••" required>
      </div>
      <div class="field">
        <label>Nouveau mot de passe <span style="color:var(--muted)">(min 8 caractères)</span></label>
        <input type="password" name="new_password" placeholder="••••••••" required minlength="8">
      </div>
      <div class="field">
        <label>Confirmer le nouveau mot de passe</label>
        <input type="password" name="confirm_password" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn btn-primary" style="padding:12px 24px">Changer le mot de passe</button>
    </form>
  </div>

  <!-- Infos compte en lecture seule -->
  <div class="card" style="max-width:480px;margin-top:16px">
    <div style="font-size:11px;color:var(--accent);font-family:var(--mono);text-transform:uppercase;letter-spacing:2px;margin-bottom:16px;padding-bottom:8px;border-bottom:1px solid var(--border)">Informations de compte</div>
    <div style="display:grid;gap:10px">
      <div style="background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:12px">
        <div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:1px;font-family:var(--mono);margin-bottom:3px">Identifiant de connexion (email)</div>
        <div style="font-size:14px;color:var(--text);font-weight:500"><?= h($user['email']) ?></div>
      </div>
      <div style="background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:12px">
        <div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:1px;font-family:var(--mono);margin-bottom:3px">Rôle</div>
        <div style="font-size:14px;color:var(--text);font-weight:500"><?= ucfirst($user['role']) ?></div>
      </div>
      <div style="background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:12px">
        <div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:1px;font-family:var(--mono);margin-bottom:3px">Dernière connexion</div>
        <div style="font-size:14px;color:var(--text);font-weight:500"><?= $user['last_login'] ? date('d/m/Y à H:i', strtotime($user['last_login'])) : 'Première connexion' ?></div>
      </div>
      <div style="background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:12px">
        <div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:1px;font-family:var(--mono);margin-bottom:3px">Compte créé le</div>
        <div style="font-size:14px;color:var(--text);font-weight:500"><?= date('d/m/Y', strtotime($user['created_at'])) ?></div>
      </div>
    </div>
  </div>
</div>

<script>
function showTab(name) {
  document.querySelectorAll('.tab-content').forEach(function(el){ el.classList.remove('active'); });
  document.querySelectorAll('.tab-btn').forEach(function(el){ el.classList.remove('active'); });
  document.getElementById('tab-'+name+'-content').classList.add('active');
  document.getElementById('tab-'+name).classList.add('active');
}
</script>

<?php include 'footer.php'; ?>
