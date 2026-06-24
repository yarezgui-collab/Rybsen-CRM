<?php
require_once 'config.php';
requireLogin();
$page_title = 'Programmes';

$db = getDB();

// Filtres GET
$type    = $_GET['type'] ?? 'all';
$tn_only = isset($_GET['tn']) ? 1 : 0;
$search  = trim($_GET['q'] ?? '');
$order   = $_GET['order'] ?? 'deadline';
$sector  = trim($_GET['sector'] ?? '');
$view    = $_GET['view'] ?? 'grid';
$stade   = trim($_GET['stade'] ?? '');

// Stades de levee de fonds
$stades_list = [
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

// Construction requête
$where  = ["p.status = 'active'"];
$params = [];

if ($type !== 'all') {
    $where[] = 'p.type = ?';
    $params[] = $type;
}
if ($tn_only) {
    $where[] = 'p.tunisia_focus = 1';
}
if ($sector) {
    $where[] = 'p.sectors LIKE ?';
    $params[] = "%$sector%";
}
if ($stade) {
    $where[] = 'p.stage_target LIKE ?';
    $params[] = "%" . $stade . "%";
}
if ($search) {
    $where[] = '(p.name LIKE ? OR p.organisation LIKE ? OR p.description LIKE ? OR p.sectors LIKE ? OR p.geo LIKE ? OR p.stage_target LIKE ?)';
    $s = "%$search%";
    array_push($params, $s, $s, $s, $s, $s, $s);
}

$order_map = [
    'deadline' => 'CASE WHEN p.deadline_date IS NULL THEN 1 ELSE 0 END, p.deadline_date ASC',
    'name'     => 'p.name ASC',
    'type'     => 'p.type ASC, p.name ASC',
];
$orderSQL = $order_map[$order] ?? 'p.created_at DESC';

$sql  = 'SELECT p.* FROM fm_programs p WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $orderSQL;
$stmt = $db->prepare($sql);
$stmt->execute($params);
$programs = $stmt->fetchAll();

// KPIs
$kpis = $db->query("SELECT
  COUNT(*) as total,
  SUM(type='fund') as funds,
  SUM(type='accelerator') as accels,
  SUM(type='grant' OR type='competition') as grants,
  SUM(tunisia_focus=1) as tn_focus,
  SUM(deadline_type='urgent' AND status='active') as urgent
FROM fm_programs WHERE status='active'")->fetch();

// Urgents
$urgents = $db->query("SELECT name FROM fm_programs WHERE deadline_type='urgent' AND status='active' ORDER BY deadline_date ASC")->fetchAll(PDO::FETCH_COLUMN);

// Top 5 contributeurs
$top5 = $db->query("
  SELECT u.startup_name, u.sector, COUNT(s.id) as total
  FROM fm_users u
  JOIN fm_submissions s ON s.user_id = u.id AND s.status = 'approved'
  GROUP BY u.id
  ORDER BY total DESC
  LIMIT 5
")->fetchAll();

$badge_map = ['fund'=>'badge-fund','accelerator'=>'badge-accelerator','grant'=>'badge-grant','competition'=>'badge-competition','incubator'=>'badge-incubator'];
$dl_map    = ['urgent'=>'dl-urgent','soon'=>'dl-soon','ok'=>'dl-ok','open'=>'dl-open'];

// Secteurs disponibles
$sectors_list = [
  'IA / AI','Fintech','Cleantech','Agritech','Healthtech','Edtech',
  'Watertech','Greentech','Deep Tech','Logistique','E-commerce',
  'Cybersécurité','Life Sciences','Impact Social','Tourisme Tech','SaaS / B2B'
];

include 'header.php';
?>

<!-- HERO -->
<div style="background:linear-gradient(135deg,#0a0f1e,#0f1a30,#0d1525);border:1px solid var(--border);border-radius:12px;padding:24px 28px;margin-bottom:20px;position:relative;overflow:hidden">
  <div style="position:relative;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px">
    <div>
      <div style="font-family:var(--mono);font-size:10px;color:var(--accent);letter-spacing:2px;text-transform:uppercase;margin-bottom:8px">// Plateforme de veille financement</div>
      <h1 style="font-size:clamp(18px,3vw,28px);font-weight:700;color:#fff;line-height:1.2;margin-bottom:6px">
        Fonds &amp; Programmes &mdash;
        <span style="background:linear-gradient(90deg,var(--accent),var(--accent2));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text">Startups Tunisiennes</span>
      </h1>
      <p style="color:var(--label);font-size:13px">Bonjour <strong style="color:var(--text)"><?= h($_SESSION['fm_name']) ?></strong> &mdash; <?= count($programs) ?> programme(s) trouv&eacute;(s)</p>
    </div>
    <!-- Top 5 widget -->
    <?php if (!empty($top5)): ?>
    <div style="background:rgba(0,0,0,.3);border:1px solid var(--border);border-radius:10px;padding:12px 16px;min-width:220px">
      <div style="font-size:10px;color:var(--accent);font-family:var(--mono);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px">&#127942; Top contributeurs</div>
      <?php foreach ($top5 as $i => $t): ?>
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:5px">
        <span style="font-family:var(--mono);font-size:10px;color:var(--muted);width:14px"><?= $i+1 ?></span>
        <span style="font-size:12px;color:#fff;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($t['startup_name']) ?></span>
        <span style="font-family:var(--mono);font-size:11px;color:var(--accent);font-weight:700"><?= $t['total'] ?></span>
        <span style="font-size:10px;color:var(--muted)">partage<?= $t['total']>1?'s':'' ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- KPIs -->
<div class="kpi-row">
  <div class="kpi" style="border-top:2px solid var(--accent)">
    <div class="kpi-value" style="color:var(--accent)"><?= $kpis['total'] ?></div>
    <div class="kpi-label">Programmes actifs</div>
  </div>
  <div class="kpi" style="border-top:2px solid #a78bfa">
    <div class="kpi-value" style="color:#a78bfa"><?= $kpis['funds'] ?></div>
    <div class="kpi-label">Fonds VC</div>
  </div>
  <div class="kpi" style="border-top:2px solid var(--accent3)">
    <div class="kpi-value" style="color:var(--accent3)"><?= $kpis['accels'] ?></div>
    <div class="kpi-label">Accélérateurs</div>
  </div>
  <div class="kpi" style="border-top:2px solid var(--accent4)">
    <div class="kpi-value" style="color:var(--accent4)"><?= $kpis['grants'] ?></div>
    <div class="kpi-label">Subventions</div>
  </div>
  <div class="kpi" style="border-top:2px solid var(--accent5)">
    <div class="kpi-value" style="color:var(--accent5)"><?= $kpis['urgent'] ?></div>
    <div class="kpi-label">Urgent · Semaine</div>
  </div>
  <div class="kpi" style="border-top:2px solid #34d399">
    <div class="kpi-value" style="color:#34d399"><?= $kpis['tn_focus'] ?></div>
    <div class="kpi-label">Focus Tunisie</div>
  </div>
</div>

<!-- URGENCY STRIP -->
<?php if ($urgents): ?>
<div class="urgency-strip">
  <div class="urgency-dot"></div>
  <div class="urgency-text">&#9889; <strong><?= count($urgents) ?> deadline<?= count($urgents)>1?'s':'' ?> cette semaine :</strong> <?= h(implode(', ', $urgents)) ?></div>
</div>
<?php endif; ?>

<!-- FILTRES SECTEURS -->
<div style="margin-bottom:12px;overflow-x:auto;padding-bottom:4px">
  <div style="display:flex;gap:6px;min-width:max-content;padding:2px">
    <a href="?<?= http_build_query(array_merge($_GET, ['sector'=>'', 'type'=>'all'])) ?>"
       style="padding:5px 12px;border-radius:20px;font-size:11px;font-weight:600;text-decoration:none;white-space:nowrap;transition:all .15s;
              <?= !$sector && $type==='all' ? 'background:var(--accent);color:#000' : 'background:var(--card);border:1px solid var(--border);color:var(--muted)' ?>">
      Tous
    </a>
    <?php foreach ($sectors_list as $sec): ?>
    <a href="?<?= http_build_query(array_merge($_GET, ['sector'=>$sec, 'type'=>'all'])) ?>"
       style="padding:5px 12px;border-radius:20px;font-size:11px;font-weight:600;text-decoration:none;white-space:nowrap;transition:all .15s;
              <?= $sector===$sec ? 'background:var(--accent);color:#000' : 'background:var(--card);border:1px solid var(--border);color:var(--muted)' ?>">
      <?= h($sec) ?>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- TOOLBAR -->
<div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:12px 16px;margin-bottom:16px">
  <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <?php if ($sector): ?><input type="hidden" name="sector" value="<?= h($sector) ?>"><?php endif; ?>
    <input type="text" name="q" value="<?= h($search) ?>"
      placeholder="&#128269; Rechercher..."
      style="flex:1;min-width:160px;max-width:280px;padding:8px 12px;background:var(--card);border:1px solid var(--border);border-radius:7px;color:var(--text);font-size:13px;outline:none">

    <select name="type" onchange="this.form.submit()"
      style="padding:8px 12px;background:var(--card);border:1px solid var(--border);border-radius:7px;color:var(--text);font-size:12px;outline:none;-webkit-appearance:none">
      <option value="all" <?= $type==='all'?'selected':'' ?>>Tous types</option>
      <option value="fund" <?= $type==='fund'?'selected':'' ?>>Fonds VC</option>
      <option value="accelerator" <?= $type==='accelerator'?'selected':'' ?>>Acc&eacute;l&eacute;rateurs</option>
      <option value="grant" <?= $type==='grant'?'selected':'' ?>>Subventions</option>
      <option value="competition" <?= $type==='competition'?'selected':'' ?>>Comp&eacute;titions</option>
      <option value="incubator" <?= $type==='incubator'?'selected':'' ?>>Incubateurs</option>
    </select>

    <select name="stade" onchange="this.form.submit()"
      style="padding:8px 12px;background:var(--card);border:1px solid var(--border);border-radius:7px;color:var(--text);font-size:12px;outline:none;-webkit-appearance:none">
      <option value="" <?= !$stade?'selected':'' ?>>Tous stades</option>
      <?php foreach ($stades_list as $sk => $sl): ?>
      <option value="<?= $sk ?>" <?= $stade===$sk?'selected':'' ?>><?= h($sl) ?></option>
      <?php endforeach; ?>
    </select>

    <select name="order" onchange="this.form.submit()"
      style="padding:8px 12px;background:var(--card);border:1px solid var(--border);border-radius:7px;color:var(--text);font-size:12px;outline:none;-webkit-appearance:none">
      <option value="deadline" <?= $order==='deadline'?'selected':'' ?>>Deadline</option>
      <option value="name"     <?= $order==='name'?'selected':'' ?>>Nom A-Z</option>
      <option value="type"     <?= $order==='type'?'selected':'' ?>>Type</option>
      <option value="recent"   <?= $order==='recent'?'selected':'' ?>>R&eacute;cents</option>
    </select>

    <label style="display:flex;align-items:center;gap:5px;font-size:12px;color:var(--label);cursor:pointer;white-space:nowrap">
      <input type="checkbox" name="tn" <?= $tn_only?'checked':'' ?> onchange="this.form.submit()" style="accent-color:var(--accent)">
      &#127481;&#127475; TN only
    </label>

    <button type="submit" class="btn btn-primary" style="padding:8px 14px;font-size:12px">OK</button>

    <?php if ($search || $type!=='all' || $tn_only || $sector || $stade): ?>
      <a href="dashboard.php" style="padding:8px 12px;background:var(--card);border:1px solid var(--border);border-radius:7px;color:var(--muted);font-size:12px;text-decoration:none">&#10005; Reset</a>
    <?php endif; ?>

    <!-- VIEW TOGGLE -->
    <div style="margin-left:auto;display:flex;gap:4px">
      <a href="?<?= http_build_query(array_merge($_GET, ['view'=>'grid'])) ?>"
         style="padding:7px 10px;border-radius:6px;font-size:14px;text-decoration:none;transition:all .15s;
                <?= $view!=='list' ? 'background:rgba(0,212,255,.1);border:1px solid var(--accent);color:var(--accent)' : 'background:var(--card);border:1px solid var(--border);color:var(--muted)' ?>"
         title="Vue grille">&#8862;</a>
      <a href="?<?= http_build_query(array_merge($_GET, ['view'=>'list'])) ?>"
         style="padding:7px 10px;border-radius:6px;font-size:14px;text-decoration:none;transition:all .15s;
                <?= $view==='list' ? 'background:rgba(0,212,255,.1);border:1px solid var(--accent);color:var(--accent)' : 'background:var(--card);border:1px solid var(--border);color:var(--muted)' ?>"
         title="Vue liste">&#8801;</a>
    </div>
  </form>
</div>

<!-- SECTION HEADER -->
<div class="section-head">
  <span class="section-title">// <?php
    $parts = [];
    if ($sector) $parts[] = h($sector);
    if ($stade && isset($stades_list[$stade])) $parts[] = h($stades_list[$stade]);
    if ($type !== 'all') $parts[] = h($type);
    echo $parts ? implode(' + ', $parts) : 'tous les programmes';
  ?></span>
  <span class="count-pill"><?= count($programs) ?> r&eacute;sultat<?= count($programs)>1?'s':'' ?></span>
</div>

<?php if (empty($programs)): ?>
  <div style="text-align:center;padding:60px;color:var(--muted)">
    <div style="font-size:40px;margin-bottom:12px">&#128269;</div>
    <h3 style="color:var(--label);margin-bottom:8px">Aucun r&eacute;sultat</h3>
    <p style="font-size:13px">Essayez un autre filtre ou <a href="dashboard.php" style="color:var(--accent)">r&eacute;initialisez</a>.</p>
  </div>

<?php elseif ($view === 'list'): ?>
<!-- ── VUE LISTE ── -->
<div style="background:var(--card);border:1px solid var(--border);border-radius:12px;overflow:hidden;overflow-x:auto">
  <table style="min-width:700px">
    <thead><tr>
      <th>Programme</th><th>Organisation</th><th>Type</th><th>Montant</th><th>G&eacute;o</th><th>Deadline</th><th></th>
    </tr></thead>
    <tbody>
      <?php foreach ($programs as $p): ?>
      <?php $bclass = $badge_map[$p['type']] ?? 'badge-fund'; $dclass = $dl_map[$p['deadline_type']] ?? 'dl-open'; ?>
      <tr data-id="<?= $p['id'] ?>" style="cursor:pointer" onclick="openModal(<?= $p['id'] ?>)">
        <td><span style="font-weight:600;color:#fff"><?= h($p['emoji']??'') ?> <?= h($p['name']) ?></span></td>
        <td style="color:var(--muted);font-size:12px"><?= h($p['organisation']) ?></td>
        <td><span class="badge <?= $bclass ?>"><?= h($p['badge']?:$p['type']) ?></span></td>
        <td style="font-family:var(--mono);color:var(--accent4);font-size:11px"><?= h($p['amount']??'—') ?></td>
        <td style="color:var(--accent3);font-size:12px"><?= h($p['geo']??'—') ?></td>
        <td class="<?= $dclass ?>"><?= h($p['deadline']) ?></td>
        <td style="color:var(--accent);font-size:12px">&rarr;</td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php else: ?>
<!-- ── VUE GRILLE ── -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:14px">
  <?php foreach ($programs as $p): ?>
  <?php
    $bclass = $badge_map[$p['type']] ?? 'badge-fund';
    $dclass = $dl_map[$p['deadline_type']] ?? 'dl-open';
    $sec_arr = array_slice(array_filter(array_map('trim', explode(',', $p['sectors']??''))), 0, 2);
    $type_map = ['fund'=>'var(--accent)','accelerator'=>'var(--accent2)','grant'=>'var(--accent3)','competition'=>'var(--accent4)'];
    $type_color = $type_map[$p['type']] ?? 'var(--accent5)';
  ?>
  <div class="card" style="cursor:pointer;position:relative;overflow:hidden"
       onclick="openModal(<?= $p['id'] ?>)"
       onmouseenter="this.style.borderColor='rgba(0,212,255,.3)';this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 32px rgba(0,0,0,.3)'"
       onmouseleave="this.style.borderColor='var(--border)';this.style.transform='';this.style.boxShadow=''">
    <div style="position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,<?= $type_color ?>,transparent)"></div>
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;gap:10px;padding-top:4px">
      <div style="width:40px;height:40px;background:var(--surface);border:1px solid var(--border);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0"><?= $p['emoji']??'&#127462;' ?></div>
      <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px">
        <span class="badge <?= $bclass ?>"><?= h($p['badge']?:$p['type']) ?></span>
        <?php if ($p['tunisia_focus']): ?><span class="badge" style="background:rgba(255,255,255,.03);color:var(--muted);border-color:var(--border);font-size:9px">&#127481;&#127475; TN</span><?php endif; ?>
      </div>
    </div>
    <div style="font-size:14px;font-weight:600;color:#fff;margin-bottom:3px;line-height:1.3"><?= h($p['name']) ?></div>
    <div style="font-size:12px;color:var(--muted);margin-bottom:10px"><?= h($p['organisation']) ?></div>
    <div style="font-size:12px;color:var(--label);line-height:1.5;margin-bottom:12px"><?= h(mb_substr($p['description']??'',0,120)) ?>&#8230;</div>
    <div style="display:flex;flex-wrap:wrap;gap:5px;margin-bottom:12px">
      <?php if ($p['amount'] && $p['amount'] !== 'A préciser'): ?>
        <span style="padding:2px 7px;background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.2);border-radius:4px;font-size:10px;color:var(--accent4);font-family:var(--mono)"><?= h($p['amount']) ?></span>
      <?php endif; ?>
      <span style="padding:2px 7px;background:rgba(16,185,129,.06);border:1px solid rgba(16,185,129,.15);border-radius:4px;font-size:10px;color:var(--accent3)"><?= h($p['geo']??'') ?></span>
      <?php foreach ($sec_arr as $s): ?>
        <span style="padding:2px 7px;background:rgba(124,58,237,.08);border:1px solid rgba(124,58,237,.2);border-radius:4px;font-size:10px;color:#a78bfa"><?= h($s) ?></span>
      <?php endforeach; ?>
    </div>
    <div style="padding-top:10px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
      <div>
        <div style="font-size:10px;color:var(--muted);margin-bottom:2px">Deadline</div>
        <div class="<?= $dclass ?>"><?= h($p['deadline']) ?></div>
      </div>
      <span style="padding:5px 11px;background:rgba(0,212,255,.08);border:1px solid rgba(0,212,255,.2);border-radius:6px;color:var(--accent);font-size:11px;font-weight:600">D&eacute;tails &rarr;</span>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- MODAL -->
<div class="modal-overlay" id="modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="m-title"></div>
      <button class="modal-close" onclick="closeModal()">&#10005;</button>
    </div>
    <div class="modal-body" id="m-body"></div>
    <div class="modal-footer">
      <a href="#" id="m-link" target="_blank" rel="noopener noreferrer" class="btn btn-primary">Postuler &rarr;</a>
      <button class="btn btn-secondary" onclick="closeModal()">Fermer</button>
    </div>
  </div>
</div>

<script>
var P = <?= json_encode(array_column($programs, null, 'id'), JSON_UNESCAPED_UNICODE) ?>;
function openModal(id) {
  var p = P[id]; if (!p) return;
  document.getElementById('m-title').textContent = (p.emoji||'') + ' ' + p.name;
  var dc = {urgent:'dl-urgent',soon:'dl-soon',ok:'dl-ok',open:'dl-open'}[p.deadline_type]||'dl-open';
  var sec = (p.sectors||'').split(',').filter(function(s){return s.trim();}).map(function(s){
    return '<span style="padding:3px 8px;background:rgba(124,58,237,.08);border:1px solid rgba(124,58,237,.2);border-radius:4px;font-size:12px;color:#a78bfa">'+s.trim()+'</span>';
  }).join(' ');
  document.getElementById('m-body').innerHTML =
    '<p style="font-size:14px;color:var(--label);line-height:1.65;margin-bottom:20px">'+(p.description||'')+'</p>'+
    '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:20px">'+
      mf('Type',p.badge||p.type)+mf('Montant',p.amount||'N/A')+
      mf('Deadline','<span class="'+dc+'">'+(p.deadline||'N/A')+'</span>')+mf('G&eacute;ographie',p.geo||'N/A')+
      mf('Stage',p.stage_target||'N/A')+mf('Eligibilit&eacute; TN',p.tn_eligible||'N/A')+
    '</div>'+
    '<div><div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;font-family:var(--mono)">Secteurs</div>'+
    '<div style="display:flex;flex-wrap:wrap;gap:6px">'+sec+'</div></div>';
  var btn = document.getElementById('m-link');
  if (p.link && p.link !== '#') { btn.href = p.link; btn.style.display = 'inline-flex'; }
  else btn.style.display = 'none';
  document.getElementById('modal').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function mf(l,v){
  return '<div style="background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:10px">'+
    '<div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:1px;font-family:var(--mono);margin-bottom:3px">'+l+'</div>'+
    '<div style="font-size:13px;color:var(--text);font-weight:500">'+(v||'&mdash;')+'</div></div>';
}
function closeModal(){
  document.getElementById('modal').classList.remove('open');
  document.body.style.overflow='';
}
document.getElementById('modal').addEventListener('click',function(e){if(e.target===this)closeModal();});
document.addEventListener('keydown',function(e){if(e.key==='Escape')closeModal();});
</script>

<?php include 'footer.php'; ?>
