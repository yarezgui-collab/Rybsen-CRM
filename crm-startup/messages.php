<?php
require_once 'config.php';
requireLogin();
$page_title = 'Messages';

$db  = getDB();
$uid = (int)$_SESSION['fm_user_id'];

// ── ENVOYER UN MESSAGE ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_msg'])) {
    $to   = (int)($_POST['receiver_id'] ?? 0);
    $body = trim($_POST['body'] ?? '');
    if ($to && $to !== $uid && $body) {
        // Vérifier que le destinataire existe et est actif
        $chk = $db->prepare('SELECT id FROM fm_users WHERE id=? AND is_active=1 LIMIT 1');
        $chk->execute([$to]);
        if ($chk->fetch()) {
            $db->prepare('INSERT INTO fm_messages (sender_id, receiver_id, body) VALUES (?,?,?)')
               ->execute([$uid, $to, $body]);
            auditLog('send_message', 'message', $to);
        }
    }
    header('Location: messages.php?to=' . $to);
    exit;
}

// ── MARQUER COMME LU ─────────────────────────────
$to_id = (int)($_GET['to'] ?? 0);
if ($to_id) {
    $db->prepare('UPDATE fm_messages SET is_read=1 WHERE receiver_id=? AND sender_id=?')
       ->execute([$uid, $to_id]);
}

