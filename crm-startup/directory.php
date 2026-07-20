<?php
require_once 'config.php';
requireLogin();
$page_title = 'Annuaire';

$db  = getDB();
$uid = (int)$_SESSION['fm_user_id'];

$stmt = $db->prepare("
    SELECT id, startup_name, sector, stage, city, website, elevator_pitch, founded_year, looking_for, last_activity, created_at
    FROM fm_users
    WHERE role = 'startup' AND is_active = 1 AND id != ?
    ORDER BY startup_name ASC
");
$stmt->execute([$uid]);
$startups = $stmt->fetchAll();

function isOnlineNow(?string $lastActivity): bool {
    return $lastActivity && strtotime($lastActivity) >= time() - 300; // 5 min
}

$stages_list = [
    'idee' => 'Idée', 'bootstrapping' => 'Bootstrapping', 'pre-seed' => 'Pre-Seed',
    'seed' => 'Seed', 'series-a' => 'Série A', 'series-b' => 'Série B',
    'series-c' => 'Série C', 'growth' => 'Growth', 'pre-ipo' => 'Pré-IPO',
];

// Secteurs présents dans l'annuaire (pour les filtres)
$sectors_set = [];
foreach ($startups as $s) {
    foreach (array_filter(array_map('trim', explode(',', $s['sector'] ?? ''))) as $sec) {
        $sectors_set[$sec] = true;
    }
}
$all_sectors = array_keys($sectors_set);
sort($all_sectors);

include 'header.php';
?>

<div class="section-head" style="margin-bottom:20px">
  <div>
    <h1>Annuaire des startups</h1>
    <p style="color:var(--muted);font-size:14px;margin-top:4px">Découvrez l'écosystème et connectez-vous avec d'autres fondateurs.</p>
  </div>
  <div style="display:flex;align-items:center;gap:10px">
    <span class="count-pill" id="dir-count"><?= count($startups) ?> startup(s)</span>
    <select id="d-sort" style="padding:8px 12px;background:var(--surface);border:1.5px solid var(--border);border-radius:var(--radius);color:var(--text);font-size:13px;outline:none;min-height:40px;-webkit-appearance:none">
      <option value="name">Trier : Alphabétique</option>
      <option value="online">Trier : En ligne d'abord</option>
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

<!-- FILTRES -->
<div class="card" style="margin-bottom:20px;padding:16px">
  <input type="search" id="d-search" placeholder="&#128269; Rechercher par nom, ville, secteur..."
    style="width:100%;padding:10px 14px;background:var(--surface);border:1.5px solid var(--border);border-radius:var(--radius);color:var(--text);font-family:var(--font);font-size:14px;outline:none;min-height:44px">
  <?php if ($all_sectors): ?>
  <div class="sector-pills" style="margin-top:12px">
    <button type="button" class="sector-pill active" data-sector="">Tous secteurs</button>
    <?php foreach ($all_sectors as $s): ?>
    <button type="button" class="sector-pill" data-sector="<?= h(mb_strtolower($s)) ?>"><?= h($s) ?></button>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php if (empty($startups)): ?>
  <div class="card" style="text-align:center;padding:48px 20px">
    <div style="font-size:40px;margin-bottom:12px">&#127970;</div>
    <h3 style="margin-bottom:8px">L'annuaire est encore vide</h3>
    <p style="font-size:14px;color:var(--muted)">Les startups activées apparaîtront ici au fur et à mesure.</p>
  </div>
<?php else: ?>

<div id="dir-grid" class="grid-cards" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px">
  <?php foreach ($startups as $s):
    $s_sectors = array_filter(array_map('trim', explode(',', $s['sector'] ?? '')));
    $blob = mb_strtolower($s['startup_name'] . ' ' . ($s['city'] ?? '') . ' ' . implode(' ', $s_sectors) . ' ' . ($s['elevator_pitch'] ?? ''));
    $online = isOnlineNow($s['last_activity']);
  ?>
  <div class="card dir-card item-card"
       data-search="<?= h($blob) ?>"
       data-sectors="<?= h(mb_strtolower(implode(',', $s_sectors))) ?>"
       data-sort-name="<?= h(mb_strtolower($s['startup_name'])) ?>"
       data-sort-online="<?= $online ? 1 : 0 ?>"
       data-sort-created="<?= strtotime($s['created_at']) ?>"
       style="display:flex;flex-direction:column;gap:12px">
    <div class="card-body" style="display:flex;align-items:center;gap:12px">
      <span class="avatar-wrap">
        <span style="width:46px;height:46px;flex-shrink:0;background:linear-gradient(135deg,var(--accent),var(--accent2));border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;color:#0d1117">
          <?= mb_strtoupper(mb_substr($s['startup_name'], 0, 1)) ?>
        </span>
        <span class="presence-dot <?= $online ? 'online' : 'offline' ?>" title="<?= $online ? 'En ligne' : 'Hors ligne' ?>"></span>
      </span>
      <div class="card-title-block" style="flex:1;min-width:0">
        <div style="font-size:15px;font-weight:700;color:#fff;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($s['startup_name']) ?></div>
        <div style="font-size:12px;color:var(--muted)">
          <?= h($s['city'] ?: 'Tunisie') ?><?= $s['founded_year'] ? ' &middot; ' . (int)$s['founded_year'] : '' ?>
          <?= $online ? ' &middot; <span style="color:var(--accent3)">En ligne</span>' : '' ?>
        </div>
      </div>
      <div class="card-badges" style="display:flex;gap:6px;flex-wrap:wrap">
        <?php if ($s['stage'] && isset($stages_list[$s['stage']])): ?>
          <span class="badge badge-fund"><?= h($stages_list[$s['stage']]) ?></span>
        <?php endif; ?>
        <?php foreach (array_slice($s_sectors, 0, 3) as $sec): ?>
          <span class="badge badge-accelerator"><?= h($sec) ?></span>
        <?php endforeach; ?>
        <?php if (count($s_sectors) > 3): ?><span class="badge badge-tn">+<?= count($s_sectors) - 3 ?></span><?php endif; ?>
      </div>
    </div>

    <?php if ($s['elevator_pitch']): ?>
    <p class="card-desc" style="font-size:13px;color:var(--text-sec);line-height:1.55;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden"><?= h($s['elevator_pitch']) ?></p>
    <?php endif; ?>

    <div class="card-footer" style="display:flex;gap:8px;margin-top:auto;padding-top:10px;border-top:1px solid var(--border)">
      <a href="messages.php?to=<?= (int)$s['id'] ?>" class="btn btn-primary btn-sm" style="flex:1">&#128172; Contacter</a>
      <?php if ($s['website']): ?>
      <a href="<?= h($s['website']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm">Site &rarr;</a>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div id="dir-empty" class="card" style="display:none;text-align:center;padding:40px 20px">
  <div style="font-size:32px;margin-bottom:10px">&#128269;</div>
  <p style="font-size:14px;color:var(--muted)">Aucune startup ne correspond à votre recherche.</p>
</div>

<script>
(function() {
  var search  = document.getElementById('d-search');
  var sortSel = document.getElementById('d-sort');
  var pills   = document.querySelectorAll('.sector-pill');
  var cards   = document.querySelectorAll('.dir-card');
  var countEl = document.getElementById('dir-count');
  var grid    = document.getElementById('dir-grid');
  var empty   = document.getElementById('dir-empty');
  var sector  = '';

  function applyFilters() {
    var q = search.value.trim().toLowerCase();
    var visible = 0;
    cards.forEach(function(c) {
      var ok = true;
      if (q && c.dataset.search.indexOf(q) === -1) ok = false;
      if (ok && sector && c.dataset.sectors.split(',').indexOf(sector) === -1) ok = false;
      c.style.display = ok ? '' : 'none';
      if (ok) visible++;
    });
    countEl.textContent = visible + ' startup(s)';
    empty.style.display = visible === 0 ? '' : 'none';
    grid.style.display  = visible === 0 ? 'none' : '';
  }

  function applySort() {
    var key = sortSel.value;
    var attr = key === 'online' ? 'sortOnline' : (key === 'recent' ? 'sortCreated' : 'sortName');
    var sorted = Array.prototype.slice.call(cards).sort(function(a, b) {
      if (attr === 'sortName') return a.dataset[attr].localeCompare(b.dataset[attr]);
      return parseFloat(b.dataset[attr]) - parseFloat(a.dataset[attr]);
    });
    sorted.forEach(function(c) { grid.appendChild(c); });
  }

  search.addEventListener('input', applyFilters);
  sortSel.addEventListener('change', applySort);
  pills.forEach(function(p) {
    p.addEventListener('click', function() {
      pills.forEach(function(x) { x.classList.remove('active'); });
      p.classList.add('active');
      sector = p.dataset.sector;
      applyFilters();
    });
  });
})();
</script>

<script>
// Bascule vue grille / liste — persistée par utilisateur dans ce navigateur
(function() {
  var gridBtn = document.getElementById('view-grid');
  var listBtn = document.getElementById('view-list');
  var KEY = 'stn_view_directory';

  function setView(mode) {
    document.body.classList.toggle('list-view', mode === 'list');
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
