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

function fmtNum($n, $d='TND') {
    return number_format(floatval($n), 3, ',', ' ') . ' ' . $d;
}

$reste = max(0, floatval($doc['total_ttc']) - $montant_paye);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($doc['numero']) ?> — RYBSEN</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: 'Helvetica Neue', Arial, sans-serif;
  background: #f0f2f5;
  color: #1a1a2e;
  font-size: 13px;
  line-height: 1.5;
}

.print-btn-bar {
  background: #1A3A52;
  padding: 12px 40px;
  display: flex;
  align-items: center;
  gap: 12px;
}
.print-btn-bar button {
  padding: 9px 22px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-size: 13px;
  font-weight: 600;
}
.btn-print { background: #4A9B8F; color: #fff; }
.btn-back { background: rgba(255,255,255,.15); color: #fff; }
.btn-back:hover { background: rgba(255,255,255,.25); }

/* PAGE */
.page {
  background: #fff;
  width: 210mm;
  min-height: 297mm;
  margin: 32px auto;
  box-shadow: 0 4px 40px rgba(0,0,0,.15);
  display: flex;
  flex-direction: column;
  position: relative;
  overflow: hidden;
}

/* HEADER BAND */
.page-header {
  background: #1A3A52;
  padding: 28px 36px 24px;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}
.doc-type-label {
  font-size: 28px;
  font-weight: 900;
  color: #fff;
  letter-spacing: 2px;
  text-transform: uppercase;
}
.doc-type-sub {
  font-size: 11px;
  color: #4A9B8F;
  text-transform: uppercase;
  letter-spacing: 3px;
  margin-top: 4px;
}
.logo-area {
  text-align: right;
}
.logo-text {
  font-size: 30px;
  font-weight: 900;
  color: #fff;
  letter-spacing: 3px;
}
.logo-tagline {
  font-size: 9px;
  color: #4A9B8F;
  letter-spacing: 2px;
  text-transform: uppercase;
  margin-top: 2px;
}
.teal-bar {
  height: 4px;
  background: linear-gradient(90deg, #4A9B8F, #E8A44C);
}

/* BODY CONTENT */
.page-body { padding: 30px 36px; flex: 1; }

/* META ROW */
.meta-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
  margin-bottom: 28px;
}
.meta-block { }
.meta-block .lbl {
  font-size: 9px;
  text-transform: uppercase;
  letter-spacing: 2px;
  color: #4A9B8F;
  font-weight: 700;
  margin-bottom: 6px;
  border-bottom: 1px solid #e8f4f2;
  padding-bottom: 4px;
}
.meta-block .val {
  font-size: 14px;
  font-weight: 700;
  color: #1A3A52;
}
.meta-block .sub { font-size: 12px; color: #555; margin-top: 2px; }
.meta-block .small { font-size: 11px; color: #888; }

/* DOC INFO PILLS */
.doc-info-row {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 28px;
}
.info-pill {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 8px 14px;
}
.info-pill .ip-lbl {
  font-size: 9px;
  text-transform: uppercase;
  letter-spacing: 1.5px;
  color: #888;
  font-weight: 600;
}
.info-pill .ip-val {
  font-size: 13px;
  font-weight: 700;
  color: #1A3A52;
  margin-top: 2px;
}

/* LINES TABLE */
.lines-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 20px;
}
.lines-table thead tr {
  background: #1A3A52;
  color: #fff;
}
.lines-table thead th {
  padding: 10px 12px;
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 1px;
  font-weight: 600;
  text-align: left;
}
.lines-table thead th:last-child,
.lines-table tbody td:last-child {
  text-align: right;
}
.lines-table tbody tr:nth-child(even) { background: #f8fafc; }
.lines-table tbody tr:hover { background: #eef6f5; }
.lines-table tbody td {
  padding: 10px 12px;
  border-bottom: 1px solid #f0f0f0;
  font-size: 12px;
  vertical-align: top;
}
.lines-table tbody td:not(:first-child) { text-align: right; white-space: nowrap; }
.line-desc { font-weight: 600; color: #1A3A52; }
.line-desc-sub { font-size: 11px; color: #666; margin-top: 2px; font-weight: 400; }

/* TOTALS */
.totals-section {
  display: flex;
  justify-content: flex-end;
  margin-bottom: 24px;
}
.totals-box {
  width: 280px;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  overflow: hidden;
}
.totals-row {
  display: flex;
  justify-content: space-between;
  padding: 9px 16px;
  border-bottom: 1px solid #f0f0f0;
  font-size: 13px;
}
.totals-row:last-child { border-bottom: none; }
.totals-row.ht .t-val { color: #1A3A52; font-weight: 600; }
.totals-row.tva-row { color: #666; font-size: 12px; }
.totals-row.timbre-row { color: #666; font-size: 12px; }
.totals-row.ttc-row {
  background: #1A3A52;
  color: #fff;
  font-size: 15px;
  font-weight: 800;
  padding: 12px 16px;
}
.totals-row.ttc-row .t-val { color: #4A9B8F; font-size: 16px; }

/* PAYMENT INFO */
.payment-box {
  background: #fffbf0;
  border: 1px solid #E8A44C;
  border-left: 4px solid #E8A44C;
  border-radius: 6px;
  padding: 12px 16px;
  margin-bottom: 24px;
  font-size: 12px;
  color: #7a5c1a;
}
.payment-box strong { color: #1A3A52; }

/* PAID BADGE */
.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1px;
}
.badge-paid { background: #dcfce7; color: #166534; }
.badge-partial { background: #fef9c3; color: #854d0e; }
.badge-draft { background: #f1f5f9; color: #475569; }
.badge-sent { background: #dbeafe; color: #1e40af; }
.badge-accepted { background: #d1fae5; color: #065f46; }
.badge-cancelled { background: #fee2e2; color: #991b1b; }

/* FOOTER */
.page-footer {
  background: #f8fafc;
  border-top: 2px solid #e2e8f0;
  padding: 20px 36px;
}
.footer-grid {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 20px;
}
.footer-col .fc-title {
  font-size: 9px;
  text-transform: uppercase;
  letter-spacing: 2px;
  color: #4A9B8F;
  font-weight: 700;
  margin-bottom: 8px;
  border-bottom: 1px solid #d1e9e6;
  padding-bottom: 4px;
}
.footer-col .fc-line {
  font-size: 10px;
  color: #555;
  margin-bottom: 3px;
  line-height: 1.5;
}
.footer-col .fc-line strong { color: #1A3A52; }

/* PRINT */
@media print {
  body { background: white; }
  .print-btn-bar { display: none !important; }
  .page {
    margin: 0;
    box-shadow: none;
    width: 100%;
    min-height: 0;
  }
}
</style>
</head>
<body>

<div class="print-btn-bar">
  <button class="btn-print" onclick="window.print()">🖨 Imprimer / PDF</button>
  <button class="btn-back" onclick="history.back()">← Retour</button>
</div>

<div class="page">

  <!-- HEADER -->
  <div class="page-header">
    <div>
      <div class="doc-type-label"><?= htmlspecialchars($label) ?></div>
      <div class="doc-type-sub">GREEN PRINTING IS OUR GOAL</div>
    </div>
    <div class="logo-area">
      <div class="logo-text">RYBSEN</div>
      <div class="logo-tagline">Benefits are yours</div>
    </div>
  </div>
  <div class="teal-bar"></div>

  <div class="page-body">

    <!-- META: SELLER + CLIENT -->
    <div class="meta-row">
      <div class="meta-block">
        <div class="lbl">Émetteur</div>
        <div class="val">RYBSEN</div>
        <div class="sub">Centre Millenium La Marsa</div>
        <div class="small">Matricule fiscale : 1829004 P/A/M/000</div>
        <div class="small">Tél : +216 95 823 432 · yrezgui@rybsen.fr</div>
      </div>
      <div class="meta-block">
        <div class="lbl">Client</div>
        <div class="val"><?= htmlspecialchars($doc['client_nom']) ?></div>
        <?php if ($doc['client_adresse']): ?>
        <div class="sub"><?= nl2br(htmlspecialchars($doc['client_adresse'])) ?></div>
        <?php endif; ?>
        <?php if ($doc['client_pays']): ?>
        <div class="small"><?= htmlspecialchars($doc['client_pays']) ?></div>
        <?php endif; ?>
        <?php if ($doc['client_mf']): ?>
        <div class="small">MF : <?= htmlspecialchars($doc['client_mf']) ?></div>
        <?php endif; ?>
        <?php if ($doc['client_email']): ?>
        <div class="small"><?= htmlspecialchars($doc['client_email']) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- DOC INFO PILLS -->
    <div class="doc-info-row">
      <div class="info-pill">
        <div class="ip-lbl">N° document</div>
        <div class="ip-val"><?= htmlspecialchars($doc['numero']) ?></div>
      </div>
      <?php if ($doc['date_document']): ?>
      <div class="info-pill">
        <div class="ip-lbl">Date</div>
        <div class="ip-val"><?= date('d/m/Y', strtotime($doc['date_document'])) ?></div>
      </div>
      <?php endif; ?>
      <?php if ($doc['date_echeance']): ?>
      <div class="info-pill">
        <div class="ip-lbl">Échéance</div>
        <div class="ip-val"><?= date('d/m/Y', strtotime($doc['date_echeance'])) ?></div>
      </div>
      <?php endif; ?>
      <?php if ($doc['date_validite']): ?>
      <div class="info-pill">
        <div class="ip-lbl">Valable jusqu'au</div>
        <div class="ip-val"><?= date('d/m/Y', strtotime($doc['date_validite'])) ?></div>
      </div>
      <?php endif; ?>
      <div class="info-pill">
        <div class="ip-lbl">Statut</div>
        <div class="ip-val">
          <?php
          $bClass = [
            'Payé'=>'badge-paid','Partiellement payé'=>'badge-partial',
            'Brouillon'=>'badge-draft','Envoyé'=>'badge-sent',
            'Accepté'=>'badge-accepted','Annulé'=>'badge-cancelled'
          ][$doc['statut']] ?? 'badge-draft';
          ?>
          <span class="status-badge <?= $bClass ?>"><?= htmlspecialchars($doc['statut']) ?></span>
        </div>
      </div>
    </div>

    <!-- LINES TABLE -->
    <table class="lines-table">
      <thead>
        <tr>
          <th>Description</th>
          <th style="width:80px">Qté</th>
          <th style="width:140px">Prix unit. HT</th>
          <th style="width:140px">Total HT</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($lignes as $i => $l): ?>
        <tr>
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
          <td><?= number_format(floatval($l['quantite']), 0) ?></td>
          <td><?= fmtNum($l['prix_unitaire_ht'], $doc['devise']) ?></td>
          <td><strong><?= fmtNum($l['total_ht'], $doc['devise']) ?></strong></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- TOTALS -->
    <div class="totals-section">
      <div class="totals-box">
        <div class="totals-row ht">
          <span>Total HT</span>
          <span class="t-val"><?= fmtNum($doc['sous_total_ht'], $doc['devise']) ?></span>
        </div>
        <?php if (floatval($doc['taux_tva']) > 0): ?>
        <div class="totals-row tva-row">
          <span>TVA <?= number_format(floatval($doc['taux_tva']), 0) ?>%</span>
          <span><?= fmtNum($doc['montant_tva'], $doc['devise']) ?></span>
        </div>
        <?php endif; ?>
        <?php if (floatval($doc['timbre']) > 0): ?>
        <div class="totals-row timbre-row">
          <span>Timbre fiscal</span>
          <span><?= fmtNum($doc['timbre'], $doc['devise']) ?></span>
        </div>
        <?php endif; ?>
        <div class="totals-row ttc-row">
          <span>Total TTC</span>
          <span class="t-val"><?= fmtNum($doc['total_ttc'], $doc['devise']) ?></span>
        </div>
      </div>
    </div>

    <!-- PAYMENT TERMS -->
    <?php if ($doc['mode_paiement']): ?>
    <div class="payment-box">
      <strong>Mode de paiement :</strong> <?= htmlspecialchars($doc['mode_paiement']) ?>
    </div>
    <?php endif; ?>

    <!-- NOTES -->
    <?php if ($doc['notes']): ?>
    <div style="background:#f8fafc;border-radius:6px;padding:12px 16px;font-size:12px;color:#555;margin-bottom:16px">
      <strong style="color:#1A3A52;display:block;margin-bottom:4px">Notes :</strong>
      <?= nl2br(htmlspecialchars($doc['notes'])) ?>
    </div>
    <?php endif; ?>

  </div><!-- /page-body -->

  <!-- FOOTER -->
  <div class="page-footer">
    <div class="footer-grid">
      <div class="footer-col">
        <div class="fc-title">Siège social</div>
        <div class="fc-line"><strong>RYBSEN</strong></div>
        <div class="fc-line">Centre Millenium La Marsa</div>
        <div class="fc-line">Tél : +216 95 823 432</div>
        <div class="fc-line">TVA : 1829004 P/A/M/000</div>
      </div>
      <div class="footer-col">
        <div class="fc-title">Coordonnées</div>
        <div class="fc-line"><strong>Rezgui Yassine</strong></div>
        <div class="fc-line">Tél : +216 95 823 432</div>
        <div class="fc-line">Mail : yrezgui@rybsen.fr</div>
        <div class="fc-line">MF : 1829004 P/A/M/000</div>
      </div>
      <div class="footer-col">
        <div class="fc-title">Détails bancaires</div>
        <div class="fc-line"><strong>ATTIJARI BANK</strong></div>
        <div class="fc-line">Agence Les Jasmins</div>
        <div class="fc-line">RIB : 04 058 1440085732154 27</div>
        <div class="fc-line">SWIFT/BIC : BSTUTNTT</div>
      </div>
    </div>
  </div>

</div><!-- /page -->

</body>
</html>