// ── LISTE DES CONVERSATIONS ───────────────────────
$convs = $db->prepare("
    SELECT 
        u.id, u.startup_name, u.sector, u.city,
        m.body as last_msg, m.created_at as last_time,
        m.sender_id as last_sender,
        (SELECT COUNT(*) FROM fm_messages WHERE sender_id=u.id AND receiver_id=? AND is_read=0) as unread
    FROM fm_users u
    JOIN fm_messages m ON (
        (m.sender_id=u.id AND m.receiver_id=?) OR
        (m.sender_id=? AND m.receiver_id=u.id)
    )
    WHERE u.id != ? AND u.is_active=1
    GROUP BY u.id
    ORDER BY last_time DESC
");
$convs->execute([$uid, $uid, $uid, $uid]);
$conversations = $convs->fetchAll();

// ── LISTE STARTUPS POUR NOUVEAU MESSAGE ──────────
$starters = $db->prepare("
    SELECT id, startup_name, sector, city
    FROM fm_users
    WHERE id != ? AND role='startup' AND is_active=1
    AND id NOT IN (
        SELECT DISTINCT CASE WHEN sender_id=? THEN receiver_id ELSE sender_id END
        FROM fm_messages WHERE sender_id=? OR receiver_id=?
    )
    ORDER BY startup_name ASC
");
$starters->execute([$uid, $uid, $uid, $uid]);
$new_contacts = $starters->fetchAll();

// ── MESSAGES DE LA CONVERSATION ACTIVE ───────────
$messages = [];
$partner  = null;
if ($to_id) {
    $p = $db->prepare('SELECT id, startup_name, sector, city FROM fm_users WHERE id=? AND is_active=1 LIMIT 1');
    $p->execute([$to_id]);
    $partner = $p->fetch();

    if ($partner) {
        $msgs = $db->prepare("
            SELECT m.*, u.startup_name as sender_name
            FROM fm_messages m
            JOIN fm_users u ON m.sender_id = u.id
            WHERE (m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?)
            ORDER BY m.created_at ASC
        ");
        $msgs->execute([$uid, $to_id, $to_id, $uid]);
        $messages = $msgs->fetchAll();
    }
}

// Si ?to= passé depuis l'annuaire mais pas encore de conversation
if ($to_id && !$partner) {
    $p = $db->prepare('SELECT id, startup_name, sector, city FROM fm_users WHERE id=? AND is_active=1 LIMIT 1');
    $p->execute([$to_id]);
    $partner = $p->fetch();
}

include 'header.php';
?>

<div style="margin-bottom:20px">
  <h1>Messages</h1>
  <p style="color:var(--muted);font-size:14px;margin-top:4px">Communiquez directement avec les autres startups.</p>
</div>

<div style="display:grid;grid-template-columns:300px 1fr;gap:16px;height:calc(100vh - 200px);min-height:500px">

  <!-- ── SIDEBAR CONVERSATIONS ── -->
  <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius-lg);display:flex;flex-direction:column;overflow:hidden">
    
    <!-- Nouveau message -->
    <div style="padding:14px;border-bottom:1px solid var(--border)">
      <button onclick="document.getElementById('modal-new').classList.add('open')"
              class="btn btn-primary" style="width:100%;font-size:13px;min-height:40px">
        &#43; Nouveau message
      </button>
    </div>

    <!-- Liste conversations -->
    <div style="overflow-y:auto;flex:1">
      <?php if (empty($conversations) && !$to_id): ?>
        <div style="padding:24px;text-align:center;color:var(--muted)">
          <div style="font-size:32px;margin-bottom:8px">&#128172;</div>
          <p style="font-size:13px">Aucune conversation.<br>Commencez à échanger !</p>
        </div>
      <?php endif; ?>

      <?php
      // Ajouter le partenaire actuel s'il n'est pas dans les conversations
      $conv_ids = array_column($conversations, 'id');
      if ($to_id && $partner && !in_array($to_id, $conv_ids)):
      ?>
      <a href="messages.php?to=<?= $partner['id'] ?>"
         style="display:flex;align-items:center;gap:12px;padding:14px;text-decoration:none;
                background:var(--accent-dim);border-left:2px solid var(--accent)">
        <div style="width:40px;height:40px;flex-shrink:0;background:var(--surface);border:1px solid var(--border);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:700;color:var(--accent)">
          <?= mb_strtoupper(mb_substr($partner['startup_name'],0,1)) ?>
        </div>
        <div style="flex:1;min-width:0">
          <div style="font-size:14px;font-weight:600;color:#fff;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($partner['startup_name']) ?></div>
          <div style="font-size:12px;color:var(--muted)">Démarrer la conversation</div>
        </div>
      </a>
      <?php endif; ?>

      <?php foreach ($conversations as $c): ?>
      <a href="messages.php?to=<?= $c['id'] ?>"
         style="display:flex;align-items:center;gap:12px;padding:14px;text-decoration:none;
                border-bottom:1px solid var(--border);transition:background .15s;
                <?= $to_id===$c['id'] ? 'background:var(--accent-dim);border-left:2px solid var(--accent)' : '' ?>">
        <div style="position:relative;flex-shrink:0">
          <div style="width:40px;height:40px;background:var(--surface);border:1px solid var(--border);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:700;color:var(--accent)">
            <?= mb_strtoupper(mb_substr($c['startup_name'],0,1)) ?>
          </div>
          <?php if ($c['unread'] > 0): ?>
          <span style="position:absolute;top:-2px;right:-2px;background:var(--accent5);color:#fff;border-radius:50%;width:16px;height:16px;font-size:9px;display:flex;align-items:center;justify-content:center;font-weight:700"><?= min($c['unread'],9) ?></span>
          <?php endif; ?>
        </div>
        <div style="flex:1;min-width:0">
          <div style="display:flex;align-items:center;justify-content:space-between;gap:4px;margin-bottom:2px">
            <span style="font-size:14px;font-weight:<?= $c['unread']>0?'700':'500' ?>;color:<?= $c['unread']>0?'#fff':'var(--text-sec)' ?>;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($c['startup_name']) ?></span>
            <span style="font-size:10px;color:var(--muted);flex-shrink:0"><?= date('d/m', strtotime($c['last_time'])) ?></span>
          </div>
          <div style="font-size:12px;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
            <?= $c['last_sender']==$uid ? 'Vous: ' : '' ?><?= h(mb_substr($c['last_msg'],0,50)) ?>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ── ZONE MESSAGE ── -->
  <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius-lg);display:flex;flex-direction:column;overflow:hidden">
    
    <?php if ($partner): ?>
    <!-- Header conversation -->
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:14px">
      <div style="width:40px;height:40px;background:var(--surface);border:1px solid var(--border);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:700;color:var(--accent);flex-shrink:0">
        <?= mb_strtoupper(mb_substr($partner['startup_name'],0,1)) ?>
      </div>
      <div>
        <div style="font-size:15px;font-weight:600;color:#fff"><?= h($partner['startup_name']) ?></div>
        <div style="font-size:12px;color:var(--muted)"><?= h($partner['sector']??'') ?><?= $partner['city'] ? ' &bull; '.h($partner['city']) : '' ?></div>
      </div>
      <a href="directory.php" style="margin-left:auto;color:var(--muted);font-size:12px;text-decoration:none" title="Voir dans l'annuaire">&#128100; Profil</a>
    </div>

    <!-- Messages -->
    <div id="msg-list" style="flex:1;overflow-y:auto;padding:20px;display:flex;flex-direction:column;gap:4px;-webkit-overflow-scrolling:touch">
      <?php if (empty($messages)): ?>
        <div style="flex:1;display:flex;align-items:center;justify-content:center;text-align:center;color:var(--muted)">
          <div>
            <div style="font-size:36px;margin-bottom:10px">&#128075;</div>
            <p style="font-size:14px">Démarrez la conversation avec<br><strong style="color:var(--text-sec)"><?= h($partner['startup_name']) ?></strong></p>
          </div>
        </div>
      <?php endif; ?>

      <?php
      $prev_date = '';
      foreach ($messages as $m):
        $is_sent = $m['sender_id'] == $uid;
        $msg_date = date('d/m/Y', strtotime($m['created_at']));
        if ($msg_date !== $prev_date):
          $prev_date = $msg_date;
      ?>
        <div style="text-align:center;margin:12px 0 8px">
          <span style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:3px 10px;font-size:11px;color:var(--muted)">
            <?= $msg_date === date('d/m/Y') ? 'Aujourd\'hui' : $msg_date ?>
          </span>
        </div>
      <?php endif; ?>

      <div style="display:flex;flex-direction:column;align-items:<?= $is_sent?'flex-end':'flex-start' ?>">
        <div class="msg-bubble <?= $is_sent?'sent':'received' ?>">
          <?= nl2br(h($m['body'])) ?>
        </div>
        <div class="msg-meta" style="color:var(--muted);<?= $is_sent?'text-align:right':'text-align:left' ?>">
          <?= date('H:i', strtotime($m['created_at'])) ?>
          <?php if ($is_sent): ?> <?= $m['is_read'] ? '&#10003;&#10003;' : '&#10003;' ?><?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Input message -->
    <div style="padding:14px 16px;border-top:1px solid var(--border)">
      <form method="POST" style="display:flex;gap:10px;align-items:flex-end">
        <input type="hidden" name="receiver_id" value="<?= $partner['id'] ?>">
        <input type="hidden" name="send_msg" value="1">
        <textarea name="body" id="msg-input" placeholder="Votre message..." rows="1" required
          onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();this.form.submit();}"
          style="flex:1;padding:11px 14px;background:var(--surface);border:1.5px solid var(--border);border-radius:var(--radius);color:var(--text);font-family:var(--font);font-size:14px;outline:none;resize:none;max-height:120px;overflow-y:auto;line-height:1.5;min-height:44px"></textarea>
        <button type="submit" class="btn btn-primary" style="min-height:44px;padding:11px 18px;flex-shrink:0">
          Envoyer &#10148;
        </button>
      </form>
      <p style="font-size:11px;color:var(--muted);margin-top:6px">Entrée pour envoyer &bull; Maj+Entrée pour nouvelle ligne</p>
    </div>

    <?php else: ?>
    <!-- Aucune conversation sélectionnée -->
    <div style="flex:1;display:flex;align-items:center;justify-content:center;text-align:center;padding:40px;color:var(--muted)">
      <div>
        <div style="font-size:48px;margin-bottom:16px">&#128172;</div>
        <h3 style="color:var(--text-sec);margin-bottom:8px">Vos messages</h3>
        <p style="font-size:14px;margin-bottom:20px">Sélectionnez une conversation ou démarrez-en une nouvelle.</p>
        <button onclick="document.getElementById('modal-new').classList.add('open')" class="btn btn-primary">
          &#43; Nouveau message
        </button>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- MODAL NOUVEAU MESSAGE -->
