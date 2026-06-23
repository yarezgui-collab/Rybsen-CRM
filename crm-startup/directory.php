<?php
require_once 'config.php';
requireLogin();
$page_title = 'Annuaire Startups';

$db  = getDB();
$uid = (int)$_SESSION['fm_user_id'];

$search = trim($_GET['q'] ?? '');
$sector = trim($_GET['sector'] ?? '');
$stage  = trim($_GET['stage'] ?? '');

$where  = ["u.role = 'startup'", "u.is_active = 1", "u.id != ?"];
$params = [$uid];

if ($search) {
    $where[] = "(u.startup_name LIKE ? OR u.sector LIKE ? OR u.city LIKE ? OR u.elevator_pitch LIKE ? OR u.ceo_name LIKE ?)";
    $s = "%$search%";
    array_push($params, $s, $s, $s, $s, $s);
}
if ($sector) { $where[] = "u.sector LIKE ?"; $params[] = "%$sector%"; }
if ($stage)  { $where[] = "u.stage = ?";     $params[] = $stage; }

$sql = "SELECT u.*, 
    (SELECT COUNT(*) FROM fm_submissions s WHERE s.user_id=u.id AND s.status='approved') as contribs
    FROM fm_users u
    WHERE " . implode(' AND ', $where) . "
    ORDER BY contribs DESC, u.startup_name ASC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$startups = $stmt->fetchAll();

$sectors_list = ['IA / AI','Fintech','Cleantech','Agritech','Healthtech','Edtech','Watertech','Greentech','Deep Tech','Logistique','E-commerce','Cybersécurité','Life Sciences','Impact Social','Tourisme Tech','SaaS / B2B','Autre'];
$stages_list  = ['idee'=>'Idée','bootstrapping'=>'Bootstrap','pre-seed'=>'Pre-Seed','seed'=>'Seed','series-a'=>'Série A','series-b'=>'Série B','series-c'=>'Série C','growth'=>'Growth','pre-ipo'=>'Pré-IPO'];

include 'header.php';
?>

<div style="margin-bottom:24px">
  <h1 style="margin-bottom:4px">Annuaire Startups &#127481;&#127475;</h1>
  <p style="color:var(--muted);font-size:14px">Découvrez et contactez les autres startups de la plateforme.</p>
</div>

