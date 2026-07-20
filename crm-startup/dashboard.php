<?php
require_once 'config.php';
requireLogin();
$page_title = 'Programmes';

$db  = getDB();
$uid = (int)$_SESSION['fm_user_id'];
$msg = $_GET['msg'] ?? '';

// Auto-archivage des programmes expirés depuis plus de 3 jours
$db->exec("UPDATE fm_programs SET status='archived'
           WHERE status='active' AND deadline_date IS NOT NULL
           AND deadline_date < DATE_SUB(CURDATE(), INTERVAL 3 DAY)");

// Programmes actifs, urgents d'abord puis par deadline croissante ; favoris de l'utilisateur en une seule requête
$stmt = $db->prepare("
    SELECT p.*, (f.id IS NOT NULL) AS is_favorite
    FROM fm_programs p
    LEFT JOIN fm_favorites f ON f.program_id = p.id AND f.user_id = ?
    WHERE p.status = 'active'
    ORDER BY
      CASE WHEN p.deadline_date IS NOT NULL AND p.deadline_date >= CURDATE() THEN 0 ELSE 1 END,
      p.deadline_date ASC,
      p.name ASC
");
$stmt->execute([$uid]);
$programs = $stmt->fetchAll();

// KPIs
$total   = count($programs);
$urgent  = 0;
$tn      = 0;
$sectors_set = [];
foreach ($programs as $p) {
    $dt = $p['deadline_date'] ? calcDeadlineType($p['deadline_date']) : ($p['deadline_type'] ?: 'open');
    if ($dt === 'urgent') $urgent++;
    if (!empty($p['tunisia_focus'])) $tn++;
    foreach (array_filter(array_map('trim', explode(',', $p['sectors'] ?? ''))) as $s) {
        $sectors_set[$s] = true;
    }
}
$all_sectors = array_keys($sectors_set);
sort($all_sectors);

$type_labels = [
    'fund'        => 'Fonds VC',
    'accelerator' => 'Accélérateur',
    'grant'       => 'Subvention',
    'competition' => 'Compétition',
    'incubator'   => 'Incubateur',
];

// Notifications : soumissions de l'utilisateur traitées ces 7 derniers jours
$recent = $db->prepare("SELECT name, status FROM fm_submissions
    WHERE user_id = ? AND status != 'pending'
    AND reviewed_at IS NOT NULL AND reviewed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY reviewed_at DESC LIMIT 3");
$recent->execute([(int)$_SESSION['fm_user_id']]);
$recent_reviews = $recent->fetchAll();

include 'header.php';
?>

<div class="section-head" style="margin-bottom:20px">
  <div>
    <h1>Programmes de financement</h1>
    <p style="color:var(--muted);font-size:14px;margin-top:4px">Fonds, subventions, accélérateurs et compétitions ouverts aux startups tunisiennes.</p>
  </div>
  <a href="submit.php" class="btn btn-primary">&#43; Soumettre un programme</a>
</div>

<script>window._csrf = '<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>';</script>

<?php if ($msg === 'access_denied'): ?>
  <div class="alert alert-warn">&#9888; Accès réservé aux administrateurs.</div>
<?php endif; ?>

<?php foreach ($recent_reviews as $rr): ?>
  <div class="alert <?= $rr['status'] === 'approved' ? 'alert-success' : 'alert-info' ?>" style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap">
    <span>
      <?= $rr['status'] === 'approved' ? '&#127881; Votre soumission <strong>' . h($rr['name']) . '</strong> a été approuvée et publiée !' : 'Votre soumission <strong>' . h($rr['name']) . '</strong> a été examinée.' ?>
    </span>
    <a href="my_submissions.php" style="color:inherit;font-size:13px;white-space:nowrap">Voir mes soumissions &rarr;</a>
  </div>
<?php endforeach; ?>

<!-- KPIs -->
<div class="kpi-row">
  <div class="kpi">
    <div class="kpi-value" style="color:var(--accent)"><?= $total ?></div>
    <div class="kpi-label">Programmes ouverts</div>
  </div>
  <div class="kpi">
    <div class="kpi-value" style="color:var(--accent5)"><?= $urgent ?></div>
    <div class="kpi-label">Deadline &lt; 7 jours</div>
  </div>
  <div class="kpi">
    <div class="kpi-value" style="color:var(--accent3)"><?= $tn ?></div>
    <div class="kpi-label">Focus Tunisie</div>
  </div>
  <div class="kpi">
    <div class="kpi-value" style="color:var(--accent2)"><?= count($all_sectors) ?></div>
    <div class="kpi-label">Secteurs couverts</div>
  </div>
</div>

<?php if ($urgent > 0): ?>
<div class="urgency-strip">
  <span class="urgency-dot"></span>
  <span class="urgency-text"><strong><?= $urgent ?></strong> programme(s) avec une deadline dans moins de 7 jours — ne les ratez pas.</span>
</div>
<?php endif; ?>

<!-- FILTRES -->
<div class="card" style="margin-bottom:20px;padding:16px">
  <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
    <input type="search" id="f-search" placeholder="&#128269; Rechercher un programme, une organisation..."
      style="flex:1;min-width:220px;padding:10px 14px;background:var(--surface);border:1.5px solid var(--border);border-radius:var(--radius);color:var(--text);font-family:var(--font);font-size:14px;outline:none;min-height:44px">
    <select id="f-type" style="padding:10px 14px;background:var(--surface);border:1.5px solid var(--border);border-radius:var(--radius);color:var(--text);font-size:13px;outline:none;min-height:44px;-webkit-appearance:none">
      <option value="">Tous les types</option>
      <?php foreach ($type_labels as $k => $lbl): ?>
      <option value="<?= $k ?>"><?= h($lbl) ?></option>
      <?php endforeach; ?>
    </select>
    <select id="f-deadline" style="padding:10px 14px;background:var(--surface);border:1.5px solid var(--border);border-radius:var(--radius);color:var(--text);font-size:13px;outline:none;min-height:44px;-webkit-appearance:none">
      <option value="">Toutes les deadlines</option>
      <option value="urgent">&lt; 7 jours</option>
      <option value="soon">&lt; 30 jours</option>
      <option value="open">Rolling / ouvert</option>
    </select>
    <label style="display:flex;align-items:center;gap:7px;font-size:13px;color:var(--text-sec);cursor:pointer;white-space:nowrap">
      <input type="checkbox" id="f-tn" style="accent-color:var(--accent);width:16px;height:16px">
      &#127481;&#127475; Focus Tunisie
    </label>
    <label style="display:flex;align-items:center;gap:7px;font-size:13px;color:var(--text-sec);cursor:pointer;white-space:nowrap">
      <input type="checkbox" id="f-fav" style="accent-color:var(--accent);width:16px;height:16px">
      &#9733; Mes favoris
    </label>
  </div>
  <?php if ($all_sectors): ?>
  <div class="sector-pills" style="margin-top:12px">
    <button type="button" class="sector-pill active" data-sector="">Tous secteurs</button>
    <?php foreach ($all_sectors as $s): ?>
    <button type="button" class="sector-pill" data-sector="<?= h(mb_strtolower($s)) ?>"><?= h($s) ?></button>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<div class="section-head">
  <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
    <span class="section-title">PROGRAMMES</span>
    <span class="count-pill" id="prog-count"><?= $total ?> résultat(s)</span>
  </div>
  <div style="display:flex;align-items:center;gap:10px">
    <select id="f-sort" style="padding:8px 12px;background:var(--surface);border:1.5px solid var(--border);border-radius:var(--radius);color:var(--text);font-size:13px;outline:none;min-height:40px;-webkit-appearance:none">
      <option value="deadline">Trier : Deadline</option>
      <option value="name">Trier : Alphabétique</option>
      <option value="recent">Trier : Plus récent</option>
    </select>
    <div class="view-toggle" role="group" aria-label="Mode d'affichage">
      <button type="button" id="view-grid" class="active" title="Vue grille" aria-pressed="true">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M2 2h5v5H2zm7 0h5v5H9zM2 9h5v5H2zm7 0h5v5H9z"/></svg>
      </button>
      <button type="button" id="view-list" title="Vue liste" aria-pressed="false">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M2 3h12v2H2zm0 4h12v2H2zm0 4h12v2H2z"/></svg>
      </button>
    </div>
  </div>
</div>

<?php if (empty($programs)): ?>
  <div class="card" style="text-align:center;padding:48px 20px">
    <div style="font-size:40px;margin-bottom:12px">&#128188;</div>
    <h3 style="margin-bottom:8px">Aucun programme actif pour le moment</h3>
    <p style="font-size:14px;color:var(--muted)">Vous connaissez un programme non référencé ? <a href="submit.php" style="color:var(--accent)">Soumettez-le ici</a>.</p>
  </div>
<?php else: ?>

<div id="prog-grid" class="grid-cards" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px">
  <?php foreach ($programs as $p):
    $dt = $p['deadline_date'] ? calcDeadlineType($p['deadline_date']) : ($p['deadline_type'] ?: 'open');
    $days_left = null;
    if ($p['deadline_date'] && strtotime($p['deadline_date']) >= strtotime(date('Y-m-d'))) {
        $days_left = (int)ceil((strtotime($p['deadline_date']) - time()) / 86400);
    }
    $ptype = $p['type'] ?: 'grant';
    $p_sectors = array_filter(array_map('trim', explode(',', $p['sectors'] ?? '')));
    $search_blob = mb_strtolower($p['name'] . ' ' . $p['organisation'] . ' ' . ($p['description'] ?? '') . ' ' . ($p['geo'] ?? '') . ' ' . implode(' ', $p_sectors));
    $sort_deadline_ts = $p['deadline_date'] ? strtotime($p['deadline_date']) : 9999999999;
    $is_fav = !empty($p['is_favorite']);
  ?>
  <div class="card clickable prog-card item-card"
       data-search="<?= h($search_blob) ?>"
       data-type="<?= h($ptype) ?>"
       data-deadline="<?= h($dt) ?>"
       data-tn="<?= !empty($p['tunisia_focus']) ? '1' : '0' ?>"
       data-fav="<?= $is_fav ? '1' : '0' ?>"
       data-sectors="<?= h(mb_strtolower(implode(',', $p_sectors))) ?>"
       data-sort-name="<?= h(mb_strtolower($p['name'])) ?>"
       data-sort-deadline="<?= $sort_deadline_ts ?>"
       data-sort-created="<?= strtotime($p['created_at']) ?>"
       style="display:flex;flex-direction:column;gap:10px;position:relative">
    <button type="button" class="btn-favorite" data-program-id="<?= (int)$p['id'] ?>"
      style="position:absolute;top:14px;right:14px;background:none;border:none;cursor:pointer;font-size:20px;line-height:1;color:<?= $is_fav ? 'var(--accent4)' : 'var(--border-light)' ?>;padding:4px;z-index:1"
      title="<?= $is_fav ? 'Retirer des favoris' : 'Ajouter aux favoris' ?>">
      <?= $is_fav ? '&#9733;' : '&#9734;' ?>
    </button>
    <div class="card-body" style="display:flex;align-items:flex-start;gap:12px">
      <div class="card-icon" style="font-size:28px;flex-shrink:0;line-height:1.2"><?= $p['emoji'] ? h($p['emoji']) : '&#128176;' ?></div>
      <div class="card-title-block" style="flex:1;min-width:0;padding-right:24px">
        <a href="program.php?id=<?= (int)$p['id'] ?>" style="font-size:15.5px;font-weight:700;color:#fff;line-height:1.35;text-decoration:none"><?= h($p['name']) ?></a>
        <div style="font-size:12.5px;color:var(--muted);margin-top:2px"><?= h($p['organisation']) ?></div>
      </div>
      <div class="card-badges" style="display:flex;gap:6px;flex-wrap:wrap">
        <span class="badge badge-<?= h($ptype) ?>"><?= h($type_labels[$ptype] ?? ucfirst($ptype)) ?></span>
        <?php if (!empty($p['tunisia_focus'])): ?><span class="badge badge-tn">&#127481;&#127475; Tunisie</span><?php endif; ?>
        <?php foreach (array_slice($p_sectors, 0, 3) as $s): ?>
          <span class="badge badge-tn"><?= h($s) ?></span>
        <?php endforeach; ?>
        <?php if (count($p_sectors) > 3): ?><span class="badge badge-tn">+<?= count($p_sectors) - 3 ?></span><?php endif; ?>
      </div>
    </div>
    <?php if ($p['description']): ?>
    <p class="card-desc" style="font-size:13px;color:var(--text-sec);line-height:1.55;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden"><?= h($p['description']) ?></p>
    <?php endif; ?>
    <div class="card-meta-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:12px;margin-top:auto">
      <?php if ($p['amount']): ?>
      <div>
        <div style="font-size:10px;color:var(--subtle);text-transform:uppercase;letter-spacing:1px;font-family:var(--mono)">Montant</div>
        <div style="font-family:var(--mono);color:var(--accent);font-weight:600"><?= h($p['amount']) ?></div>
      </div>
      <?php endif; ?>
      <?php if ($p['geo']): ?>
      <div>
        <div style="font-size:10px;color:var(--subtle);text-transform:uppercase;letter-spacing:1px;font-family:var(--mono)">Zone</div>
        <div style="color:var(--text-sec)"><?= h($p['geo']) ?></div>
      </div>
      <?php endif; ?>
    </div>
    <div class="card-footer" style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding-top:10px;border-top:1px solid var(--border)">
      <span class="dl-<?= h($dt) ?>">
        <?php if ($days_left !== null): ?>
          &#9200; <?= $days_left <= 0 ? "Aujourd'hui" : 'J-' . $days_left ?> &middot; <?= date('d/m/Y', strtotime($p['deadline_date'])) ?>
        <?php else: ?>
          <?= h($p['deadline'] ?: 'Rolling') ?>
        <?php endif; ?>
      </span>
      <div style="display:flex;gap:6px">
        <a href="program.php?id=<?= (int)$p['id'] ?>" class="btn btn-secondary btn-sm">Détails</a>
        <?php if ($p['link']): ?>
        <a href="<?= h($p['link']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-sm">Candidater &rarr;</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div id="no-results" class="card" style="display:none;text-align:center;padding:40px 20px">
  <div style="font-size:32px;margin-bottom:10px">&#128269;</div>
  <p style="font-size:14px;color:var(--muted)">Aucun programme ne correspond à vos filtres.</p>
</div>

<script>
(function() {
  var search   = document.getElementById('f-search');
  var typeSel  = document.getElementById('f-type');
  var dlSel    = document.getElementById('f-deadline');
  var tnChk    = document.getElementById('f-tn');
  var favChk   = document.getElementById('f-fav');
  var sortSel  = document.getElementById('f-sort');
  var pills    = document.querySelectorAll('.sector-pill');
  var cards    = document.querySelectorAll('.prog-card');
  var countEl  = document.getElementById('prog-count');
  var noRes    = document.getElementById('no-results');
  var grid     = document.getElementById('prog-grid');
  var sector   = '';

  function applyFilters() {
    var q  = search.value.trim().toLowerCase();
    var t  = typeSel.value;
    var dl = dlSel.value;
    var tn = tnChk.checked;
    var fav = favChk.checked;
    var visible = 0;

    cards.forEach(function(c) {
      var ok = true;
      if (q && c.dataset.search.indexOf(q) === -1) ok = false;
      if (ok && t && c.dataset.type !== t) ok = false;
      if (ok && dl) {
        if (dl === 'urgent' && c.dataset.deadline !== 'urgent') ok = false;
        if (dl === 'soon' && c.dataset.deadline !== 'urgent' && c.dataset.deadline !== 'soon') ok = false;
        if (dl === 'open' && c.dataset.deadline !== 'open') ok = false;
      }
      if (ok && tn && c.dataset.tn !== '1') ok = false;
      if (ok && fav && c.dataset.fav !== '1') ok = false;
      if (ok && sector && c.dataset.sectors.split(',').indexOf(sector) === -1) ok = false;
      c.style.display = ok ? '' : 'none';
      if (ok) visible++;
    });

    countEl.textContent = visible + ' résultat(s)';
    noRes.style.display = visible === 0 ? '' : 'none';
    grid.style.display  = visible === 0 ? 'none' : '';
  }

  function applySort() {
    var key = sortSel.value;
    var attr = key === 'name' ? 'sortName' : (key === 'recent' ? 'sortCreated' : 'sortDeadline');
    var sorted = Array.prototype.slice.call(cards).sort(function(a, b) {
      if (attr === 'sortName') return a.dataset[attr].localeCompare(b.dataset[attr]);
      var av = parseFloat(a.dataset[attr]), bv = parseFloat(b.dataset[attr]);
      return key === 'recent' ? bv - av : av - bv;
    });
    sorted.forEach(function(c) { grid.appendChild(c); });
  }

  search.addEventListener('input', applyFilters);
  typeSel.addEventListener('change', applyFilters);
  dlSel.addEventListener('change', applyFilters);
  tnChk.addEventListener('change', applyFilters);
  favChk.addEventListener('change', applyFilters);
  sortSel.addEventListener('change', applySort);
  pills.forEach(function(p) {
    p.addEventListener('click', function() {
      pills.forEach(function(x) { x.classList.remove('active'); });
      p.classList.add('active');
      sector = p.dataset.sector;
      applyFilters();
    });
  });

  // Favoris : toggle AJAX sans recharger la page
  document.querySelectorAll('.btn-favorite').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      var fd = new FormData();
      fd.append('program_id', btn.dataset.programId);
      fd.append('csrf_token', window._csrf);
      fetch('api_favorites.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(d) {
          var card = btn.closest('.prog-card');
          card.dataset.fav = d.favorited ? '1' : '0';
          btn.innerHTML = d.favorited ? '&#9733;' : '&#9734;';
          btn.style.color = d.favorited ? 'var(--accent4)' : 'var(--border-light)';
          if (favChk.checked) applyFilters();
        })
        .catch(function() {});
    });
  });
})();
</script>

<script>
// Bascule vue grille / liste — persistée par utilisateur dans ce navigateur
(function() {
  var gridBtn = document.getElementById('view-grid');
  var listBtn = document.getElementById('view-list');
  var container = document.body;
  var KEY = 'stn_view_dashboard';

  function setView(mode) {
    container.classList.toggle('list-view', mode === 'list');
    gridBtn.classList.toggle('active', mode === 'grid');
    listBtn.classList.toggle('active', mode === 'list');
    gridBtn.setAttribute('aria-pressed', mode === 'grid');
    listBtn.setAttribute('aria-pressed', mode === 'list');
    localStorage.setItem(KEY, mode);
  }
  gridBtn.addEventListener('click', function() { setView('grid'); });
  listBtn.addEventListener('click', function() { setView('list'); });
  setView(localStorage.getItem(KEY) === 'list' ? 'list' : 'grid');
})();
</script>
<?php endif; ?>

<?php include 'footer.php'; ?>
