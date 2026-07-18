<?php
/** RYBSEN DATA ROOM — Salle de consultation des documents */
require_once __DIR__ . '/_dr.php';
require_once __DIR__ . '/_layout.php';

$db  = getDB();
$acc = drRequireNda($db);
$flash = '';
$flashErr = '';

// ── Envoi d'une suggestion / question ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['suggestion'])) {
    $csrfOk = hash_equals($_SESSION['dr_csrf'] ?? '', $_POST['csrf'] ?? '');
    $msg    = trim($_POST['suggestion']);
    $docId  = intval($_POST['doc_id'] ?? 0) ?: null;
    if (!$csrfOk || $msg === '') {
        $flashErr = t('suggest_empty');
    } else {
        if ($docId) { // vérifier que le doc existe
            $chk = $db->prepare("SELECT id FROM dataroom_documents WHERE id=? AND actif=1");
            $chk->execute([$docId]);
            if (!$chk->fetch()) $docId = null;
        }
        $db->prepare("INSERT INTO dataroom_suggestions (acces_id, document_id, message) VALUES (?,?,?)")
           ->execute([$acc['id'], $docId, substr($msg, 0, 5000)]);
        drLog($db, intval($acc['id']), 'suggestion', $docId, substr($msg, 0, 200));
        $flash = t('suggest_ok');
    }
}

// ── Documents actifs visibles par CET investisseur (hors restrictions) ──
$docs = drVisibleDocs($db, intval($acc['id']));

$catMeta = [
  'Pitch & Vision'        => ['🎯', 'Pitch & Vision'],
  'Produit & Technologie' => ['⚙️', 'Product & Technology'],
  'Financier'             => ['📊', 'Financials'],
  'Juridique'             => ['⚖️', 'Legal'],
  'Équipe'                => ['👥', 'Team'],
  'Marché & Traction'     => ['📈', 'Market & Traction'],
  'Vidéo'                 => ['🎬', 'Video'],
  'Autre'                 => ['📁', 'Other'],
];
$grouped = [];
foreach ($docs as $d) {
    $cat = isset($catMeta[$d['categorie']]) ? $d['categorie'] : 'Autre';
    $grouped[$cat][] = $d;
}
// Ordonner selon $catMeta
$ordered = [];
foreach ($catMeta as $cat => $_) if (isset($grouped[$cat])) $ordered[$cat] = $grouped[$cat];

function fmtSize($b) {
    $b = intval($b);
    if ($b >= 1048576) return round($b / 1048576, 1) . ' Mo';
    if ($b >= 1024) return round($b / 1024) . ' Ko';
    return $b . ' o';
}

drHead(t('room_title'));
?>
<!-- Bandeau de bienvenue -->
<div class="dr-card" style="padding:26px 32px;margin-bottom:26px;display:flex;flex-wrap:wrap;gap:18px;align-items:center;background:linear-gradient(120deg,var(--navy) 0%,var(--navy-2) 100%);border:none">
  <div style="flex:1;min-width:240px">
    <div style="font-size:20px;font-weight:800;color:#fff">
      <?= e(t('welcome')) ?>, <?= e(trim(($acc['prenom'] ?? '') . ' ' . $acc['nom'])) ?>
      <?php if ($acc['societe']): ?><span style="color:var(--cyan)"> · <?= e($acc['societe']) ?></span><?php endif; ?>
    </div>
    <div style="font-size:12.5px;color:rgba(255,255,255,.65);margin-top:6px">
      ✅ <?= e(t('nda_signed_on')) ?> <?= $acc['nda_date'] ? date('d/m/Y H:i', strtotime($acc['nda_date'])) : '—' ?>
      <?php if ($acc['date_expiration']): ?>
        &nbsp;·&nbsp; ⏳ <?= e(t('expires_on')) ?> <?= date('d/m/Y', strtotime($acc['date_expiration'])) ?>
      <?php endif; ?>
    </div>
  </div>
  <div style="background:rgba(23,179,204,.18);border:1px solid var(--cyan);border-radius:10px;padding:10px 18px;color:var(--cyan);font-weight:800;font-size:13px">
    <?= count($docs) ?> <?= e(t('docs')) ?>
  </div>