<!-- FILTRES -->
<div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:16px;margin-bottom:20px">
  <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <div style="flex:1;min-width:200px">
      <label style="display:block;font-size:11px;color:var(--muted);font-family:var(--mono);text-transform:uppercase;letter-spacing:.8px;margin-bottom:5px">Rechercher</label>
      <input type="text" name="q" value="<?= h($search) ?>" placeholder="Nom, secteur, ville, fondateur..."
        style="width:100%;padding:10px 14px;background:var(--surface);border:1.5px solid var(--border);border-radius:var(--radius);color:var(--text);font-size:14px;outline:none;min-height:42px">
    </div>
    <div>
      <label style="display:block;font-size:11px;color:var(--muted);font-family:var(--mono);text-transform:uppercase;letter-spacing:.8px;margin-bottom:5px">Secteur</label>
      <select name="sector" onchange="this.form.submit()"
        style="padding:10px 14px;background:var(--surface);border:1.5px solid var(--border);border-radius:var(--radius);color:var(--text);font-size:14px;outline:none;-webkit-appearance:none;min-height:42px;min-width:160px">
        <option value="">Tous secteurs</option>
        <?php foreach ($sectors_list as $s): ?>
        <option value="<?= h($s) ?>" <?= $sector===$s?'selected':'' ?>><?= h($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label style="display:block;font-size:11px;color:var(--muted);font-family:var(--mono);text-transform:uppercase;letter-spacing:.8px;margin-bottom:5px">Stade</label>
      <select name="stage" onchange="this.form.submit()"
        style="padding:10px 14px;background:var(--surface);border:1.5px solid var(--border);border-radius:var(--radius);color:var(--text);font-size:14px;outline:none;-webkit-appearance:none;min-height:42px;min-width:140px">
        <option value="">Tous stades</option>
        <?php foreach ($stages_list as $k=>$v): ?>
        <option value="<?= $k ?>" <?= $stage===$k?'selected':'' ?>><?= h($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Rechercher</button>
    <?php if ($search||$sector||$stage): ?>
      <a href="directory.php" class="btn btn-secondary">&#10005; Reset</a>
    <?php endif; ?>
  </form>
</div>

<!-- RÉSULTATS -->
<div class="section-head">
  <span class="section-title">// <?= count($startups) ?> startup<?= count($startups)>1?'s':'' ?> trouv&eacute;e<?= count($startups)>1?'s':'' ?></span>
  <div style="display:flex;gap:6px">
    <button id="btn-grid-dir" onclick="setView('grid')" class="btn btn-sm" 
      style="padding:6px 12px;background:var(--accent-dim);border:1px solid var(--accent);color:var(--accent)">&#8862; Grille</button>
    <button id="btn-list-dir" onclick="setView('list')" class="btn btn-sm btn-secondary"
      style="padding:6px 12px">&#8801; Liste</button>
  </div>
</div>

<?php if (empty($startups)): ?>
  <div style="text-align:center;padding:60px;color:var(--muted)">
    <div style="font-size:40px;margin-bottom:12px">&#128269;</div>
    <h3 style="color:var(--text-sec);margin-bottom:8px">Aucune startup trouvée</h3>
    <p style="font-size:14px">Essayez d'autres critères de recherche.</p>
  </div>
<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px">
  <?php foreach ($startups as $s): ?>
  <div class="card" style="display:flex;flex-direction:column;gap:14px">
    <!-- Header carte -->
    <div style="display:flex;align-items:flex-start;gap:14px">
      <div style="width:48px;height:48px;flex-shrink:0;
        background:linear-gradient(135deg,var(--accent-dim),var(--accent2-dim));
        border:1px solid var(--border);border-radius:var(--radius);
        display:flex;align-items:center;justify-content:center;
        font-size:20px;font-weight:700;color:var(--accent)">
        <?= mb_strtoupper(mb_substr($s['startup_name'],0,1)) ?>
      </div>
      <div style="flex:1;min-width:0">
        <div style="font-size:15px;font-weight:600;color:#fff;margin-bottom:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($s['startup_name']) ?></div>
        <div style="font-size:12px;color:var(--muted)">
          <?= $s['ceo_name'] ? h($s['ceo_name'] ?? '') . ' &mdash; ' : '' ?>
          <?= $s['city'] ? h($s['city']) : 'Tunisie' ?>
          <?= $s['founded_year'] ? ' &bull; ' . $s['founded_year'] : '' ?>
        </div>
      </div>
      <?php if ($s['contribs'] > 0): ?>
      <div style="flex-shrink:0;text-align:center;background:var(--accent-dim);border:1px solid var(--accent-border);border-radius:var(--radius-sm);padding:4px 8px">
        <div style="font-family:var(--mono);font-size:16px;font-weight:500;color:var(--accent);line-height:1"><?= $s['contribs'] ?></div>
        <div style="font-size:9px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px">partage<?= $s['contribs']>1?'s':'' ?></div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Badges -->
    <div style="display:flex;flex-wrap:wrap;gap:6px">
      <?php if ($s['sector']): ?><span class="badge badge-accelerator"><?= h($s['sector'] ?? '') ?></span><?php endif; ?>
      <?php if ($s['stage'] && isset($stages_list[$s['stage']])): ?><span class="badge badge-fund"><?= h($stages_list[$s['stage']]) ?></span><?php endif; ?>
      <?php if ($s['website']): ?>
        <a href="<?= h($s['website']) ?>" target="_blank" rel="noopener"
           style="padding:3px 9px;background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:4px;font-size:11px;color:var(--muted);text-decoration:none"
           onclick="event.stopPropagation()">&#127760; Site</a>
      <?php endif; ?>
    </div>

    <!-- Pitch -->
    <?php if ($s['elevator_pitch']): ?>
    <p style="font-size:13.5px;color:var(--text-sec);line-height:1.55;margin:0">
      <?= h(mb_substr($s['elevator_pitch'],0,150)) ?><?= mb_strlen($s['elevator_pitch'])>150?'…':'' ?>
    </p>
    <?php endif; ?>

    <!-- Traction -->
    <?php if ($s['revenue_range'] && $s['revenue_range'] !== 'pre-revenue'): ?>
    <div style="display:flex;gap:8px;font-size:12px;color:var(--muted)">
      <span>&#128200; <?= h($s['revenue_range']) ?></span>
      <?php if ($s['users_count']): ?><span>&bull; <?= h($s['users_count']) ?></span><?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Actions -->
    <div style="display:flex;gap:8px;padding-top:12px;border-top:1px solid var(--border);margin-top:auto">
      <a href="messages.php?to=<?= $s['id'] ?>" class="btn btn-primary" style="flex:1;font-size:13px;min-height:38px">
        &#128172; Envoyer un message
      </a>
      <button class="btn btn-secondary btn-sm" onclick="showProfile(<?= $s['id'] ?>,'<?= addslashes(h($s['startup_name'])) ?>')" style="min-height:38px">
        Voir
      </button>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- VUE LISTE ANNUAIRE -->
<div id="dir-list-view" style="display:none;background:var(--card);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;overflow-x:auto">
  <table style="min-width:600px">
    <thead><tr>
      <th>Startup</th>
      <th>CEO</th>
      <th>Secteur</th>
      <th>Stade</th>
      <th>Ville</th>
      <th>Partages</th>
      <th></th>
    </tr></thead>
    <tbody>
      <?php foreach ($startups as $s): ?>
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:10px">
            <div style="width:34px;height:34px;flex-shrink:0;background:var(--accent-dim);border:1px solid var(--accent-border);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--accent);font-size:14px">
              <?= mb_strtoupper(mb_substr($s['startup_name'],0,1)) ?>
            </div>
            <span style="font-weight:600;color:#fff"><?= h($s['startup_name']) ?></span>
          </div>
        </td>
        <td style="color:var(--muted);font-size:13px"><?= h($s['ceo_name'] ?? '—') ?></td>
        <td><?php if($s['sector']): ?><span class="badge badge-accelerator" style="font-size:10px"><?= h($s['sector'] ?? '') ?></span><?php else: ?>—<?php endif; ?></td>
        <td style="font-size:12px;color:var(--muted)"><?php
          $sl = ['idee'=>'Idée','bootstrapping'=>'Bootstrap','pre-seed'=>'Pre-Seed','seed'=>'Seed','series-a'=>'Série A','series-b'=>'Série B','series-c'=>'Série C','growth'=>'Growth','pre-ipo'=>'Pré-IPO'];
          echo h($sl[$s['stage']] ?? ($s['stage'] ?? '—'));
        ?></td>
        <td style="font-size:13px;color:var(--muted)"><?= h($s['city'] ?? '—') ?></td>
        <td>
          <?php if ($s['contribs'] > 0): ?>
          <span style="font-family:var(--mono);color:var(--accent);font-weight:600"><?= $s['contribs'] ?></span>
          <span style="font-size:11px;color:var(--muted)"> partage<?= $s['contribs']>1?'s':'' ?></span>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td>
          <div style="display:flex;gap:6px">
            <a href="messages.php?to=<?= $s['id'] ?>" class="btn btn-primary btn-sm">&#128172;</a>
            <button class="btn btn-secondary btn-sm" onclick="showProfile(<?= $s['id'] ?>,'')">Voir</button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- MODAL PROFIL PUBLIC -->
<div class="modal-overlay" id="modal-profile">
  <div class="modal" style="max-width:620px;width:calc(100vw - 32px)">
    <div class="modal-header" style="padding:16px 20px">
      <div style="display:flex;align-items:center;gap:12px;flex:1;min-width:0">
        <div id="mp-avatar" style="width:44px;height:44px;flex-shrink:0;border-radius:10px;background:var(--accent-dim);border:1px solid var(--accent-border);display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;color:var(--accent)"></div>
        <div style="min-width:0">
          <div id="mp-title" style="font-size:16px;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"></div>
          <div id="mp-sub" style="font-size:12px;color:var(--muted)"></div>
        </div>
      </div>
      <button class="modal-close" onclick="document.getElementById('modal-profile').classList.remove('open')">&#10005;</button>
    </div>
    <div class="modal-body" id="mp-body" style="padding:16px 20px 20px">
    </div>
    <div class="modal-footer" style="padding:12px 20px">
      <a href="#" id="mp-msg-link" class="btn btn-primary" style="width:100%;justify-content:center">&#128172; Envoyer un message</a>
    </div>
  </div>
</div>

<script>
var STARTUPS = <?= json_encode(array_values($startups), JSON_UNESCAPED_UNICODE) ?>;

function showProfile(id, name) {
  var s = STARTUPS.find(function(x){ return parseInt(x.id) === parseInt(id); });
  if (!s) return;

  var stages = {
    'idee':'Idée','bootstrapping':'Bootstrap','pre-seed':'Pre-Seed',
    'seed':'Seed','series-a':'Série A','series-b':'Série B',
    'series-c':'Série C','growth':'Growth','pre-ipo':'Pré-IPO'
  };

  // Header
  document.getElementById('mp-avatar').textContent = s.startup_name.charAt(0).toUpperCase();
  document.getElementById('mp-title').textContent = s.startup_name;
  var sub = [];
  if (s.ceo_name) sub.push(s.ceo_name);
  if (s.city) sub.push(s.city);
  if (s.founded_year) sub.push('Fondée ' + s.founded_year);
  document.getElementById('mp-sub').textContent = sub.join(' · ');

  // Lien message
  document.getElementById('mp-msg-link').href = 'messages.php?to=' + s.id;

  var html = '';

  // Badges
  html += '<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px">';
  if (s.sector) html += badge(s.sector, 'var(--accent2)', 'rgba(129,140,248,.12)', 'rgba(129,140,248,.25)');
  if (s.stage && stages[s.stage]) html += badge(stages[s.stage], 'var(--accent)', 'var(--accent-dim)', 'var(--accent-border)');
  if (parseInt(s.has_tech_team) === 1) html += badge('✓ Équipe tech', 'var(--accent3)', 'var(--accent3-dim)', 'rgba(52,211,153,.2)');
  html += '</div>';

  // Pitch
  if (s.elevator_pitch) {
    html += block('Pitch', s.elevator_pitch, '14px', 'var(--accent-dim)', 'var(--accent-border)');
  }

  // Problème
  if (s.problem) {
    html += block('Problème résolu', s.problem, '13px', 'var(--surface)', 'var(--border)');
  }

  // Solution
  if (s.solution) {
    html += block('Solution', s.solution, '13px', 'var(--surface)', 'var(--border)');
  }

  // Infos complémentaires
  var infos = [];
  if (s.revenue_range && s.revenue_range !== 'pre-revenue') infos.push('Revenus · ' + s.revenue_range);
  if (s.users_count) infos.push(s.users_count);
  if (s.funding_raised) infos.push('Levé · ' + s.funding_raised);
  if (infos.length) {
    html += '<div style="margin-top:12px;display:flex;flex-wrap:wrap;gap:8px">';
    for (var i=0; i<infos.length; i++) {
      html += '<span style="padding:4px 10px;background:var(--surface);border:1px solid var(--border);border-radius:6px;font-size:12px;color:var(--muted)">' + esc(infos[i]) + '</span>';
    }
    html += '</div>';
  }

  document.getElementById('mp-body').innerHTML = html;
  document.getElementById('modal-profile').classList.add('open');
}

function badge(txt, color, bg, border) {
  return '<span style="padding:4px 12px;background:'+bg+';border:1px solid '+border+';border-radius:6px;font-size:12px;color:'+color+';font-weight:600">'+esc(txt)+'</span>';
}

function block(label, text, size, bg, border) {
  return '<div style="background:'+bg+';border:1px solid '+border+';border-radius:10px;padding:14px;margin-bottom:12px">'
    + '<div style="font-size:10px;color:var(--muted);font-family:monospace;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px">'+esc(label)+'</div>'
    + '<p style="font-size:'+size+';color:var(--text-sec);line-height:1.6;margin:0">'+esc(text)+'</p>'
    + '</div>';
}

function mf(l,v){ return ''; }
function esc(s){ var d=document.createElement('div'); d.textContent=s; return d.innerHTML; }
document.getElementById('modal-profile').addEventListener('click',function(e){if(e.target===this)this.classList.remove('open');});

function setView(v) {
  var grid = document.querySelector('[style*="auto-fill,minmax(300px"]');
  var list = document.getElementById('dir-list-view');
  var btnG = document.getElementById('btn-grid-dir');
  var btnL = document.getElementById('btn-list-dir');
  if (v === 'list') {
    if (grid) grid.style.display = 'none';
    if (list) list.style.display = 'block';
    if (btnG) { btnG.style.background='var(--surface)'; btnG.style.borderColor='var(--border)'; btnG.style.color='var(--muted)'; }
    if (btnL) { btnL.style.background='var(--accent-dim)'; btnL.style.borderColor='var(--accent)'; btnL.style.color='var(--accent)'; }
  } else {
    if (grid) grid.style.display = 'grid';
    if (list) list.style.display = 'none';
    if (btnG) { btnG.style.background='var(--accent-dim)'; btnG.style.borderColor='var(--accent)'; btnG.style.color='var(--accent)'; }
    if (btnL) { btnL.style.background='var(--surface)'; btnL.style.borderColor='var(--border)'; btnL.style.color='var(--muted)'; }
  }
}
document.addEventListener('keydown',function(e){if(e.key==='Escape')document.getElementById('modal-profile').classList.remove('open');});
</script>


<style>
@media (max-width: 520px) {
  #modal-profile .modal { max-width: 100%; }
  #modal-profile .modal-body div[style*="grid-template-columns:1fr 1fr"] {
    grid-template-columns: 1fr !important;
  }
}
</style>

<?php include 'footer.php'; ?>
