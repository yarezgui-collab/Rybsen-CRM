<?php
require_once '../config.php';
requireLogin();

$id = intval($_GET['id'] ?? 0);
if (!$id) die('ID requis');

$db = getDB();
$stmt = $db->prepare("SELECT * FROM documents WHERE id=?");
$stmt->execute([$id]);
$doc = $stmt->fetch();
if (!$doc) die('Document introuvable');

$stmt2 = $db->prepare("SELECT * FROM document_lignes WHERE document_id=? ORDER BY position");
$stmt2->execute([$id]);
$lignes = $stmt2->fetchAll();

$stmt3 = $db->prepare("SELECT COALESCE(SUM(montant),0) FROM paiements_recus WHERE document_id=?");
$stmt3->execute([$id]);
$montant_paye = floatval($stmt3->fetchColumn());

$labels = [
  'Devis' => 'DEVIS',
  'Facture' => 'FACTURE',
  'Pro forma' => 'FACTURE PRO FORMA',
  'Bon de livraison' => 'BON DE LIVRAISON'
];
$label = $labels[$doc['type']] ?? strtoupper($doc['type']);

function fmtNum($n, $d = 'TND') {
    return number_format(floatval($n), 3, ',', ' ') . ' ' . $d;
}

// ── Montant en lettres (TND : dinars + millimes) ──────────────────────────────
function _nLettres(int $n): string {
    if ($n === 0) return 'zéro';
    $u = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf',
          'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize',
          'dix-sept', 'dix-huit', 'dix-neuf'];
    if ($n < 20) return $u[$n];
    if ($n < 70) {
        $t = intdiv($n, 10); $r = $n % 10;
        $d = ['', '', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante'][$t];
        if ($r === 0) return $d . ($t === 8 ? 's' : '');
        return $d . ($r === 1 && $t < 8 ? '-et-' : '-') . $u[$r];
    }
    if ($n < 80) { $r = $n - 60; return 'soixante' . ($r === 1 ? '-et-' : '-') . $u[$r]; }
    if ($n < 100) { $r = $n - 80; return 'quatre-vingt' . ($r === 0 ? 's' : '-' . $u[$r]); }
    if ($n < 200) { $r = $n % 100; return 'cent' . ($r ? ' ' . _nLettres($r) : 's'); }
    if ($n < 1000) { $h = intdiv($n, 100); $r = $n % 100; return _nLettres($h) . ' cent' . ($r ? ' ' . _nLettres($r) : 's'); }
    if ($n < 2000) { $r = $n % 1000; return 'mille' . ($r ? ' ' . _nLettres($r) : ''); }
    if ($n < 1000000) { $k = intdiv($n, 1000); $r = $n % 1000; return _nLettres($k) . ' mille' . ($r ? ' ' . _nLettres($r) : ''); }
    $m = intdiv($n, 1000000); $r = $n % 1000000;
    return _nLettres($m) . ' million' . ($m > 1 ? 's' : '') . ($r ? ' ' . _nLettres($r) : '');
}

function montantEnLettres(float $montant, string $devise = 'TND'): string {
    $entier   = intval($montant);
    $millimes = (int) round(($montant - $entier) * 1000);
    $texte = ucfirst(_nLettres($entier));
    if ($devise === 'TND') {
        $texte .= ' dinar' . ($entier > 1 ? 's' : '');
        if ($millimes > 0)
            $texte .= ' et ' . _nLettres($millimes) . ' millime' . ($millimes > 1 ? 's' : '');
    } else {
        $texte .= ' ' . $devise;
    }
    return $texte;
}

$reste = max(0, floatval($doc['total_ttc']) - $montant_paye);
$isPaye    = $montant_paye >= floatval($doc['total_ttc']) && floatval($doc['total_ttc']) > 0;
$isPartiel = $montant_paye > 0 && !$isPaye;

