<?php
// header.php — Design System v3 — Inter + messagerie + annuaire
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Compter messages non lus pour la nav
$unread_count = 0;
if (isLoggedIn()) {
    try {
        $db = getDB();
        $stmt = $db->prepare('SELECT COUNT(*) FROM fm_messages WHERE receiver_id = ? AND is_read = 0');
        $stmt->execute([$_SESSION['fm_user_id']]);
        $unread_count = (int)$stmt->fetchColumn();

        // Présence en ligne : throttlée à 20s pour ne pas écrire en BDD à chaque page vue
        if (!isset($_SESSION['last_activity_write']) || time() - $_SESSION['last_activity_write'] > 20) {
            $db->prepare('UPDATE fm_users SET last_activity = NOW() WHERE id = ?')->execute([$_SESSION['fm_user_id']]);
            $_SESSION['last_activity_write'] = time();
        }
    } catch (Exception $e) { $unread_count = 0; }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title><?= isset($page_title) ? h($page_title) . ' — ' : '' ?>Startup.TN</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* ═══════════════════════════════════════════════════
   DESIGN SYSTEM v3 — Startup.TN
   Inter body · DM Mono data · Cross-platform
═══════════════════════════════════════════════════ */
:root {
  /* Couleurs */
  --bg:        #0d1117;
  --surface:   #161b27;
  --card:      #1c2333;
  --card-hover:#212840;
  --border:    #2a3349;
  --border-light:#344060;

  --accent:    #38bdf8;
  --accent-dim:rgba(56,189,248,0.12);
  --accent-border:rgba(56,189,248,0.25);
  --accent2:   #818cf8;
  --accent2-dim:rgba(129,140,248,0.12);
  --accent3:   #34d399;
  --accent3-dim:rgba(52,211,153,0.1);
  --accent4:   #fbbf24;
  --accent4-dim:rgba(251,191,36,0.1);
  --accent5:   #f87171;
  --accent5-dim:rgba(248,113,113,0.1);

  --text:      #f0f4f8;
  --text-sec:  #D2DFED;
  --muted:     #A8B8CC;
  --subtle:    #6A7F9A;

  /* Typo */
  --font:      'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  --mono:      'DM Mono', 'SF Mono', 'Fira Code', monospace;

  /* Espacements */
  --radius-sm: 6px;
  --radius:    10px;
  --radius-lg: 14px;
  --radius-xl: 18px;

  /* Ombres */
  --shadow:    0 4px 20px rgba(0,0,0,0.4);
  --shadow-lg: 0 8px 40px rgba(0,0,0,0.5);
}

*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

html { scroll-behavior: smooth; -webkit-text-size-adjust: 100%; }

body {
  font-family: var(--font);
  font-size: 15px;
  line-height: 1.68;
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  overflow-x: hidden;
}

/* ── NAVIGATION ─────────────────────────────────── */
.nav {
  position: sticky; top: 0; z-index: 200;
  background: rgba(13,17,23,0.96);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border-bottom: 1px solid var(--border);
  height: 58px;
  display: flex; align-items: center;
  padding: 0 20px;
  gap: 8px;
}
.nav-logo {
  font-family: var(--mono);
  font-size: 16px; font-weight: 500;
  color: var(--accent);
  text-decoration: none;
  letter-spacing: -0.3px;
  margin-right: 8px;
  white-space: nowrap;
  flex-shrink: 0;
}
.nav-logo span { color: var(--text); }

.nav-links {
  display: flex; gap: 2px; flex: 1;
  overflow-x: auto; scrollbar-width: none;
}
.nav-links::-webkit-scrollbar { display: none; }

.nav-link {
  display: flex; align-items: center; gap: 5px;
  padding: 6px 12px;
  border-radius: var(--radius-sm);
  color: var(--muted);
  text-decoration: none;
  font-size: 13.5px; font-weight: 500;
  white-space: nowrap;
  transition: color .15s, background .15s;
  position: relative;
}
.nav-link:hover { color: var(--text-sec); background: rgba(255,255,255,.05); }
.nav-link.active { color: var(--accent); background: var(--accent-dim); }

.nav-badge {
  position: absolute; top: 4px; right: 4px;
  background: var(--accent5);
  color: #fff; border-radius: 10px;
  font-size: 10px; font-weight: 700;
  padding: 1px 5px; min-width: 16px;
  text-align: center; line-height: 14px;
}

.nav-right {
  display: flex; align-items: center; gap: 10px;
  margin-left: auto; flex-shrink: 0;
}
.nav-user-name {
  font-size: 13px; color: var(--muted);
  max-width: 130px; overflow: hidden;
  text-overflow: ellipsis; white-space: nowrap;
}
.nav-user-name strong { display: block; color: var(--text-sec); font-size: 11px; font-weight: 500; }
.badge-admin-nav {
  padding: 2px 8px;
  background: var(--accent2-dim);
  border: 1px solid rgba(129,140,248,.3);
  border-radius: 4px;
  color: var(--accent2);
  font-size: 10px; font-family: var(--mono);
  text-transform: uppercase; letter-spacing: .5px;
}
.btn-logout {
  padding: 6px 14px;
  background: rgba(248,113,113,.08);
  border: 1px solid rgba(248,113,113,.2);
  border-radius: var(--radius-sm);
  color: var(--accent5);
  font-size: 13px; font-family: var(--font);
  text-decoration: none; cursor: pointer;
  transition: all .15s; white-space: nowrap;
}
.btn-logout:hover { background: rgba(248,113,113,.16); }

/* ── LAYOUT ─────────────────────────────────────── */
.page-wrap {
  padding: 24px 20px;
  max-width: 1440px;
  margin: 0 auto;
}

/* ── TYPOGRAPHY ─────────────────────────────────── */
h1 { font-size: clamp(20px,3vw,28px); font-weight: 700; color: #fff; line-height: 1.2; }
h2 { font-size: clamp(17px,2.5vw,22px); font-weight: 600; color: #fff; }
h3 { font-size: 16px; font-weight: 600; color: var(--text); }
p  { color: var(--text-sec); line-height: 1.65; }

/* ── CARDS ──────────────────────────────────────── */
.card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 20px;
  transition: border-color .2s, box-shadow .2s, transform .2s;
}
.card:hover { border-color: var(--border-light); }
.card.clickable:hover {
  border-color: var(--accent-border);
  box-shadow: var(--shadow);
  transform: translateY(-2px);
}
.card-section-label {
  font-size: 11px; font-family: var(--mono);
  color: var(--accent); text-transform: uppercase;
  letter-spacing: 1.5px; margin-bottom: 16px;
  padding-bottom: 10px; border-bottom: 1px solid var(--border);
}

/* ── KPI CARDS ──────────────────────────────────── */
.kpi-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px,1fr));
  gap: 12px; margin-bottom: 24px;
}
.kpi { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 18px 20px; }
.kpi-value { font-family: var(--mono); font-size: 28px; font-weight: 500; color: #fff; line-height: 1; margin-bottom: 4px; }
.kpi-label { font-size: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: .8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* ── BADGES ─────────────────────────────────────── */
.badge {
  display: inline-flex; align-items: center;
  padding: 3px 9px; border-radius: 4px;
  font-size: 11px; font-weight: 600;
  font-family: var(--mono); letter-spacing: .4px;
  text-transform: uppercase; white-space: nowrap;
}
.badge-fund         { background: var(--accent-dim);  color: var(--accent);  border: 1px solid var(--accent-border); }
.badge-accelerator  { background: var(--accent2-dim); color: var(--accent2); border: 1px solid rgba(129,140,248,.25); }
.badge-grant        { background: var(--accent3-dim); color: var(--accent3); border: 1px solid rgba(52,211,153,.2); }
.badge-competition  { background: var(--accent4-dim); color: var(--accent4); border: 1px solid rgba(251,191,36,.2); }
.badge-incubator    { background: var(--accent5-dim); color: var(--accent5); border: 1px solid rgba(248,113,113,.2); }
.badge-active       { background: var(--accent3-dim); color: var(--accent3); border: 1px solid rgba(52,211,153,.2); }
.badge-pending      { background: var(--accent4-dim); color: var(--accent4); border: 1px solid rgba(251,191,36,.2); }
.badge-approved     { background: var(--accent3-dim); color: var(--accent3); border: 1px solid rgba(52,211,153,.2); }
.badge-rejected     { background: var(--accent5-dim); color: var(--accent5); border: 1px solid rgba(248,113,113,.2); }
.badge-tn           { background: rgba(255,255,255,.04); color: var(--muted); border: 1px solid var(--border); font-size: 9px; }

/* ── DEADLINE COLORS ────────────────────────────── */
.dl-urgent { color: var(--accent5); font-family: var(--mono); font-size: 12px; font-weight: 600; }
.dl-soon   { color: var(--accent4); font-family: var(--mono); font-size: 12px; font-weight: 600; }
.dl-ok     { color: var(--accent3); font-family: var(--mono); font-size: 12px; font-weight: 600; }
.dl-open   { color: var(--muted);   font-family: var(--mono); font-size: 12px; }

/* ── BUTTONS ────────────────────────────────────── */
.btn {
  display: inline-flex; align-items: center; justify-content: center; gap: 6px;
  padding: 10px 18px; border-radius: var(--radius);
  border: none; font-family: var(--font);
  font-size: 14px; font-weight: 600;
  cursor: pointer; text-decoration: none;
  transition: all .15s; white-space: nowrap;
  min-height: 42px; /* Mobile tap target */
  -webkit-appearance: none;
}
.btn-primary  { background: var(--accent); color: #0d1117; }
.btn-primary:hover { opacity: .88; transform: translateY(-1px); }
.btn-secondary { background: var(--surface); border: 1px solid var(--border); color: var(--text-sec); }
.btn-secondary:hover { border-color: var(--accent); color: var(--accent); }
.btn-danger   { background: var(--accent5-dim); border: 1px solid rgba(248,113,113,.25); color: var(--accent5); }
.btn-danger:hover { background: rgba(248,113,113,.2); }
.btn-success  { background: var(--accent3-dim); border: 1px solid rgba(52,211,153,.25); color: var(--accent3); }
.btn-success:hover { background: rgba(52,211,153,.2); }
.btn-sm { padding: 6px 12px; font-size: 12px; min-height: 32px; }
.btn-ghost { background: transparent; color: var(--muted); }
.btn-ghost:hover { color: var(--text); background: rgba(255,255,255,.06); }

/* ── FORMS ──────────────────────────────────────── */
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.field { margin-bottom: 16px; }
.field label {
  display: block; font-size: 12px; font-weight: 500;
  color: var(--muted); text-transform: uppercase;
  letter-spacing: .8px; margin-bottom: 6px;
  font-family: var(--mono);
}
.field .req { color: var(--accent); }
.field input,
.field select,
.field textarea {
  width: 100%; padding: 11px 14px;
  background: var(--surface);
  border: 1.5px solid var(--border);
  border-radius: var(--radius);
  color: var(--text); font-family: var(--font);
  font-size: 14px; outline: none;
  transition: border-color .2s, box-shadow .2s;
  -webkit-appearance: none;
  min-height: 44px; /* Mobile */
}
.field input:focus,
.field select:focus,
.field textarea:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(56,189,248,.12);
}
.field input::placeholder,
.field textarea::placeholder { color: var(--subtle); }
.field textarea { resize: vertical; min-height: 90px; line-height: 1.6; }
.field small { display: block; font-size: 12px; color: var(--muted); margin-top: 5px; }

/* ── CUSTOM TOGGLE BUTTONS (remplace checkboxes) ── */
.toggle-group {
  display: flex; flex-wrap: wrap; gap: 8px; margin-top: 6px;
}
.toggle-item {
  position: relative;
}
.toggle-item input[type="checkbox"] {
  position: absolute; opacity: 0; width: 0; height: 0;
}
.toggle-item label {
  display: flex; align-items: center; gap: 6px;
  padding: 8px 14px;
  background: var(--surface);
  border: 1.5px solid var(--border);
  border-radius: 20px;
  font-size: 13px; font-weight: 500;
  color: var(--text-sec);
  cursor: pointer; transition: all .15s;
  text-transform: none; letter-spacing: 0;
  min-height: 38px; white-space: nowrap;
  user-select: none; -webkit-user-select: none;
}
.toggle-item label::before {
  content: '';
  width: 14px; height: 14px;
  border: 1.5px solid var(--border-light);
  border-radius: 3px;
  background: var(--bg);
  flex-shrink: 0;
  transition: all .15s;
}
.toggle-item input:checked + label {
  background: var(--accent-dim);
  border-color: var(--accent);
  color: var(--accent);
}
.toggle-item input:checked + label::before {
  background: var(--accent);
  border-color: var(--accent);
  background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 10 8' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 4L3.5 6.5L9 1' stroke='%230d1117' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: center;
  background-size: 10px;
}
.toggle-item label:hover { border-color: var(--accent); color: var(--accent); }

/* ── SECTOR PILLS ───────────────────────────────── */
.sector-pills { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; overflow-x: auto; padding-bottom: 4px; }
.sector-pill {
  padding: 6px 14px;
  background: var(--surface);
  border: 1.5px solid var(--border);
  border-radius: 20px;
  font-size: 13px; font-weight: 500;
  color: var(--muted);
  cursor: pointer; transition: all .15s;
  white-space: nowrap;
  -webkit-appearance: none;
  min-height: 36px;
  display: flex; align-items: center;
}
.sector-pill:hover { border-color: var(--accent); color: var(--accent); }
.sector-pill.active { background: var(--accent-dim); border-color: var(--accent); color: var(--accent); font-weight: 600; }

/* ── TABLES ─────────────────────────────────────── */
table { width: 100%; border-collapse: collapse; }
th {
  text-align: left; padding: 11px 14px;
  font-size: 11px; text-transform: uppercase;
  letter-spacing: .8px; color: var(--muted);
  font-family: var(--mono); font-weight: 500;
  border-bottom: 1px solid var(--border);
  background: var(--surface); white-space: nowrap;
}
td { padding: 12px 14px; border-bottom: 1px solid var(--border); font-size: 14px; vertical-align: middle; }
tr:hover td { background: rgba(255,255,255,.02); }

/* ── ALERTS ─────────────────────────────────────── */
.alert { padding: 13px 16px; border-radius: var(--radius); font-size: 14px; margin-bottom: 20px; }
.alert-success { background: var(--accent3-dim); border: 1px solid rgba(52,211,153,.25); color: #6ee7b7; }
.alert-error   { background: var(--accent5-dim); border: 1px solid rgba(248,113,113,.25); color: #fca5a5; }
.alert-info    { background: var(--accent-dim);  border: 1px solid var(--accent-border);  color: var(--accent); }
.alert-warn    { background: var(--accent4-dim); border: 1px solid rgba(251,191,36,.25);  color: #fcd34d; }

/* ── MODAL ──────────────────────────────────────── */
.modal-overlay {
  display: none; position: fixed; inset: 0; z-index: 300;
  background: rgba(0,0,0,.75);
  backdrop-filter: blur(6px);
  align-items: center; justify-content: center;
  padding: 16px;
}
.modal-overlay.open { display: flex; }
.modal {
  background: var(--card); border: 1px solid var(--border);
  border-radius: var(--radius-xl);
  width: 100%; max-width: 560px;
  max-height: 90vh; overflow-y: auto;
  animation: modalIn .2s ease;
  -webkit-overflow-scrolling: touch;
}
@keyframes modalIn { from { opacity:0; transform:translateY(16px); } to { opacity:1; transform:none; } }
.modal-header {
  padding: 20px 24px; border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
}
.modal-title { font-size: 17px; font-weight: 600; color: #fff; }
.modal-close {
  background: none; border: none; color: var(--muted);
  font-size: 20px; cursor: pointer; line-height: 1;
  padding: 4px; transition: color .15s; flex-shrink: 0;
}
.modal-close:hover { color: var(--text); }
.modal-body { padding: 24px; }
.modal-footer {
  padding: 16px 24px; border-top: 1px solid var(--border);
  display: flex; gap: 10px; justify-content: flex-end;
}

/* ── URGENCY STRIP ──────────────────────────────── */
.urgency-strip {
  background: rgba(248,113,113,.06);
  border: 1px solid rgba(248,113,113,.18);
  border-radius: var(--radius); padding: 12px 16px;
  margin-bottom: 20px; display: flex; align-items: center; gap: 10px;
}
.urgency-dot {
  width: 8px; height: 8px; border-radius: 50%;
  background: var(--accent5); flex-shrink: 0;
  animation: blink 1.5s infinite;
}
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }
.urgency-text { font-size: 13.5px; color: #fca5a5; line-height: 1.5; }
.urgency-text strong { font-family: var(--mono); }

/* ── SECTION HEADER ─────────────────────────────── */
.section-head {
  display: flex; align-items: center;
  justify-content: space-between;
  margin-bottom: 16px; gap: 12px; flex-wrap: wrap;
}
.section-title { font-size: 12px; color: var(--muted); font-family: var(--mono); letter-spacing: .5px; }
.count-pill {
  padding: 3px 10px; background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px; font-size: 12px;
  color: var(--muted); font-family: var(--mono);
}

/* ── TABS ───────────────────────────────────────── */
.tabs { display: flex; gap: 4px; margin-bottom: 20px; border-bottom: 1px solid var(--border); }
.tab-btn {
  padding: 10px 18px; background: none;
  border: 1px solid transparent; border-bottom: none;
  border-radius: var(--radius) var(--radius) 0 0;
  font-size: 13.5px; font-weight: 500; color: var(--muted);
  cursor: pointer; transition: all .15s; font-family: var(--font);
  margin-bottom: -1px; white-space: nowrap;
  display: flex; align-items: center; gap: 6px;
}
.tab-btn:hover { color: var(--text-sec); }
.tab-btn.active {
  background: var(--card);
  border-color: var(--border);
  border-bottom-color: var(--card);
  color: #fff;
}
.tab-content { display: none; }
.tab-content.active { display: block; }

/* ── MODAL FIELD ────────────────────────────────── */
.modal-field {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius); padding: 12px 14px;
}
.modal-field-label { font-size: 10px; color: var(--muted); text-transform: uppercase; letter-spacing: 1px; font-family: var(--mono); margin-bottom: 3px; }
.modal-field-value { font-size: 14px; color: var(--text); font-weight: 500; }
.modal-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; }

/* ── PRÉSENCE EN LIGNE ──────────────────────────── */
.presence-dot {
  width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0;
  border: 2px solid var(--card); box-sizing: content-box;
}
.presence-dot.online { background: var(--accent3); }
.presence-dot.offline { background: var(--subtle); }
.avatar-wrap { position: relative; display: inline-flex; flex-shrink: 0; }
.avatar-wrap .presence-dot { position: absolute; bottom: -1px; right: -1px; }

/* ── BASCULE VUE GRILLE / LISTE ─────────────────── */
.view-toggle { display: inline-flex; border: 1px solid var(--border); border-radius: var(--radius-sm); overflow: hidden; flex-shrink: 0; }
.view-toggle button {
  display: flex; align-items: center; justify-content: center;
  width: 40px; height: 40px; background: var(--surface); border: none;
  color: var(--muted); cursor: pointer; transition: all .15s;
}
.view-toggle button + button { border-left: 1px solid var(--border); }
.view-toggle button.active { background: var(--accent-dim); color: var(--accent); }
.view-toggle button:hover { color: var(--text); }

/* Vue liste : rangées compactes au lieu de cartes en grille */
.list-view .grid-cards { display: flex !important; flex-direction: column; gap: 8px; }
.list-view .grid-cards .item-card {
  display: flex !important; flex-direction: row !important; align-items: center;
  gap: 14px; padding: 12px 16px;
}
.list-view .grid-cards .item-card .card-icon { font-size: 20px; }
.list-view .grid-cards .item-card .card-body { flex: 1; min-width: 0; display: flex; align-items: center; gap: 16px; }
.list-view .grid-cards .item-card .card-title-block { min-width: 180px; flex-shrink: 0; }
.list-view .grid-cards .item-card .card-desc { display: none; }
.list-view .grid-cards .item-card .card-meta-grid { display: flex !important; gap: 16px; flex-shrink: 0; }
.list-view .grid-cards .item-card .card-badges { flex-shrink: 0; }
.list-view .grid-cards .item-card .card-footer { border-top: none !important; padding-top: 0 !important; margin-left: auto; flex-shrink: 0; }
@media (max-width: 768px) {
  .list-view .grid-cards .item-card { flex-wrap: wrap; }
  .list-view .grid-cards .item-card .card-body { flex-wrap: wrap; }
}

/* ── MESSAGE BUBBLE ─────────────────────────────── */
.msg-bubble {
  max-width: 72%; padding: 10px 14px;
  border-radius: 12px; font-size: 14px; line-height: 1.55;
  margin-bottom: 8px; word-wrap: break-word;
}
.msg-bubble.sent {
  background: var(--accent); color: #0d1117;
  border-bottom-right-radius: 3px; margin-left: auto;
}
.msg-bubble.received {
  background: var(--surface); color: var(--text);
  border: 1px solid var(--border);
  border-bottom-left-radius: 3px;
}
.msg-meta { font-size: 11px; margin-top: 3px; opacity: .7; }

/* ── RESPONSIVE ─────────────────────────────────── */
@media (max-width: 768px) {
  body { font-size: 15px; }
  .nav { padding: 0 14px; }
  .nav-user-name { display: none; }
  .badge-admin-nav { display: none; }
  .page-wrap { padding: 16px 14px; }
  .form-grid { grid-template-columns: 1fr; }
  .kpi-row { grid-template-columns: 1fr 1fr; }
  .modal-grid { grid-template-columns: 1fr; }
  th, td { padding: 10px 12px; font-size: 13px; }
  .btn { min-height: 44px; }
  .field input, .field select, .field textarea { min-height: 48px; font-size: 16px; } /* iOS zoom fix */
}
@media (max-width: 480px) {
  .nav-links { gap: 0; }
  .nav-link { padding: 6px 8px; font-size: 12px; }
  .nav-logo { font-size: 14px; margin-right: 4px; }
  .kpi-row { grid-template-columns: 1fr 1fr; gap: 8px; }
  .kpi { padding: 14px 16px; }
  .kpi-value { font-size: 24px; }
  .btn-logout { padding: 6px 10px; font-size: 12px; }
  .tabs { overflow-x: auto; }
  .tab-btn { padding: 8px 12px; font-size: 12px; }
}

/* ── PRINT ──────────────────────────────────────── */
@media print {
  .nav, .btn-logout, .modal-overlay { display: none !important; }
  body { background: #fff; color: #000; }
}
</style>
</head>
<body>
<nav class="nav">
  <a class="nav-logo" href="dashboard.php" style="font-size:26px;letter-spacing:0">&#127481;&#127475;</a>
  <div class="nav-links">
    <a class="nav-link <?= $current_page==='dashboard'?'active':'' ?>" href="dashboard.php">
      <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M2 3h5v5H2zm7 0h5v2h-5zm0 4h5v1h-5zM2 10h5v3H2zm7 1h5v2h-5z"/></svg>
      Programmes
    </a>
    <a class="nav-link <?= $current_page==='submit'?'active':'' ?>" href="submit.php">
      <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M8 1v10M3 6l5-5 5 5M1 13h14" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round"/></svg>
      Soumettre
    </a>
    <a class="nav-link <?= $current_page==='my_submissions'?'active':'' ?>" href="my_submissions.php">Mes soumissions</a>
    <a class="nav-link <?= $current_page==='directory'?'active':'' ?>" href="directory.php">
      <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M8 8a3 3 0 100-6 3 3 0 000 6zm-5 6a5 5 0 0110 0H3z"/></svg>
      Annuaire
    </a>
    <a class="nav-link <?= $current_page==='messages'?'active':'' ?>" href="messages.php" style="position:relative">
      <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M14 2H2a1 1 0 00-1 1v8a1 1 0 001 1h3l2 3 2-3h5a1 1 0 001-1V3a1 1 0 00-1-1z"/></svg>
      Messages
      <span class="nav-badge" id="nav-unread-badge" style="<?= $unread_count > 0 ? '' : 'display:none' ?>"><?= min($unread_count, 99) ?></span>
    </a>
    <a class="nav-link <?= $current_page==='profile'?'active':'' ?>" href="profile.php">Mon profil</a>
    <?php if (isAdmin()): ?>
    <a class="nav-link <?= $current_page==='admin'?'active':'' ?>" href="admin.php">
      <span class="badge-admin-nav">Admin</span>
    </a>
    <a class="nav-link <?= $current_page==='mail_diag'?'active':'' ?>" href="mail_diag.php" title="Diagnostic email">&#9993;</a>
    <?php endif; ?>
  </div>
  <div class="nav-right">
    <div class="nav-user-name">
      <strong>Connecté</strong>
      <?= h($_SESSION['fm_name'] ?? '') ?>
    </div>
    <a class="btn-logout" href="logout.php">Quitter</a>
  </div>
</nav>
<?php if (isLoggedIn()): ?>
<script>
// Badge messages non lus : rafraîchi périodiquement (polling — pas de WebSocket sur hébergement mutualisé)
(function() {
  var badge = document.getElementById('nav-unread-badge');
  if (!badge || document.hidden === undefined) return;
  function refresh() {
    if (document.hidden) return; // pas d'appel réseau sur un onglet en arrière-plan
    fetch('api_messages.php?action=unread_count', { credentials: 'same-origin' })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (d.unread > 0) {
          badge.textContent = Math.min(d.unread, 99);
          badge.style.display = '';
        } else {
          badge.style.display = 'none';
        }
      })
      .catch(function() {});
  }
  setInterval(refresh, 20000);
})();
</script>
<?php endif; ?>
<div class="page-wrap">
