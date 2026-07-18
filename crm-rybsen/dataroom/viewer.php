<?php
/**
 * RYBSEN DATA ROOM — Visionneuse sécurisée
 * PDF rendus en canvas (pdf.js) : pas de bouton téléchargement, pas de fichier exposé.
 * Filigrane dynamique nominatif sur chaque page.
 */
require_once __DIR__ . '/_dr.php';
require_once __DIR__ . '/_layout.php';

$db  = getDB();
$acc = drRequireNda($db);

$id = intval($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM dataroom_documents WHERE id=? AND actif=1");
$stmt->execute([$id]);
$doc = $stmt->fetch();
if (!$doc) { header('Location: /dataroom/room.php'); exit; }

// Document masqué pour cet investisseur → retour à la salle
if (drDocRestricted($db, intval($acc['id']), $id)) {
    header('Location: /dataroom/room.php');
    exit;
}

drLog($db, intval($acc['id']), 'vue_document', $id);

$titre = ($DR_LANG === 'en' && $doc['titre_en']) ? $doc['titre_en'] : $doc['titre'];
$isImage = str_starts_with($doc['mime'], 'image/');
$isVideo = str_starts_with($doc['mime'], 'video/');
$wmText = trim(($acc['prenom'] ?? '') . ' ' . $acc['nom']) . ' · ' . $acc['email'] . ' · ' . date('d/m/Y');

drHead($titre, true);
?>
<style>
.viewer-bar {
  display:flex; align-items:center; gap:14px; padding:12px 26px;
  background:#fff; border-bottom:1px solid var(--line); flex-wrap:wrap;
}
.viewer-stage {
  position:relative; padding:26px 12px 60px; min-height:70vh;
  display:flex; flex-direction:column; align-items:center; gap:18px;
  background:#39454E;
  user-select:none; -webkit-user-select:none;
}
.viewer-stage canvas, .viewer-stage img {
  max-width:min(920px, 96vw); width:100%; height:auto;
  box-shadow:0 6px 30px rgba(0,0,0,.35); border-radius:4px; background:#fff;
  pointer-events:none;
}
.viewer-stage video {
  max-width:min(920px, 96vw); width:100%; height:auto;
  box-shadow:0 6px 30px rgba(0,0,0,.35); border-radius:4px; background:#000;
}
.wm-overlay {
  position:absolute; inset:0; overflow:hidden; pointer-events:none; z-index:5;
  display:flex; flex-wrap:wrap; align-content:space-around; justify-content:space-around;
}
.wm-overlay span {
  transform:rotate(-30deg); white-space:nowrap;
  font-size:15px; font-weight:700; color:rgba(255,255,255,.14);
  padding:52px 34px; letter-spacing:1px;
}
@media print { body { display:none !important; } }
</style>

<div class="viewer-bar">
  <a href="/dataroom/room.php" style="text-decoration:none;font-size:13.5px;font-weight:700;color:var(--cyan-dark)"><?= e(t('back_room')) ?></a>
  <div style="flex:1;min-width:180px;font-weight:800;font-size:14.5px;color:var(--navy-2)">
    <?= $isImage ? '🖼' : ($isVideo ? '🎬' : '📄') ?> <?= e($titre) ?>
    <span style="font-weight:400;color:var(--muted);font-size:12px">· <?= e($doc['version']) ?></span>
  </div>
  <span style="font-size:11px;background:#FEF3E2;color:#92600E;border:1px solid #F3D9AC;border-radius:20px;padding:4px 12px;font-weight:700">
    🔒 <?= e(t('viewer_notice')) ?>
  </span>
</div>

<div class="viewer-stage" id="stage" oncontextmenu="return false">
  <div class="wm-overlay" id="wm"></div>
  <?php if ($isImage): ?>
    <img src="/dataroom/file.php?id=<?= $id ?>" alt="" draggable="false">
  <?php elseif ($isVideo): ?>
    <video controls controlsList="nodownload noremoteplayback" disablePictureInPicture playsinline preload="metadata">
      <source src="/dataroom/file.php?id=<?= $id ?>" type="<?= e($doc['mime']) ?>">
    </video>
  <?php else: ?>
    <div id="pdf-status" style="color:rgba(255,255,255,.7);font-size:13px;padding:40px">⏳ …</div>
  <?php endif; ?>
</div>

<script>
// Filigrane nominatif répété
(function(){
  const wm = document.getElementById('wm');
  const txt = <?= json_encode(t('confidential') . ' — ' . $wmText) ?>;
  for (let i = 0; i < 60; i++) {
    const s = document.createElement('span');
    s.textContent = txt;
    wm.appendChild(s);
  }
})();
document.addEventListener('keydown', e => {
  if ((e.ctrlKey || e.metaKey) && ['s','p'].includes(e.key.toLowerCase())) e.preventDefault();
});
</script>

<?php if (!$isImage && !$isVideo): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
(async function(){
  const status = document.getElementById('pdf-status');
  const stage  = document.getElementById('stage');
  try {
    pdfjsLib.GlobalWorkerOptions.workerSrc =
      'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    const pdf = await pdfjsLib.getDocument({ url: '/dataroom/file.php?id=<?= $id ?>' }).promise;
    status.remove();
    const scale = Math.min(2, (window.devicePixelRatio || 1) * 1.4);
    for (let p = 1; p <= pdf.numPages; p++) {
      const page = await pdf.getPage(p);
      const vp = page.getViewport({ scale });
      const canvas = document.createElement('canvas');
      canvas.width = vp.width; canvas.height = vp.height;
      stage.appendChild(canvas);
      await page.render({ canvasContext: canvas.getContext('2d'), viewport: vp }).promise;
    }
  } catch (err) {
    status.textContent = '⚠️ ' + err.message;
  }
})();
</script>
<?php endif; ?>
<?php drFoot(); ?>