// Watermark selon statut
$wm = '';
if ($doc['statut'] === 'Payé' || $isPaye) $wm = 'PAYÉ';
elseif ($doc['statut'] === 'Annulé') $wm = 'ANNULÉ';
elseif ($doc['statut'] === 'Brouillon') $wm = 'BROUILLON';

// Logo embarqué via base64 (PDF-safe)
$logoPath = __DIR__ . '/../assets/logo.jpg';
$logoSrc  = null;
if (file_exists($logoPath)) {
    $ext = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
    $mime = $ext === 'png' ? 'image/png' : 'image/jpeg';
    $logoSrc = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoPath));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($doc['numero']) ?> — RYBSEN</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --cyan:    #17B3CC;
  --navy:    #1A3A52;
  --gold:    #E8A44C;
  --bg:      #f0f2f5;
  --surface: #f8fafc;
  --border:  #e2e8f0;
  --text:    #1a1a2e;
  --muted:   #64748b;
}

body {
  font-family: 'Helvetica Neue', Arial, sans-serif;
  background: var(--bg);
  color: var(--text);
  font-size: 13px;
  line-height: 1.5;
}

/* ── Barre d'actions ── */
.action-bar {
  background: var(--navy);
  padding: 11px 36px;
  display: flex;
  align-items: center;
  gap: 10px;
  position: sticky;
  top: 0;
  z-index: 100;
}
.action-bar button {
  padding: 8px 20px;
  border: none;
  border-radius: 7px;
  cursor: pointer;
  font-size: 13px;
  font-weight: 600;
}
.btn-print { background: var(--cyan); color: #fff; }
.btn-back  { background: rgba(255,255,255,.12); color: #fff; }
.btn-back:hover { background: rgba(255,255,255,.22); }
.action-bar .doc-ref { color: rgba(255,255,255,.5); font-size: 12px; margin-left: auto; }

/* ── Page A4 ── */
.page {
  background: #fff;
  width: 210mm;
  min-height: 297mm;
  margin: 28px auto;
  box-shadow: 0 4px 48px rgba(0,0,0,.16);
  display: flex;
  flex-direction: column;
  position: relative;
  overflow: hidden;
}

/* ── Watermark ── */
.watermark {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%) rotate(-38deg);
  font-size: 88px;
  font-weight: 900;
  letter-spacing: 12px;
  pointer-events: none;
  z-index: 0;
  user-select: none;
  white-space: nowrap;
}
.wm-paye    { color: rgba(22,163,74,.10); }
.wm-annule  { color: rgba(153,27,27,.09); }
.wm-brouillon { color: rgba(100,116,139,.07); }

/* ── Header ── */
.page-header {
  background: var(--navy);
  padding: 22px 36px 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: relative;
  z-index: 1;
}
.logo-area img  { height: 52px; width: auto; display: block; }
.logo-area-text { /* fallback si pas d'image */ }
.logo-text      { font-size: 28px; font-weight: 900; color: #fff; letter-spacing: 3px; }
.logo-tagline   { font-size: 9px; color: var(--cyan); letter-spacing: 2.5px; text-transform: uppercase; margin-top: 3px; }

.doc-title-area { text-align: right; }
.doc-type-label {
  font-size: 26px;
  font-weight: 900;
  color: #fff;
  letter-spacing: 2px;
  text-transform: uppercase;
}
.doc-numero {
  font-size: 13px;
  color: var(--cyan);
  font-weight: 600;
  margin-top: 5px;
  letter-spacing: 0.5px;
}

/* Barre de séparation cyan-or */
.accent-bar {
  height: 4px;
  background: linear-gradient(90deg, var(--cyan) 0%, var(--gold) 100%);
}

/* ── Corps ── */
.page-body { padding: 28px 36px; flex: 1; position: relative; z-index: 1; }

/* Émetteur / Client */
.parties-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  margin-bottom: 24px;
}
.party-block { }
.party-block .pb-label {
  font-size: 9px;
  text-transform: uppercase;
  letter-spacing: 2px;
  color: var(--cyan);
  font-weight: 700;
  padding-bottom: 5px;
  margin-bottom: 7px;
  border-bottom: 1.5px solid #ddf4f8;
}
.party-block .pb-name { font-size: 14px; font-weight: 700; color: var(--navy); }
.party-block .pb-line { font-size: 11.5px; color: #444; margin-top: 2px; }
.party-block .pb-small { font-size: 10.5px; color: var(--muted); margin-top: 1px; }

/* Pills d'info */
.pills-row {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 24px;
}
.pill {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 7px 13px;
  min-width: 110px;
}
.pill .pl { font-size: 9px; text-transform: uppercase; letter-spacing: 1.5px; color: var(--muted); font-weight: 600; }
.pill .pv { font-size: 13px; font-weight: 700; color: var(--navy); margin-top: 2px; }

/* Badge statut */
.status-badge {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .8px;
}
.badge-paid      { background: #dcfce7; color: #166534; }
.badge-partial   { background: #fef9c3; color: #854d0e; }
.badge-draft     { background: #f1f5f9; color: #475569; }
.badge-sent      { background: #dbeafe; color: #1e40af; }
.badge-accepted  { background: #d1fae5; color: #065f46; }
.badge-cancelled { background: #fee2e2; color: #991b1b; }

/* Tableau lignes */
.lines-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 12px; }
.lines-table thead tr { background: var(--navy); color: #fff; }
.lines-table thead th {
  padding: 9px 12px;
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: .8px;
  font-weight: 600;
  text-align: left;
}
.lines-table thead th.r,
.lines-table tbody td.r { text-align: right; white-space: nowrap; }
.lines-table tbody tr:nth-child(even) { background: #f9fbfc; }
.lines-table tbody td { padding: 9px 12px; border-bottom: 1px solid #edf2f7; vertical-align: top; }
.line-desc     { font-weight: 600; color: var(--navy); }
.line-desc-sub { font-size: 10.5px; color: #666; margin-top: 2px; }
.lines-table tfoot td { padding: 7px 12px; font-size: 11px; color: var(--muted); border-top: 2px solid var(--border); }

/* Totaux */
.totals-section { display: flex; justify-content: flex-end; margin-bottom: 20px; }
.totals-box {
  width: 300px;
  border: 1px solid var(--border);
  border-radius: 10px;
  overflow: hidden;
}
.t-row {
  display: flex;
  justify-content: space-between;
  padding: 8px 16px;
  border-bottom: 1px solid #f0f4f8;
  font-size: 12.5px;
}
.t-row:last-child { border-bottom: none; }
.t-row.ht    { color: var(--text); }
.t-row.sub   { color: var(--muted); font-size: 12px; }
.t-row.ttc   { background: var(--navy); color: #fff; font-size: 14px; font-weight: 800; padding: 11px 16px; }
.t-row.ttc .tv { color: var(--cyan); font-size: 15px; }
.t-row.paye  { background: #f0fdf4; color: #166534; font-size: 12px; font-weight: 600; }
.t-row.reste { background: #fffbf0; color: #7a5c1a; font-size: 13px; font-weight: 700;
               border-top: 2px solid var(--gold); }
.t-row.reste .tv { color: var(--gold); font-size: 14px; font-weight: 900; }

/* Montant en lettres */
.montant-lettres {
  background: linear-gradient(135deg, #f0fbff 0%, #e8f8fc 100%);
  border: 1px solid #b8e9f4;
  border-left: 4px solid var(--cyan);
  border-radius: 7px;
  padding: 12px 16px;
  margin-bottom: 20px;
  font-size: 12px;
}
.montant-lettres .ml-label {
  font-size: 9px;
  text-transform: uppercase;
  letter-spacing: 2px;
  color: var(--cyan);
  font-weight: 700;
  margin-bottom: 4px;
}
.montant-lettres .ml-text {
  font-style: italic;
  color: var(--navy);
  font-weight: 600;
  font-size: 12.5px;
}

/* Mode paiement */
.paiement-box {
  background: #fffbf0;
  border: 1px solid var(--gold);
  border-left: 4px solid var(--gold);
  border-radius: 6px;
  padding: 10px 14px;
  margin-bottom: 18px;
  font-size: 12px;
  color: #6b4f1a;
}

/* Notes */
.notes-box {
  background: var(--surface);
  border-radius: 6px;
  border: 1px solid var(--border);
  padding: 10px 14px;
  font-size: 11.5px;
  color: #555;
  margin-bottom: 22px;
}
.notes-box strong { color: var(--navy); display: block; margin-bottom: 4px; }

/* Zone signature */
.signature-zone {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  margin-top: 8px;
  margin-bottom: 24px;
}
.sig-block {
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 14px 16px;
}
.sig-block .sig-title {
  font-size: 9px;
  text-transform: uppercase;
  letter-spacing: 2px;
  color: var(--cyan);
  font-weight: 700;
  margin-bottom: 10px;
  padding-bottom: 6px;
  border-bottom: 1px solid #ddf4f8;
}
.sig-block .sig-name { font-size: 12px; font-weight: 700; color: var(--navy); margin-bottom: 4px; }
.sig-block .sig-line {
  border-bottom: 1px dashed #ccc;
  height: 32px;
  margin: 8px 0;
}
.sig-block .sig-field { font-size: 10px; color: var(--muted); margin-top: 2px; }

/* Footer */
.page-footer {
  background: #f8fafc;
  border-top: 2px solid var(--border);
  padding: 18px 36px;
  position: relative;
  z-index: 1;
}
.footer-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 18px; }
.footer-col .fc-title {
  font-size: 9px;
  text-transform: uppercase;
  letter-spacing: 2px;
  color: var(--cyan);
  font-weight: 700;
  margin-bottom: 7px;
  border-bottom: 1px solid #d0eef4;
  padding-bottom: 4px;
}
.footer-col .fc-line { font-size: 10px; color: #555; margin-bottom: 2px; line-height: 1.5; }
.footer-col .fc-line strong { color: var(--navy); }

/* ── Impression ── */
@media print {
  body { background: white; }
  .action-bar { display: none !important; }
  .page { margin: 0; box-shadow: none; width: 100%; min-height: 0; }
}
</style>
</head>
<body>

<div class="action-bar">
  <button class="btn-print" onclick="window.print()">🖨 Imprimer / PDF</button>
  <button class="btn-back" onclick="history.back()">← Retour</button>
  <span class="doc-ref"><?= htmlspecialchars($doc['numero']) ?></span>
</div>

<div class="page">

  <?php if ($wm): ?>
  <div class="watermark <?= $wm === 'PAYÉ' ? 'wm-paye' : ($wm === 'ANNULÉ' ? 'wm-annule' : 'wm-brouillon') ?>">
    <?= $wm ?>
  </div>
  <?php endif; ?>

  <!-- HEADER -->
  <div class="page-header">
    <div class="logo-area">
      <?php if ($logoSrc): ?>
        <img src="<?= $logoSrc ?>" alt="RYBSEN">
      <?php else: ?>
        <div class="logo-area-text">
          <div class="logo-text">RYBSEN</div>
          <div class="logo-tagline">Green Printing is our goal — Benefits are yours</div>
        </div>
      <?php endif; ?>
    </div>
    <div class="doc-title-area">
      <div class="doc-type-label"><?= htmlspecialchars($label) ?></div>
      <div class="doc-numero"><?= htmlspecialchars($doc['numero']) ?></div>
    </div>
  </div>
  <div class="accent-bar"></div>

  <div class="page-body">

    <!-- ÉMETTEUR / CLIENT -->
    <div class="parties-row">
      <div class="party-block">
        <div class="pb-label">Émetteur</div>
        <div class="pb-name">RYBSEN</div>
        <div class="pb-line">Centre Millenium La Marsa — Tunisie</div>
        <div class="pb-small">Matricule fiscale : 1829004 P/A/M/000</div>
        <div class="pb-small">Tél : +216 95 823 432 · yrezgui@rybsen.fr</div>
      </div>
      <div class="party-block">
        <div class="pb-label">Facturé à</div>
        <div class="pb-name"><?= htmlspecialchars($doc['client_nom']) ?></div>
        <?php if ($doc['client_adresse']): ?>
          <div class="pb-line"><?= nl2br(htmlspecialchars($doc['client_adresse'])) ?></div>
        <?php endif; ?>
        <?php if ($doc['client_pays']): ?>
          <div class="pb-small"><?= htmlspecialchars($doc['client_pays']) ?></div>
        <?php endif; ?>
        <?php if ($doc['client_mf']): ?>
          <div class="pb-small">MF / TVA : <?= htmlspecialchars($doc['client_mf']) ?></div>
        <?php endif; ?>
        <?php if ($doc['client_email']): ?>
          <div class="pb-small"><?= htmlspecialchars($doc['client_email']) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- PILLS -->
    <div class="pills-row">
      <div class="pill">
        <div class="pl">N° document</div>
        <div class="pv"><?= htmlspecialchars($doc['numero']) ?></div>
      </div>
      <?php if ($doc['date_document']): ?>
      <div class="pill">
        <div class="pl">Date</div>
        <div class="pv"><?= date('d/m/Y', strtotime($doc['date_document'])) ?></div>
      </div>
      <?php endif; ?>
      <?php if ($doc['date_echeance']): ?>
      <div class="pill">
        <div class="pl">Échéance paiement</div>
        <div class="pv"><?= date('d/m/Y', strtotime($doc['date_echeance'])) ?></div>
      </div>
      <?php endif; ?>
      <?php if ($doc['date_validite']): ?>
      <div class="pill">
        <div class="pl">Valable jusqu'au</div>
        <div class="pv"><?= date('d/m/Y', strtotime($doc['date_validite'])) ?></div>
      </div>
      <?php endif; ?>
    </div>

    <!-- TABLEAU DES LIGNES -->
    <table class="lines-table">
      <thead>
        <tr>
          <th style="width:26px">#</th>
          <th>Description</th>
          <th class="r" style="width:60px">Qté</th>
          <th class="r" style="width:130px">P.U. HT</th>
          <th class="r" style="width:130px">Total HT</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($lignes as $i => $l): ?>
        <tr>
          <td style="color:var(--muted);font-size:11px"><?= $i + 1 ?></td>
          <td>
            <?php
            $lines = explode("\n", $l['description']);
            echo '<div class="line-desc">' . htmlspecialchars($lines[0]) . '</div>';
            if (count($lines) > 1) {
              $sub = implode("\n", array_slice($lines, 1));
              echo '<div class="line-desc-sub">' . nl2br(htmlspecialchars(trim($sub))) . '</div>';
            }
            ?>
          </td>
          <td class="r"><?= number_format(floatval($l['quantite']), floatval($l['quantite']) == intval($l['quantite']) ? 0 : 3) ?></td>
          <td class="r"><?= fmtNum($l['prix_unitaire_ht'], $doc['devise']) ?></td>
          <td class="r"><strong><?= fmtNum($l['total_ht'], $doc['devise']) ?></strong></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- TOTAUX -->
    <div class="totals-section">
      <div class="totals-box">
        <div class="t-row ht">
          <span>Sous-total HT</span>
          <span><?= fmtNum($doc['sous_total_ht'], $doc['devise']) ?></span>
        </div>
        <?php if (floatval($doc['taux_tva']) > 0): ?>
        <div class="t-row sub">
          <span>TVA <?= number_format(floatval($doc['taux_tva']), 0) ?>%</span>
          <span><?= fmtNum($doc['montant_tva'], $doc['devise']) ?></span>
        </div>
        <?php endif; ?>
        <?php if (floatval($doc['timbre']) > 0): ?>
        <div class="t-row sub">
          <span>Timbre fiscal</span>
          <span><?= fmtNum($doc['timbre'], $doc['devise']) ?></span>
        </div>
        <?php endif; ?>
        <div class="t-row ttc">
          <span>Total TTC</span>
          <span class="tv"><?= fmtNum($doc['total_ttc'], $doc['devise']) ?></span>
        </div>
        <?php if ($isPartiel): ?>
        <div class="t-row paye">
          <span>✓ Déjà réglé</span>
          <span><?= fmtNum($montant_paye, $doc['devise']) ?></span>
        </div>
        <div class="t-row reste">
          <span>Reste à payer</span>
          <span class="tv"><?= fmtNum($reste, $doc['devise']) ?></span>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ARRÊTÉ À LA SOMME DE -->
    <?php if ($doc['type'] !== 'Bon de livraison'): ?>
    <div class="montant-lettres">
      <div class="ml-label">Arrêté à la somme de</div>
      <div class="ml-text">
        <?= htmlspecialchars(montantEnLettres(floatval($doc['total_ttc']), $doc['devise'])) ?>
        (TTC)<?php if ($isPartiel): ?> — Solde restant :
          <?= htmlspecialchars(montantEnLettres($reste, $doc['devise'])) ?>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- MODE DE PAIEMENT -->
    <?php if ($doc['mode_paiement']): ?>
    <div class="paiement-box">
      <strong>Mode de paiement :</strong> <?= htmlspecialchars($doc['mode_paiement']) ?>
    </div>
    <?php endif; ?>

    <!-- NOTES -->
    <?php if ($doc['notes']): ?>
    <div class="notes-box">
      <strong>Notes :</strong>
      <?= nl2br(htmlspecialchars($doc['notes'])) ?>
    </div>
    <?php endif; ?>

    <!-- ZONE SIGNATURE (Bon de livraison uniquement) -->
    <?php if ($doc['type'] === 'Bon de livraison'): ?>
    <div class="signature-zone">
      <div class="sig-block">
        <div class="sig-title">Signature &amp; Cachet — Émetteur</div>
        <div class="sig-name">RYBSEN — Yassine Rezgui</div>
        <div class="sig-line"></div>
        <div class="sig-field">Date : ___________________</div>
      </div>
      <div class="sig-block">
        <div class="sig-title">Lu &amp; Approuvé — Client</div>
        <div class="sig-name"><?= htmlspecialchars($doc['client_nom']) ?></div>
        <div class="sig-line"></div>
        <div class="sig-field">Date : ___________________</div>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /page-body -->

  <!-- FOOTER -->
  <div class="page-footer">
    <div class="footer-grid">
      <div class="footer-col">
        <div class="fc-title">Siège social</div>
        <div class="fc-line"><strong>RYBSEN</strong></div>
        <div class="fc-line">Centre Millenium La Marsa — Tunisie</div>
        <div class="fc-line">Tél : +216 95 823 432</div>
        <div class="fc-line">MF : 1829004 P/A/M/000</div>
      </div>
      <div class="footer-col">
        <div class="fc-title">Contact</div>
        <div class="fc-line"><strong>Rezgui Yassine</strong></div>
        <div class="fc-line">+216 95 823 432</div>
        <div class="fc-line">yrezgui@rybsen.fr</div>
      </div>
      <div class="footer-col">
        <div class="fc-title">Coordonnées bancaires</div>
        <div class="fc-line"><strong>ATTIJARI BANK</strong> — Ag. Les Jasmins</div>
        <div class="fc-line">RIB : 04 058 1440085732154 27</div>
        <div class="fc-line">SWIFT/BIC : BSTUTNTT</div>
        <div class="fc-line" style="margin-top:4px;color:#aaa;font-size:9px">Document généré par RYBSEN CRM</div>
      </div>
    </div>
  </div>

</div><!-- /page -->

</body>
</html>