<div class="modal-overlay" id="modal-new">
  <div class="modal" style="max-width:460px">
    <div class="modal-header">
      <div class="modal-title">Nouveau message</div>
      <button class="modal-close" onclick="document.getElementById('modal-new').classList.remove('open')">&#10005;</button>
    </div>
    <div class="modal-body">
      <?php if (empty($new_contacts)): ?>
        <p style="color:var(--muted);font-size:14px;text-align:center;padding:20px">
          Vous avez déjà échangé avec toutes les startups disponibles !<br>
          <a href="directory.php" style="color:var(--accent)">Voir l'annuaire</a>
        </p>
      <?php else: ?>
        <p style="font-size:14px;color:var(--muted);margin-bottom:16px">Choisissez une startup pour démarrer la conversation :</p>
        <div style="display:flex;flex-direction:column;gap:8px;max-height:400px;overflow-y:auto">
          <?php foreach ($new_contacts as $nc): ?>
          <a href="messages.php?to=<?= $nc['id'] ?>"
             onclick="document.getElementById('modal-new').classList.remove('open')"
             style="display:flex;align-items:center;gap:12px;padding:12px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);text-decoration:none;transition:all .15s"
             onmouseenter="this.style.borderColor='var(--accent)'"
             onmouseleave="this.style.borderColor='var(--border)'">
            <div style="width:38px;height:38px;background:var(--card);border:1px solid var(--border);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--accent);flex-shrink:0">
              <?= mb_strtoupper(mb_substr($nc['startup_name'],0,1)) ?>
            </div>
            <div>
              <div style="font-size:14px;font-weight:600;color:#fff"><?= h($nc['startup_name']) ?></div>
              <div style="font-size:12px;color:var(--muted)"><?= h($nc['sector']??'') ?><?= $nc['city']?' &bull; '.h($nc['city']):'' ?></div>
            </div>
            <span style="margin-left:auto;color:var(--accent);font-size:18px">&rarr;</span>
          </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
// Auto-scroll vers le bas des messages
var ml = document.getElementById('msg-list');
if (ml) ml.scrollTop = ml.scrollHeight;

// Auto-resize textarea
var inp = document.getElementById('msg-input');
if (inp) {
  inp.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
  });
  // Focus auto
  if (inp && window.innerWidth > 768) inp.focus();
}
document.getElementById('modal-new').addEventListener('click', function(e){ if(e.target===this) this.classList.remove('open'); });
</script>

<style>
/* Layout messages responsive */
@media (max-width: 768px) {
  .msg-layout { grid-template-columns: 1fr !important; }
}
</style>

<?php include 'footer.php'; ?>
