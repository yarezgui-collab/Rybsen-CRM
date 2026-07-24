<?php
require_once 'config.php';
require_once 'mailer.php';
requireLogin();
requireAdmin();
$page_title = 'Administration';

$db = getDB();

// Re-vérifier le rôle en BDD (la session peut être obsolète si l'admin a été rétrogradé)
$role_chk = $db->prepare('SELECT role, is_active FROM fm_users WHERE id=? LIMIT 1');
$role_chk->execute([(int)$_SESSION['fm_user_id']]);
$role_row = $role_chk->fetch();
if (!$role_row || $role_row['role'] !== 'admin' || !(int)$role_row['is_active']) {
    session_unset();
    session_destroy();
    header('Location: index.php?msg=session_expired');
    exit;
}
$tab = $_GET['tab'] ?? 'submissions';
$msg = $_GET['msg'] ?? '';

// ── ACTIONS POST ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // Valider / rejeter soumission
    if ($action === 'review_submission') {
        $sid    = (int)($_POST['sub_id'] ?? 0);
        $status = $_POST['status'] === 'approved' ? 'approved' : 'rejected';
        $db->prepare('UPDATE fm_submissions SET status=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?')
           ->execute([$status, $_SESSION['fm_user_id'], $sid]);

        // Si approuvé → créer programme
        if ($status === 'approved') {
            $sub = $db->prepare('SELECT * FROM fm_submissions WHERE id=?');
            $sub->execute([$sid]);
            $s = $sub->fetch();
            if ($s) {
                // Si la deadline soumise est une date ISO (YYYY-MM-DD), en profiter
                // pour alimenter deadline_date + deadline_type (calcul auto urgence)
                $dl_date = null;
                $dl_text = $s['deadline'] ?: 'Rolling';
                if ($s['deadline'] && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($s['deadline']))) {
                    $dl_date = trim($s['deadline']);
                    $dl_text = date('d/m/Y', strtotime($dl_date));
                }
                $db->prepare('INSERT INTO fm_programs (name,organisation,type,badge,amount,geo,deadline,deadline_date,deadline_type,sectors,description,link,status,submitted_by,validated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                   ->execute([
                       $s['name'], $s['organisation'], $s['type'],
                       ucfirst($s['type']), $s['amount'], $s['geo'],
                       $dl_text, $dl_date,
                       $dl_date ? calcDeadlineType($dl_date) : 'open',
                       $s['sectors'], $s['description'],
                       $s['url'], 'active', $s['user_id'], $_SESSION['fm_user_id']
                   ]);
            }
        }
        auditLog('review_submission', 'submission', $sid, $status);

        // Notify the startup by email (fetch submission if not already loaded)
        if (!isset($s) || !$s) {
            $sub2 = $db->prepare('SELECT * FROM fm_submissions WHERE id=? LIMIT 1');
            $sub2->execute([$sid]);
            $s = $sub2->fetch();
        }
        if ($s) {
            $owner = $db->prepare('SELECT email, startup_name FROM fm_users WHERE id=? LIMIT 1');
            $owner->execute([$s['user_id']]);
            $owner = $owner->fetch();
            if ($owner) {
                stn_send_submission_result($owner['email'], $owner['startup_name'], $s['name'], $status === 'approved');
            }
        }

        header('Location: admin.php?tab=submissions&msg=' . ($status === 'approved' ? 'approved' : 'rejected'));
        exit;
    }

    // Activer / désactiver utilisateur
    if ($action === 'toggle_user') {
        $uid    = (int)($_POST['user_id'] ?? 0);
        $active = (int)($_POST['is_active'] ?? 0);
        $db->prepare('UPDATE fm_users SET is_active=? WHERE id=? AND role != "admin"')->execute([$active, $uid]);
        auditLog('toggle_user', 'user', $uid, $active ? 'activated' : 'deactivated');
        header('Location: admin.php?tab=users&msg=user_updated');
        exit;
    }

    // Créer un nouvel utilisateur
    if ($action === 'create_user') {
        $email  = trim($_POST['new_email'] ?? '');
        $name   = trim($_POST['new_name'] ?? '');
        $pass   = $_POST['new_password'] ?? '';
        $role   = $_POST['new_role'] === 'admin' ? 'admin' : 'startup';
        $sector = trim($_POST['new_sector'] ?? '');
        $stage  = trim($_POST['new_stage'] ?? 'seed');
        if ($email && $name && strlen($pass) >= 8) {
            $chk = $db->prepare('SELECT id FROM fm_users WHERE email=? LIMIT 1');
            $chk->execute([$email]);
            if (!$chk->fetch()) {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $db->prepare('INSERT INTO fm_users (email,password,startup_name,sector,stage,role,is_active) VALUES (?,?,?,?,?,?,1)')
                   ->execute([$email, $hash, $name, $sector, $stage, $role]);
                auditLog('create_user', 'user', (int)$db->lastInsertId(), $email);
                header('Location: admin.php?tab=users&msg=user_created');
                exit;
            }
            header('Location: admin.php?tab=users&msg=email_exists');
            exit;
        }
        header('Location: admin.php?tab=users&msg=missing_fields');
        exit;
    }

    // Modifier mot de passe
    if ($action === 'change_password') {
        $uid  = (int)($_POST['user_id'] ?? 0);
        $pass = $_POST['new_pass'] ?? '';
        if ($uid && strlen($pass) >= 8) {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $db->prepare('UPDATE fm_users SET password=? WHERE id=?')->execute([$hash, $uid]);
            auditLog('change_password', 'user', $uid);
            header('Location: admin.php?tab=users&msg=password_changed');
            exit;
        }
        header('Location: admin.php?tab=users&msg=missing_fields');
        exit;
    }

    // Modifier infos utilisateur
    if ($action === 'edit_user') {
        $uid    = (int)($_POST['user_id'] ?? 0);
        $name   = trim($_POST['edit_name'] ?? '');
        $email  = trim($_POST['edit_email'] ?? '');
        $sector = trim($_POST['edit_sector'] ?? '');
        $stage  = trim($_POST['edit_stage'] ?? '');
        if ($uid && $name && $email) {
            $db->prepare('UPDATE fm_users SET startup_name=?, email=?, sector=?, stage=? WHERE id=? AND role != "admin"')
               ->execute([$name, $email, $sector, $stage, $uid]);
            auditLog('edit_user', 'user', $uid);
            header('Location: admin.php?tab=users&msg=user_updated');
            exit;
        }
        header('Location: admin.php?tab=users&msg=missing_fields');
        exit;
    }

    // Supprimer utilisateur
    if ($action === 'delete_user') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid) {
            $db->prepare('DELETE FROM fm_users WHERE id=? AND role != "admin"')->execute([$uid]);
            auditLog('delete_user', 'user', $uid);
            header('Location: admin.php?tab=users&msg=user_deleted');
            exit;
        }
    }

    // Archiver programme
    if ($action === 'archive_program') {
        $pid = (int)($_POST['prog_id'] ?? 0);
        $db->prepare('UPDATE fm_programs SET status="archived" WHERE id=?')->execute([$pid]);
        auditLog('archive_program', 'program', $pid);
        header('Location: admin.php?tab=programs&msg=archived');
        exit;
    }

    // Restaurer programme
    if ($action === 'restore_program') {
        $pid = (int)($_POST['prog_id'] ?? 0);
        $db->prepare('UPDATE fm_programs SET status="active" WHERE id=?')->execute([$pid]);
        auditLog('restore_program', 'program', $pid);
        header('Location: admin.php?tab=programs&msg=restored');
        exit;
    }

    // Modifier programme (tous les champs)
    if ($action === 'edit_program') {
        $pid = (int)($_POST['prog_id'] ?? 0);
        if ($pid) {
            $deadline_date = trim($_POST['deadline_date'] ?? '') ?: null;
            $db->prepare('UPDATE fm_programs SET
                name=?, organisation=?, type=?, badge=?, amount=?, stage_target=?,
                geo=?, tn_eligible=?, tunisia_focus=?, deadline=?, deadline_date=?,
                deadline_type=?, sectors=?, description=?, link=?, emoji=?
                WHERE id=?')
               ->execute([
                   trim($_POST['name'] ?? ''),
                   trim($_POST['organisation'] ?? ''),
                   $_POST['type'] ?? 'grant',
                   trim($_POST['badge'] ?? ''),
                   trim($_POST['amount'] ?? ''),
                   trim($_POST['stage_target'] ?? ''),
                   trim($_POST['geo'] ?? ''),
                   trim($_POST['tn_eligible'] ?? ''),
                   isset($_POST['tunisia_focus']) ? 1 : 0,
                   trim($_POST['deadline'] ?? 'Rolling'),
                   $deadline_date,
                   $deadline_date ? calcDeadlineType($deadline_date) : 'open',
                   trim($_POST['sectors'] ?? ''),
                   trim($_POST['description'] ?? ''),
                   trim($_POST['link'] ?? ''),
                   trim($_POST['emoji'] ?? ''),
                   $pid
               ]);
            auditLog('edit_program', 'program', $pid);
        }
        header('Location: admin.php?tab=programs&msg=program_updated');
        exit;
    }

    // Supprimer programme définitivement
    if ($action === 'delete_program') {
        $pid = (int)($_POST['prog_id'] ?? 0);
        if ($pid) {
            $db->prepare('DELETE FROM fm_programs WHERE id=?')->execute([$pid]);
            auditLog('delete_program', 'program', $pid);
        }
        header('Location: admin.php?tab=programs&msg=program_deleted');
        exit;
    }

    // Créer un nouveau programme manuellement
    if ($action === 'create_program') {
        $deadline_date = trim($_POST['deadline_date'] ?? '') ?: null;
        $db->prepare('INSERT INTO fm_programs
            (name,organisation,type,badge,amount,stage_target,geo,tn_eligible,
             tunisia_focus,deadline,deadline_date,deadline_type,sectors,description,
             link,emoji,status,validated_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,"active",?)')
           ->execute([
               trim($_POST['name'] ?? ''),
               trim($_POST['organisation'] ?? ''),
               $_POST['type'] ?? 'grant',
               trim($_POST['badge'] ?? ''),
               trim($_POST['amount'] ?? ''),
               trim($_POST['stage_target'] ?? ''),
               trim($_POST['geo'] ?? ''),
               trim($_POST['tn_eligible'] ?? ''),
               isset($_POST['tunisia_focus']) ? 1 : 0,
               trim($_POST['deadline'] ?? 'Rolling'),
               $deadline_date,
               $deadline_date ? calcDeadlineType($deadline_date) : 'open',
               trim($_POST['sectors'] ?? ''),
               trim($_POST['description'] ?? ''),
               trim($_POST['link'] ?? ''),
               trim($_POST['emoji'] ?? ''),
               $_SESSION['fm_user_id']
           ]);
        auditLog('create_program', 'program', (int)$db->lastInsertId());
        header('Location: admin.php?tab=programs&msg=program_created');
        exit;
    }

    // Import JSON en masse — depuis fichier OU texte collé directement
    if ($action === 'import_programs') {
        $json_path = __DIR__ . '/import/programs.json';
        $imported = 0; $errors = 0;
        $raw_json = trim($_POST['json_paste'] ?? '');
        $data = null;

        if ($raw_json !== '') {
            // Priorité au texte collé dans le formulaire
            $data = json_decode($raw_json, true);
            if (!is_array($data)) {
                header('Location: admin.php?tab=import&msg=json_invalid');
                exit;
            }
        } elseif (file_exists($json_path)) {
            $data = json_decode(file_get_contents($json_path), true);
        }

        if (is_array($data)) {
            $stmt = $db->prepare('INSERT INTO fm_programs
                (name,organisation,type,badge,amount,stage_target,geo,tn_eligible,
                 tunisia_focus,deadline,deadline_date,deadline_type,sectors,description,
                 link,emoji,status,validated_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,"active",?)');
            foreach ($data as $row) {
                if (empty($row['name'])) { $errors++; continue; }
                $dd = !empty($row['deadline_date']) ? $row['deadline_date'] : null;
                try {
                    $stmt->execute([
                        $row['name'] ?? '', $row['organisation'] ?? '',
                        $row['type'] ?? 'grant', $row['badge'] ?? '',
                        $row['amount'] ?? '', $row['stage_target'] ?? '',
                        $row['geo'] ?? '', $row['tn_eligible'] ?? '',
                        !empty($row['tunisia_focus']) ? 1 : 0,
                        $row['deadline'] ?? 'Rolling', $dd,
                        $dd ? calcDeadlineType($dd) : 'open',
                        $row['sectors'] ?? '', $row['description'] ?? '',
                        $row['link'] ?? '', $row['emoji'] ?? '',
                        $_SESSION['fm_user_id']
                    ]);
                    $imported++;
                } catch (Exception $e) { $errors++; }
            }
            // Si le JSON venait du fichier, le renommer pour éviter un double-import
            if ($raw_json === '' && file_exists($json_path)) {
                rename($json_path, __DIR__ . '/import/programs_imported_' . date('YmdHis') . '.json');
            }
        }
        auditLog('import_programs', 'program', 0, "$imported importés, $errors erreurs");
        header('Location: admin.php?tab=import&msg=imported&n=' . $imported . '&e=' . $errors);
        exit;
    }
}

// ── AUTO-ARCHIVAGE DES PROGRAMMES EXPIRÉS ────────────────
// Tout programme actif dont la deadline_date est passée de plus de 3 jours est archivé automatiquement
$db->exec("UPDATE fm_programs SET status='archived'
           WHERE status='active' AND deadline_date IS NOT NULL
           AND deadline_date < DATE_SUB(CURDATE(), INTERVAL 3 DAY)");

// ── DATA ─────────────────────────────────────────────────
$pending_count  = $db->query("SELECT COUNT(*) FROM fm_submissions WHERE status='pending'")->fetchColumn();
$inactive_users = $db->query("SELECT COUNT(*) FROM fm_users WHERE is_active=0 AND role='startup'")->fetchColumn();
$total_programs = $db->query("SELECT COUNT(*) FROM fm_programs WHERE status='active'")->fetchColumn();
$total_users    = $db->query("SELECT COUNT(*) FROM fm_users WHERE role='startup'")->fetchColumn();

include 'header.php';
?>
<script>window._csrf = '<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>';</script>

<div style="margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
  <div>
    <h1 style="font-size:22px;font-weight:700;color:#fff;margin-bottom:4px">Administration</h1>
    <p style="color:var(--muted);font-size:13px">G&eacute;rez les soumissions, utilisateurs et programmes.</p>
  </div>
  <?php if ($pending_count > 0): ?>
    <div style="padding:8px 14px;background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.25);border-radius:8px;font-size:13px;color:var(--accent4)">
      &#9888; <strong><?= $pending_count ?></strong> soumission(s) en attente
    </div>
  <?php endif; ?>
</div>

<!-- KPIs -->
<div class="kpi-row" style="margin-bottom:20px">
  <div class="card">
    <div class="card-title">Programmes actifs</div>
    <div class="card-value" style="color:var(--accent)"><?= $total_programs ?></div>
  </div>
  <div class="card">
    <div class="card-title">Startups inscrites</div>
    <div class="card-value" style="color:#a78bfa"><?= $total_users ?></div>
  </div>
  <div class="card">
    <div class="card-title">Comptes en attente</div>
    <div class="card-value" style="color:var(--accent4)"><?= $inactive_users ?></div>
    <div class="card-sub">Validation requise</div>
  </div>
  <div class="card">
    <div class="card-title">Soumissions pending</div>
    <div class="card-value" style="color:var(--accent5)"><?= $pending_count ?></div>
    <div class="card-sub">&Agrave; valider</div>
  </div>
</div>

<?php if ($msg && $tab !== 'users'): ?>
<div class="alert <?= in_array($msg,['approved','restored','user_updated','user_created','user_deleted','password_changed'])? 'alert-success':'alert-info' ?>">
  <?php
  $msg_texts = [
    'approved' => '✓ Soumission approuvée et ajoutée aux programmes.',
    'rejected' => '✓ Soumission rejetée.',
    'archived' => '✓ Programme archivé.',
    'restored' => '✓ Programme restauré.',
  ];
  echo isset($msg_texts[$msg]) ? $msg_texts[$msg] : h($msg);
  ?>
</div>
<?php endif; ?>

<!-- TABS -->
<div style="display:flex;gap:4px;margin-bottom:20px;border-bottom:1px solid var(--border);padding-bottom:0">
  <?php foreach(['submissions'=>'Soumissions','users'=>'Utilisateurs','programs'=>'Programmes','import'=>'Import JSON','audit'=>'Journal'] as $t=>$label): ?>
    <a href="admin.php?tab=<?= $t ?>"
       style="padding:10px 18px;border-radius:8px 8px 0 0;font-size:13px;font-weight:500;text-decoration:none;transition:all .15s;border:1px solid transparent;border-bottom:none;margin-bottom:-1px;
              <?= $tab===$t ? 'background:var(--card);border-color:var(--border);border-bottom-color:var(--card);color:#fff' : 'color:var(--muted)' ?>">
      <?= $label ?>
      <?php if ($t==='submissions' && $pending_count): ?><span style="margin-left:5px;padding:1px 6px;background:rgba(245,158,11,.2);border-radius:10px;font-size:10px;color:var(--accent4)"><?= $pending_count ?></span><?php endif; ?>
      <?php if ($t==='users' && $inactive_users): ?><span style="margin-left:5px;padding:1px 6px;background:rgba(245,158,11,.2);border-radius:10px;font-size:10px;color:var(--accent4)"><?= $inactive_users ?></span><?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>

<!-- ── TAB: SOUMISSIONS ── -->
<?php if ($tab === 'submissions'):
  $filter_status = $_GET['s'] ?? 'pending';
  $subs = $db->prepare('SELECT fm_submissions.*, fm_users.startup_name, fm_users.email
    FROM fm_submissions
    JOIN fm_users ON fm_submissions.user_id = fm_users.id
    WHERE fm_submissions.status = ?
    ORDER BY fm_submissions.created_at DESC');
  $subs->execute([$filter_status]);
  $subs = $subs->fetchAll();
?>
  <div style="display:flex;gap:8px;margin-bottom:16px">
    <?php foreach(['pending'=>'En attente','approved'=>'Approuvées','rejected'=>'Rejetées'] as $sv=>$sl): ?>
      <a href="admin.php?tab=submissions&s=<?= $sv ?>" class="btn btn-sm <?= $filter_status===$sv?'btn-primary':'btn-secondary' ?>"><?= $sl ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (empty($subs)): ?>
    <div style="text-align:center;padding:40px;color:var(--muted)">Aucune soumission dans cette cat&eacute;gorie.</div>
  <?php else: ?>
  <div style="display:grid;gap:12px">
    <?php foreach ($subs as $s): ?>
    <div class="card" style="padding:16px">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap">
        <div style="flex:1;min-width:0">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
            <span class="badge badge-<?= h($s['type']) ?>"><?= h($s['type']) ?></span>
            <span style="font-size:11px;color:var(--muted);font-family:var(--mono)"><?= date('d/m/Y H:i', strtotime($s['created_at'])) ?></span>
          </div>
          <div style="font-size:15px;font-weight:600;color:#fff;margin-bottom:2px"><?= h($s['name'] ?: 'Sans nom') ?></div>
          <div style="font-size:12px;color:var(--muted);margin-bottom:6px"><?= h($s['organisation'] ?: '—') ?> &mdash; soumis par <strong style="color:var(--accent)"><?= h($s['startup_name']) ?></strong> <span style="color:var(--muted)">(<?= h($s['email']) ?>)</span></div>
          <?php if ($s['description']): ?><div style="font-size:12px;color:var(--label);margin-bottom:6px;line-height:1.5"><?= h(mb_substr($s['description'],0,200)) ?>...</div><?php endif; ?>
          <div style="display:flex;gap:8px;flex-wrap:wrap;font-size:11px;color:var(--muted)">
            <?php if ($s['amount']): ?><span>&#128176; <?= h($s['amount']) ?></span><?php endif; ?>
            <?php if ($s['geo']): ?><span>&#127757; <?= h($s['geo']) ?></span><?php endif; ?>
            <?php if ($s['deadline']): ?><span>&#128197; <?= h($s['deadline']) ?></span><?php endif; ?>
            <a href="<?= h($s['url']) ?>" target="_blank" rel="noopener" style="color:var(--accent);text-decoration:none">&#128279; Voir l&rsquo;URL &#8599;</a>
          </div>
          <?php if ($s['notes']): ?><div style="margin-top:8px;font-size:12px;color:var(--accent4);font-style:italic">Note: <?= h($s['notes']) ?></div><?php endif; ?>
        </div>
        <?php if ($s['status'] === 'pending'): ?>
        <div style="display:flex;flex-direction:column;gap:8px;flex-shrink:0">
          <form method="POST">
            <input type="hidden" name="action" value="review_submission">
            <input type="hidden" name="sub_id" value="<?= $s['id'] ?>">
            <input type="hidden" name="status" value="approved">
            <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Approuver et publier ce programme ?')">&#10003; Approuver</button>
          </form>
          <form method="POST">
            <input type="hidden" name="action" value="review_submission">
            <input type="hidden" name="sub_id" value="<?= $s['id'] ?>">
            <input type="hidden" name="status" value="rejected">
            <button type="submit" class="btn btn-danger btn-sm">&#10005; Rejeter</button>
          </form>
        </div>
        <?php else: ?>
        <span class="badge badge-<?= $s['status'] === 'approved' ? 'approved' : 'rejected' ?>">
          <?= $s['status'] === 'approved' ? 'Approuv&eacute;' : 'Refus&eacute;' ?>
        </span>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

<!-- ── TAB: UTILISATEURS ── -->
<?php elseif ($tab === 'users'):
  $per_page   = 25;
  $page_u     = max(1, (int)($_GET['p'] ?? 1));
  $users_total = (int)$db->query("SELECT COUNT(*) FROM fm_users")->fetchColumn();
  $pages_u    = max(1, (int)ceil($users_total / $per_page));
  $page_u     = min($page_u, $pages_u);
  $offset_u   = ($page_u - 1) * $per_page;
  $users = $db->query("SELECT * FROM fm_users ORDER BY role DESC, is_active DESC, created_at DESC LIMIT $per_page OFFSET $offset_u")->fetchAll();
  $msg_users = $_GET['msg'] ?? '';
  $stades_admin = ['idee'=>'Idée / Pré-création','bootstrapping'=>'Bootstrapping','pre-seed'=>'Pre-Seed','seed'=>'Seed','series-a'=>'Série A','series-b'=>'Série B','series-c'=>'Série C','growth'=>'Growth / Late Stage','pre-ipo'=>'Pré-IPO'];
?>

<!-- Formulaire création -->
<div class="card" style="margin-bottom:20px">
  <div style="font-size:12px;color:var(--accent);font-family:var(--mono);text-transform:uppercase;letter-spacing:1px;margin-bottom:16px">+ Créer un nouveau compte</div>
  <form method="POST" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;align-items:end;flex-wrap:wrap">
    <input type="hidden" name="action" value="create_user">
    <div class="field" style="margin:0">
      <label>Nom startup *</label>
      <input type="text" name="new_name" placeholder="Nom de la startup" required>
    </div>
    <div class="field" style="margin:0">
      <label>Email *</label>
      <input type="email" name="new_email" placeholder="contact@startup.tn" required>
    </div>
    <div class="field" style="margin:0">
      <label>Mot de passe * (min 8 car.)</label>
      <input type="password" name="new_password" placeholder="••••••••" required minlength="8">
    </div>
    <div class="field" style="margin:0">
      <label>Secteur</label>
      <input type="text" name="new_sector" placeholder="Fintech, Cleantech...">
    </div>
    <div class="field" style="margin:0">
      <label>Stade</label>
      <select name="new_stage" style="width:100%;padding:10px 14px;background:var(--surface);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;outline:none;-webkit-appearance:none">
        <?php foreach ($stades_admin as $sk=>$sl): ?>
        <option value="<?= $sk ?>"><?= h($sl) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field" style="margin:0">
      <label>Rôle</label>
      <select name="new_role" style="width:100%;padding:10px 14px;background:var(--surface);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;outline:none;-webkit-appearance:none">
        <option value="startup">Startup</option>
        <option value="admin">Admin</option>
      </select>
    </div>
    <div style="grid-column:1/-1">
      <button type="submit" class="btn btn-primary">+ Créer le compte</button>
    </div>
  </form>
</div>

<?php
$msg_map = [
  'user_created' => ['alert-success','✓ Compte créé avec succès.'],
  'user_updated' => ['alert-success','✓ Compte mis à jour.'],
  'user_deleted' => ['alert-success','✓ Compte supprimé.'],
  'password_changed' => ['alert-success','✓ Mot de passe modifié.'],
  'email_exists' => ['alert-error','✗ Cet email est déjà utilisé.'],
  'missing_fields' => ['alert-error','✗ Champs obligatoires manquants.'],
];
if (isset($msg_map[$msg_users])): ?>
  <div class="alert <?= $msg_map[$msg_users][0] ?>" style="margin-bottom:16px"><?= $msg_map[$msg_users][1] ?></div>
<?php endif; ?>

<!-- Liste utilisateurs -->
<div style="background:var(--card);border:1px solid var(--border);border-radius:12px;overflow:hidden;overflow-x:auto">
  <table style="min-width:900px">
    <thead><tr>
      <th>Startup</th><th>Email</th><th>Secteur</th><th>Stade</th><th>Rôle</th><th>Dernière conn.</th><th>Statut</th><th style="min-width:280px">Actions</th>
    </tr></thead>
    <tbody>
      <?php foreach ($users as $u): ?>
      <tr>
        <td style="font-weight:600;color:#fff"><?= h($u['startup_name']) ?></td>
        <td style="color:var(--muted);font-size:12px"><?= h($u['email']) ?></td>
        <td style="font-size:12px;color:var(--label)"><?= h($u['sector'] ?: '—') ?></td>
        <td style="font-size:11px;font-family:var(--mono);color:var(--muted)"><?= h($stades_admin[$u['stage']] ?? ($u['stage'] ?: '—')) ?></td>
        <td><?= $u['role']==='admin' ? '<span class="badge" style="background:rgba(124,58,237,.2);color:#a78bfa;border-color:rgba(124,58,237,.4)">Admin</span>' : '<span class="badge badge-fund">Startup</span>' ?></td>
        <td style="font-size:11px;color:var(--muted);font-family:var(--mono)"><?= $u['last_login'] ? date('d/m/Y', strtotime($u['last_login'])) : 'Jamais' ?></td>
        <td><?= $u['is_active'] ? '<span class="badge badge-active">Actif</span>' : '<span class="badge badge-pending">Inactif</span>' ?></td>
        <td>
          <?php if ($u['role'] !== 'admin'): ?>
          <div style="display:flex;gap:6px;flex-wrap:wrap">
            <!-- Activer/Désactiver -->
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="toggle_user">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <input type="hidden" name="is_active" value="<?= $u['is_active'] ? 0 : 1 ?>">
              <button type="submit" class="btn btn-sm <?= $u['is_active'] ? 'btn-danger' : 'btn-success' ?>"
                onclick="return confirm('<?= $u['is_active'] ? 'Désactiver' : 'Activer' ?> ce compte ?')">
                <?= $u['is_active'] ? 'Désactiver' : 'Activer' ?>
              </button>
            </form>
            <!-- Modifier -->
            <button class="btn btn-sm btn-secondary btn-edit-user"
              data-id="<?= (int)$u['id'] ?>"
              data-name="<?= h($u['startup_name']) ?>"
              data-email="<?= h($u['email']) ?>"
              data-sector="<?= h($u['sector'] ?? '') ?>"
              data-stage="<?= h($u['stage'] ?? 'seed') ?>">Modifier</button>
            <!-- Changer MDP -->
            <button class="btn btn-sm btn-secondary btn-pass-user"
              data-id="<?= (int)$u['id'] ?>"
              data-name="<?= h($u['startup_name']) ?>">MDP</button>
            <!-- Supprimer -->
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="delete_user">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger btn-del-user"
                data-name="<?= h($u['startup_name']) ?>">
                Supprimer
              </button>
            </form>
          </div>
          <?php else: ?>
            <span style="color:var(--muted);font-size:11px">Protégé</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if ($pages_u > 1): ?>
<div style="display:flex;gap:6px;justify-content:center;margin-top:16px;flex-wrap:wrap">
  <?php for ($i = 1; $i <= $pages_u; $i++): ?>
    <a href="admin.php?tab=users&p=<?= $i ?>" class="btn btn-sm <?= $i === $page_u ? 'btn-primary' : 'btn-secondary' ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<!-- MODAL MODIFIER UTILISATEUR -->
<div class="modal-overlay" id="modal-edit">
  <div class="modal" style="max-width:480px">
    <div class="modal-header">
      <div class="modal-title">Modifier le compte</div>
      <button class="modal-close" onclick="document.getElementById('modal-edit').classList.remove('open')">&#10005;</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="action" value="edit_user">
        <input type="hidden" name="user_id" id="edit-uid">
        <div class="field">
          <label>Nom de la startup</label>
          <input type="text" name="edit_name" id="edit-name" required>
        </div>
        <div class="field">
          <label>Email</label>
          <input type="email" name="edit_email" id="edit-email" required>
        </div>
        <div class="field">
          <label>Secteur</label>
          <input type="text" name="edit_sector" id="edit-sector" placeholder="Fintech, Cleantech...">
        </div>
        <div class="field">
          <label>Stade</label>
          <select name="edit_stage" id="edit-stage" style="width:100%;padding:10px 14px;background:var(--surface);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;outline:none;-webkit-appearance:none">
            <?php foreach ($stades_admin as $sk=>$sl): ?>
            <option value="<?= $sk ?>"><?= h($sl) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">Enregistrer</button>
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('modal-edit').classList.remove('open')">Annuler</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL CHANGER MOT DE PASSE -->
<div class="modal-overlay" id="modal-pass">
  <div class="modal" style="max-width:400px">
    <div class="modal-header">
      <div class="modal-title">Changer le mot de passe</div>
      <button class="modal-close" onclick="document.getElementById('modal-pass').classList.remove('open')">&#10005;</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="action" value="change_password">
        <input type="hidden" name="user_id" id="pass-uid">
        <p style="font-size:13px;color:var(--muted);margin-bottom:16px">Compte : <strong id="pass-name" style="color:var(--text)"></strong></p>
        <div class="field">
          <label>Nouveau mot de passe (min 8 caractères)</label>
          <input type="password" name="new_pass" placeholder="••••••••" required minlength="8">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">Changer</button>
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('modal-pass').classList.remove('open')">Annuler</button>
      </div>
    </form>
  </div>
</div>

<script>
document.querySelectorAll('.btn-edit-user').forEach(function(b) {
  b.addEventListener('click', function() {
    document.getElementById('edit-uid').value    = b.dataset.id;
    document.getElementById('edit-name').value   = b.dataset.name;
    document.getElementById('edit-email').value  = b.dataset.email;
    document.getElementById('edit-sector').value = b.dataset.sector;
    document.getElementById('edit-stage').value  = b.dataset.stage;
    document.getElementById('modal-edit').classList.add('open');
  });
});
document.querySelectorAll('.btn-pass-user').forEach(function(b) {
  b.addEventListener('click', function() {
    document.getElementById('pass-uid').value = b.dataset.id;
    document.getElementById('pass-name').textContent = b.dataset.name;
    document.getElementById('modal-pass').classList.add('open');
  });
});
document.querySelectorAll('.btn-del-user').forEach(function(b) {
  b.addEventListener('click', function(e) {
    if (!confirm('⚠️ Supprimer définitivement le compte de « ' + b.dataset.name + ' » ? Cette action est irréversible.')) {
      e.preventDefault();
    }
  });
});
document.getElementById('modal-edit').addEventListener('click', function(e){if(e.target===this)this.classList.remove('open');});
document.getElementById('modal-pass').addEventListener('click', function(e){if(e.target===this)this.classList.remove('open');});
</script>

<!-- ── TAB: PROGRAMMES ── -->
<?php elseif ($tab === 'programs'):
  $prog_status = $_GET['ps'] ?? 'active';
  $progs = $db->prepare('SELECT p.*, u.startup_name as submitted_by_name
    FROM fm_programs p
    LEFT JOIN fm_users u ON p.submitted_by = u.id
    WHERE p.status = ?
    ORDER BY p.deadline_date IS NULL, p.deadline_date ASC, p.name ASC');
  $progs->execute([$prog_status]);
  $progs = $progs->fetchAll();
  $prog_msg = $_GET['msg'] ?? '';
?>
<?php if ($prog_msg): ?>
<div class="alert <?= in_array($prog_msg,['program_updated','program_created','program_deleted','archived','restored'])?'alert-success':'alert-info' ?>">
  <?php
  $pm = ['program_updated'=>'✓ Programme mis à jour.','program_created'=>'✓ Programme créé.','program_deleted'=>'✓ Programme supprimé définitivement.','archived'=>'✓ Programme archivé.','restored'=>'✓ Programme restauré.'];
  echo isset($pm[$prog_msg]) ? $pm[$prog_msg] : h($prog_msg);
  ?>
</div>
<?php endif; ?>

<div style="display:flex;gap:8px;margin-bottom:16px;align-items:center;justify-content:space-between;flex-wrap:wrap">
  <div style="display:flex;gap:8px">
    <?php foreach(['active'=>'Actifs','archived'=>'Archivés (expirés)','pending'=>'En attente'] as $pv=>$pl): ?>
      <a href="admin.php?tab=programs&ps=<?= $pv ?>" class="btn btn-sm <?= $prog_status===$pv?'btn-primary':'btn-secondary' ?>"><?= $pl ?></a>
    <?php endforeach; ?>
  </div>
  <div style="display:flex;gap:8px;align-items:center">
    <span class="count-pill"><?= count($progs) ?> programme(s)</span>
    <button class="btn btn-sm btn-primary" onclick="openCreateProgram()">+ Nouveau programme</button>
  </div>
</div>

<p style="font-size:12px;color:var(--muted);margin-bottom:16px">
  ℹ️ Les programmes dont la deadline est dépassée de plus de 3 jours sont automatiquement déplacés dans l'onglet <strong>Archivés</strong>.
</p>

<div style="background:var(--card);border:1px solid var(--border);border-radius:12px;overflow:hidden;overflow-x:auto">
  <table style="min-width:760px">
    <thead><tr>
      <th>Programme</th><th>Organisation</th><th>Type</th><th>Deadline</th><th>Soumis par</th><th style="min-width:200px">Action</th>
    </tr></thead>
    <tbody>
      <?php foreach ($progs as $p): ?>
      <tr>
        <td style="font-weight:600;color:#fff"><?= h($p['emoji'] ?: '') ?> <?= h($p['name']) ?></td>
        <td style="color:var(--muted);font-size:12px"><?= h($p['organisation']) ?></td>
        <td><span class="badge badge-<?= h($p['type']) ?>"><?= h($p['badge'] ?: $p['type']) ?></span></td>
        <td class="dl-<?= h($p['deadline_type']) ?>"><?= h($p['deadline']) ?></td>
        <td style="font-size:11px;color:var(--muted)"><?= $p['submitted_by_name'] ? h($p['submitted_by_name']) : '<span style="color:var(--border)">Données initiales</span>' ?></td>
        <td>
          <div style="display:flex;gap:6px;flex-wrap:wrap">
            <button class="btn btn-sm btn-secondary" onclick='openEditProgram(<?= json_encode($p, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>Modifier</button>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="<?= $prog_status==='active'?'archive_program':'restore_program' ?>">
              <input type="hidden" name="prog_id" value="<?= $p['id'] ?>">
              <button type="submit" class="btn btn-sm <?= $prog_status==='active'?'btn-danger':'btn-success' ?>"
                onclick="return confirm('<?= $prog_status==='active'?'Archiver':'Restaurer' ?> ce programme ?')">
                <?= $prog_status==='active'?'Archiver':'Restaurer' ?>
              </button>
            </form>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="delete_program">
              <input type="hidden" name="prog_id" value="<?= $p['id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger btn-del-prog"
                data-name="<?= h($p['name']) ?>">
                Supprimer
              </button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- MODAL CRÉER / MODIFIER PROGRAMME -->
<div class="modal-overlay" id="modal-program">
  <div class="modal" style="max-width:640px">
    <div class="modal-header">
      <div class="modal-title" id="mp-prog-title">Nouveau programme</div>
      <button class="modal-close" onclick="document.getElementById('modal-program').classList.remove('open')">&#10005;</button>
    </div>
    <form method="POST" id="form-program">
      <div class="modal-body" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;max-height:65vh;overflow-y:auto">
        <input type="hidden" name="action" id="prog-action" value="create_program">
        <input type="hidden" name="prog_id" id="prog-id" value="">

        <div class="field" style="grid-column:1/-1">
          <label>Nom du programme *</label>
          <input type="text" name="name" id="prog-name" required>
        </div>
        <div class="field">
          <label>Organisation</label>
          <input type="text" name="organisation" id="prog-organisation">
        </div>
        <div class="field">
          <label>Type</label>
          <select name="type" id="prog-type">
            <option value="fund">Fonds VC</option>
            <option value="accelerator">Accélérateur</option>
            <option value="grant">Subvention</option>
            <option value="competition">Compétition</option>
            <option value="incubator">Incubateur</option>
          </select>
        </div>
        <div class="field">
          <label>Badge (texte court)</label>
          <input type="text" name="badge" id="prog-badge" placeholder="Ex: Accélérateur">
        </div>
        <div class="field">
          <label>Emoji</label>
          <input type="text" name="emoji" id="prog-emoji" placeholder="🚀" maxlength="4">
        </div>
        <div class="field">
          <label>Montant</label>
          <input type="text" name="amount" id="prog-amount" placeholder="Ex: 50 000 EUR">
        </div>
        <div class="field">
          <label>Stade ciblé</label>
          <input type="text" name="stage_target" id="prog-stage" placeholder="Ex: Seed, Série A">
        </div>
        <div class="field">
          <label>Géographie</label>
          <input type="text" name="geo" id="prog-geo" placeholder="Ex: Tunisie">
        </div>
        <div class="field">
          <label>Éligibilité TN</label>
          <input type="text" name="tn_eligible" id="prog-tn" placeholder="Ex: Oui — national">
        </div>
        <div class="field">
          <label>Deadline (texte affiché)</label>
          <input type="text" name="deadline" id="prog-deadline" placeholder="Rolling">
        </div>
        <div class="field">
          <label>Date deadline (calcul auto urgence)</label>
          <input type="date" name="deadline_date" id="prog-deadline-date">
        </div>
        <div class="field" style="grid-column:1/-1">
          <label>Secteurs (séparés par virgule)</label>
          <input type="text" name="sectors" id="prog-sectors" placeholder="Cleantech,Fintech,IA / AI">
        </div>
        <div class="field" style="grid-column:1/-1">
          <label>Lien officiel</label>
          <input type="url" name="link" id="prog-link" placeholder="https://...">
        </div>
        <div class="field" style="grid-column:1/-1">
          <label>Description</label>
          <textarea name="description" id="prog-description" style="min-height:90px"></textarea>
        </div>
        <div style="grid-column:1/-1">
          <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--label);cursor:pointer">
            <input type="checkbox" name="tunisia_focus" id="prog-tunisia" value="1" style="accent-color:var(--accent);width:16px;height:16px">
            Focus Tunisie
          </label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">Enregistrer</button>
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('modal-program').classList.remove('open')">Annuler</button>
      </div>
    </form>
  </div>
</div>

<script>
function openCreateProgram() {
  document.getElementById('form-program').reset();
  document.getElementById('mp-prog-title').textContent = 'Nouveau programme';
  document.getElementById('prog-action').value = 'create_program';
  document.getElementById('prog-id').value = '';
  document.getElementById('modal-program').classList.add('open');
}
function openEditProgram(p) {
  document.getElementById('mp-prog-title').textContent = 'Modifier : ' + p.name;
  document.getElementById('prog-action').value = 'edit_program';
  document.getElementById('prog-id').value = p.id;
  document.getElementById('prog-name').value = p.name || '';
  document.getElementById('prog-organisation').value = p.organisation || '';
  document.getElementById('prog-type').value = p.type || 'grant';
  document.getElementById('prog-badge').value = p.badge || '';
  document.getElementById('prog-emoji').value = p.emoji || '';
  document.getElementById('prog-amount').value = p.amount || '';
  document.getElementById('prog-stage').value = p.stage_target || '';
  document.getElementById('prog-geo').value = p.geo || '';
  document.getElementById('prog-tn').value = p.tn_eligible || '';
  document.getElementById('prog-deadline').value = p.deadline || '';
  document.getElementById('prog-deadline-date').value = p.deadline_date || '';
  document.getElementById('prog-sectors').value = p.sectors || '';
  document.getElementById('prog-link').value = p.link || '';
  document.getElementById('prog-description').value = p.description || '';
  document.getElementById('prog-tunisia').checked = parseInt(p.tunisia_focus) === 1;
  document.getElementById('modal-program').classList.add('open');
}
document.getElementById('modal-program').addEventListener('click', function(e){ if(e.target===this) this.classList.remove('open'); });
document.querySelectorAll('.btn-del-prog').forEach(function(b) {
  b.addEventListener('click', function(e) {
    if (!confirm('⚠️ Supprimer définitivement « ' + b.dataset.name + ' » ? Action irréversible.')) {
      e.preventDefault();
    }
  });
});
</script>

<!-- ── TAB: IMPORT JSON ── -->
<?php elseif ($tab === 'import'):
  $import_dir = __DIR__ . '/import';
  if (!is_dir($import_dir)) { @mkdir($import_dir, 0755, true); }
  $pending_file = $import_dir . '/programs.json';
  $has_pending = file_exists($pending_file);
  $pending_preview = [];
  $pending_count_imp = 0;
  if ($has_pending) {
      $raw = json_decode(file_get_contents($pending_file), true);
      if (is_array($raw)) { $pending_preview = $raw; $pending_count_imp = count($raw); }
  }
  $imp_msg = $_GET['msg'] ?? '';
?>

<?php if ($imp_msg === 'imported'): ?>
  <div class="alert alert-success">
    ✓ Import terminé : <strong><?= (int)($_GET['n'] ?? 0) ?></strong> programme(s) ajouté(s)<?php if ((int)($_GET['e'] ?? 0) > 0): ?>, <strong style="color:var(--accent5)"><?= (int)$_GET['e'] ?></strong> erreur(s) ignorée(s)<?php endif; ?>.
  </div>
<?php endif; ?>

<div class="card" style="margin-bottom:20px">
  <div class="card-section-label">2 façons d'importer des programmes</div>
  <p style="font-size:13.5px;color:var(--text-sec);line-height:1.7;margin-bottom:8px">
    <strong style="color:var(--accent)">① Coller directement (recommandé)</strong> — demandez à Claude le JSON, collez-le ci-dessous, cliquez sur Importer. Aucun fichier à téléverser.
  </p>
  <p style="font-size:13.5px;color:var(--text-sec);line-height:1.7;margin-bottom:0">
    <strong style="color:var(--muted)">② Via fichier</strong> — déposez <code style="background:var(--surface);padding:2px 6px;border-radius:4px;color:var(--accent)">programs.json</code> dans <code style="background:var(--surface);padding:2px 6px;border-radius:4px;color:var(--accent)">public_html/startup/import/</code> avec le File Manager Hostinger.
  </p>
</div>

<?php if ($imp_msg === 'json_invalid'): ?>
  <div class="alert alert-error">✗ Le JSON collé est invalide. Vérifiez le format et réessayez.</div>
<?php endif; ?>

<div class="card" style="margin-bottom:20px">
  <div class="card-section-label">① Coller le JSON ici</div>
  <form method="POST">
    <input type="hidden" name="action" value="import_programs">
    <div class="field">
      <textarea name="json_paste" rows="8" placeholder='[ { "name": "...", "organisation": "...", ... } ]'
        style="width:100%;font-family:var(--mono);font-size:12.5px;background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:12px;color:var(--text-sec);resize:vertical"></textarea>
    </div>
    <button type="submit" class="btn btn-primary" onclick="return document.querySelector('[name=json_paste]').value.trim() !== '' && confirm('Importer ces programmes maintenant ?')">
      ⬆ Importer le JSON collé
    </button>
  </form>
</div>

<?php if ($has_pending && $pending_count_imp > 0): ?>
  <div class="card" style="margin-bottom:20px;border-color:var(--accent-border)">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px">
      <div>
        <div style="font-size:15px;font-weight:600;color:#fff">📦 ② Fichier détecté : programs.json</div>
        <div style="font-size:13px;color:var(--muted)"><?= $pending_count_imp ?> programme(s) prêt(s) à importer</div>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="import_programs">
        <button type="submit" class="btn btn-primary" onclick="return confirm('Importer ces <?= $pending_count_imp ?> programmes maintenant ?')">
          ⬆ Importer maintenant
        </button>
      </form>
    </div>
    <div style="display:flex;flex-direction:column;gap:8px;max-height:400px;overflow-y:auto">
      <?php foreach ($pending_preview as $pp): ?>
      <div style="background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:12px 14px;display:flex;align-items:center;gap:10px">
        <span style="font-size:18px"><?= h($pp['emoji'] ?? '📄') ?></span>
        <div style="flex:1;min-width:0">
          <div style="font-size:13.5px;font-weight:600;color:#fff;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($pp['name'] ?? 'Sans nom') ?></div>
          <div style="font-size:12px;color:var(--muted)"><?= h($pp['organisation'] ?? '') ?> · <?= h($pp['type'] ?? '') ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php else: ?>
  <div class="card" style="text-align:center;padding:40px;color:var(--muted)">
    <div style="font-size:36px;margin-bottom:10px">📭</div>
    <p style="font-size:14px">Aucun fichier <code style="background:var(--surface);padding:2px 6px;border-radius:4px">programs.json</code> détecté dans le dossier import.</p>
    <p style="font-size:12px;margin-top:8px">Chemin attendu : <code style="background:var(--surface);padding:2px 6px;border-radius:4px;color:var(--accent)">public_html/startup/import/programs.json</code></p>
  </div>
<?php endif; ?>

<!-- ── TAB: AUDIT ── -->
<?php elseif ($tab === 'audit'):
  $per_page_a = 50;
  $page_a     = max(1, (int)($_GET['p'] ?? 1));
  $logs_total = (int)$db->query("SELECT COUNT(*) FROM fm_audit")->fetchColumn();
  $pages_a    = max(1, (int)ceil($logs_total / $per_page_a));
  $page_a     = min($page_a, $pages_a);
  $offset_a   = ($page_a - 1) * $per_page_a;
  $logs = $db->query("SELECT l.*, u.startup_name, u.email
    FROM fm_audit l
    LEFT JOIN fm_users u ON l.user_id = u.id
    ORDER BY l.created_at DESC
    LIMIT $per_page_a OFFSET $offset_a")->fetchAll();
?>
  <div style="background:var(--card);border:1px solid var(--border);border-radius:12px;overflow:hidden;overflow-x:auto">
    <table style="min-width:700px">
      <thead><tr>
        <th>Date</th><th>Utilisateur</th><th>Action</th><th>Cible</th><th>D&eacute;tails</th><th>IP</th>
      </tr></thead>
      <tbody>
        <?php foreach ($logs as $l): ?>
        <tr>
          <td style="font-family:var(--mono);font-size:11px;color:var(--muted);white-space:nowrap"><?= date('d/m/Y H:i', strtotime($l['created_at'])) ?></td>
          <td style="font-size:12px"><?= h($l['startup_name'] ?: 'Syst&egrave;me') ?></td>
          <td><span style="padding:2px 7px;background:rgba(0,212,255,.08);border:1px solid rgba(0,212,255,.15);border-radius:4px;font-size:10px;font-family:var(--mono);color:var(--accent)"><?= h($l['action']) ?></span></td>
          <td style="font-size:11px;color:var(--label)"><?= h(($l['target'] ?: '') . ($l['target_id'] ? ' #'.$l['target_id'] : '')) ?></td>
          <td style="font-size:11px;color:var(--muted)"><?= h(mb_substr($l['details'] ?: '', 0, 60)) ?></td>
          <td style="font-family:var(--mono);font-size:10px;color:var(--muted)"><?= h($l['ip'] ?: '—') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages_a > 1): ?>
  <div style="display:flex;gap:6px;justify-content:center;margin-top:16px;flex-wrap:wrap;align-items:center">
    <?php if ($page_a > 1): ?><a href="admin.php?tab=audit&p=<?= $page_a - 1 ?>" class="btn btn-sm btn-secondary">&larr; Précédent</a><?php endif; ?>
    <span class="count-pill">Page <?= $page_a ?> / <?= $pages_a ?> (<?= $logs_total ?> entrées)</span>
    <?php if ($page_a < $pages_a): ?><a href="admin.php?tab=audit&p=<?= $page_a + 1 ?>" class="btn btn-sm btn-secondary">Suivant &rarr;</a><?php endif; ?>
  </div>
  <?php endif; ?>
<?php endif; ?>

<script>
// Auto-inject CSRF token into every POST form that doesn't already have it
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('form[method="POST"],form[method="post"]').forEach(function(form) {
    if (!form.querySelector('input[name="csrf_token"]')) {
      var f = document.createElement('input');
      f.type = 'hidden'; f.name = 'csrf_token'; f.value = window._csrf;
      form.appendChild(f);
    }
  });
});
</script>

<?php include 'footer.php'; ?>