</div>

<?php if ($flash): ?><div class="dr-alert ok"><?= e($flash) ?></div><?php endif; ?>
<?php if ($flashErr): ?><div class="dr-alert err"><?= e($flashErr) ?></div><?php endif; ?>

<?php if (!$docs): ?>
  <div class="dr-card" style="padding:60px;text-align:center;color:var(--muted)">
    <div style="font-size:40px;margin-bottom:12px">🗂</div>
    <?= e(t('no_docs')) ?>
  </div>
<?php endif; ?>

<?php foreach ($ordered as $cat => $items): [$icon, $catEn] = $catMeta[$cat]; ?>
<div class="dr-card" style="margin-bottom:22px;overflow:hidden">
  <div style="padding:15px 24px;border-bottom:1px solid var(--line);display:flex;align-items:center;gap:10px;background:#FAFCFD">
    <span style="font-size:18px"><?= $icon ?></span>
    <span style="font-weight:800;font-size:14.5px;color:var(--navy-2)">
      <?= e($DR_LANG === 'en' ? $catEn : $cat) ?>
    </span>
    <span style="margin-left:auto;font-size:11.5px;color:var(--muted)"><?= count($items) ?> <?= e(t('docs')) ?></span>
  </div>
  <?php foreach ($items as $d):
      $titre = ($DR_LANG === 'en' && $d['titre_en']) ? $d['titre_en'] : $d['titre']; ?>
  <div style="display:flex;align-items:center;gap:16px;padding:14px 24px;border-bottom:1px solid #F0F4F6;flex-wrap:wrap">
    <div style="width:38px;height:38px;border-radius:9px;background:#E8F8FB;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0">
      <?= str_starts_with($d['mime'], 'image/') ? '🖼' : (str_starts_with($d['mime'], 'video/') ? '🎬' : '📄') ?>
    </div>
    <div style="flex:1;min-width:200px">
      <div style="font-weight:700;font-size:14px;color:var(--ink)"><?= e($titre) ?></div>
      <div style="font-size:11.5px;color:var(--muted)">
        <?= e($d['version']) ?> · <?= fmtSize($d['taille_octets']) ?>
        <?php if ($d['description']): ?> · <?= e(mb_substr($d['description'], 0, 90)) ?><?php endif; ?>
      </div>
    </div>
    <a class="btn-dr" style="padding:9px 20px;font-size:13px" href="/dataroom/viewer.php?id=<?= intval($d['id']) ?>">
      👁 <?= e(t('view')) ?>
    </a>
  </div>
  <?php endforeach; ?>
</div>
<?php endforeach; ?>

<!-- Question / Suggestion -->
<div class="dr-card" style="padding:28px 32px;margin-top:8px">
  <div style="font-weight:800;font-size:15.5px;color:var(--navy-2);margin-bottom:16px">💬 <?= e(t('suggest_title')) ?></div>
  <form method="POST">
    <input type="hidden" name="csrf" value="<?= e($_SESSION['dr_csrf'] ?? '') ?>">
    <div class="dr-field">
      <label><?= e(t('suggest_doc')) ?></label>
      <select name="doc_id">
        <option value="0"><?= e(t('suggest_general')) ?></option>
        <?php foreach ($docs as $d): ?>
          <option value="<?= intval($d['id']) ?>"><?= e($d['titre']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="dr-field">
      <label><?= e(t('suggest_title')) ?></label>
      <textarea name="suggestion" rows="4" required placeholder="<?= e(t('suggest_ph')) ?>"></textarea>
    </div>
    <button type="submit" class="btn-dr"><?= e(t('suggest_send')) ?> →</button>
  </form>
</div>
<?php drFoot(); ?>
