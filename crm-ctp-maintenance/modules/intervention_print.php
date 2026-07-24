<?php
require_once '../config.php';
requireRole(['admin','technicien','magasinier']);

$id = intval($_GET['id'] ?? 0);
if (!$id) die('ID requis');

$db = getDB();
$stmt = $db->prepare("SELECT i.*, m.modele, m.gamme, m.n_serie, m.technologie, m.localisation, m.compteur_plaques,
        cl.raison_sociale, cl.adresse, cl.ville, cl.contact_nom, cl.telephone AS client_tel,
        ct.numero AS contrat_numero, u.nom AS technicien_nom
    FROM interventions i
    JOIN machines m ON m.id=i.machine_id
    JOIN clients cl ON cl.id=i.client_id
    LEFT JOIN contrats ct ON ct.id=i.contrat_id
    LEFT JOIN users u ON u.id=i.technicien_id
    WHERE i.id=?");
$stmt->execute([$id]);
$iv = $stmt->fetch();
if (!$iv) die('Intervention introuvable');

$pst = $db->prepare("SELECT ip.*, p.reference, p.designation FROM intervention_pieces ip JOIN pieces p ON p.id=ip.piece_id WHERE ip.intervention_id=?");
$pst->execute([$id]);
$pieces = $pst->fetchAll();

$totalPieces = 0;
foreach ($pieces as $p) { $totalPieces += $p['quantite'] * $p['prix_unitaire']; }
$totalMO = (float)$iv['cout_main_oeuvre'];
$totalGlobal = $totalPieces + $totalMO;

function fmt($n) { return number_format((float)$n, 3, ',', ' ') . ' TND'; }
function fdate($d) { return $d ? date('d/m/Y', strtotime($d)) : '—'; }
function fdt($d) { return $d ? date('d/m/Y H:i', strtotime($d)) : '—'; }
function h($s) { return htmlspecialchars((string)$s); }
$typeLabels = ['preventive'=>'Maintenance préventive','corrective'=>'Réparation corrective','installation'=>'Installation','mise_a_jour'=>'Mise à jour'];
$technoLabels = ['thermique'=>'Thermique','violet'=>'Violet','uv'=>'UV','flexo'=>'Flexo','autre'=>'Autre'];
$statutLabels = ['nouvelle'=>'Nouvelle','planifiee'=>'Planifiée','en_cours'=>'En cours','en_attente_piece'=>'En attente pièce','resolue'=>'Résolue','cloturee'=>'Clôturée','annulee'=>'Annulée'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Bon d'intervention <?= h($iv['numero']) ?></title>
<style>
  *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
  :root { --charcoal:#23282D; --red:#DA291C; --yellow:#FFB81C; --border:#E3E6EA; --muted:#6B7280; }
  body { font-family:'Helvetica Neue',Arial,sans-serif; background:#eceef1; color:var(--charcoal); font-size:13px; line-height:1.5; }
  .bar { background:var(--charcoal); padding:12px 40px; display:flex; gap:12px; align-items:center; }
  .bar button, .bar a { padding:9px 20px; border:none; border-radius:8px; cursor:pointer; font-size:13px; font-weight:600; text-decoration:none; }
  .btn-print { background:var(--red); color:#fff; }
  .btn-back { background:rgba(255,255,255,.15); color:#fff; }
  .page { background:#fff; width:210mm; min-height:297mm; margin:28px auto; padding:18mm; box-shadow:0 4px 40px rgba(0,0,0,.15); }
  .head { display:flex; justify-content:space-between; align-items:flex-start; border-bottom:3px solid var(--charcoal); padding-bottom:16px; }
  .brand { display:flex; align-items:center; gap:12px; }
  .logo { width:52px; height:52px; background:var(--yellow); border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:26px; }
  .brand-name { font-size:20px; font-weight:bold; letter-spacing:1px; }
  .brand-sub { font-size:10px; color:var(--red); letter-spacing:2px; text-transform:uppercase; font-weight:700; }
  .doc-title { text-align:right; }
  .doc-title h1 { font-size:22px; color:var(--charcoal); letter-spacing:1px; }
  .doc-num { font-size:14px; color:var(--red); font-weight:700; margin-top:4px; }
  .doc-date { font-size:11px; color:var(--muted); margin-top:2px; }
  .cols { display:flex; gap:20px; margin-top:20px; }
  .box { flex:1; border:1px solid var(--border); border-radius:8px; padding:12px 14px; }
  .box h3 { font-size:10px; text-transform:uppercase; letter-spacing:1px; color:var(--muted); margin-bottom:8px; border-bottom:1px solid var(--border); padding-bottom:5px; }
  .row { display:flex; justify-content:space-between; gap:8px; padding:2px 0; }
  .row .k { color:var(--muted); }
  .row .v { font-weight:600; text-align:right; }
  .chips { margin-top:16px; display:flex; gap:8px; flex-wrap:wrap; }
  .chip { padding:4px 12px; border-radius:20px; font-size:11px; font-weight:700; }
  .chip.red { background:#FDEAE8; color:var(--red); }
  .chip.yellow { background:#FFF6E0; color:#8a6a10; }
  .chip.dark { background:#eef0f3; color:var(--charcoal); }
  .section { margin-top:18px; }
  .section h2 { font-size:11px; text-transform:uppercase; letter-spacing:1px; color:var(--charcoal); background:#f4f5f7; padding:7px 12px; border-radius:6px; }
  .section .content { padding:10px 12px; min-height:36px; border:1px solid var(--border); border-top:none; border-radius:0 0 6px 6px; white-space:pre-wrap; }
  table { width:100%; border-collapse:collapse; margin-top:8px; }
  th { background:var(--charcoal); color:#fff; font-size:10px; text-transform:uppercase; letter-spacing:.5px; padding:8px 10px; text-align:left; }
  td { padding:8px 10px; border-bottom:1px solid var(--border); font-size:12px; }
  td.num, th.num { text-align:right; }
  .totals { margin-top:12px; margin-left:auto; width:270px; }
  .totals .row { padding:4px 0; }
  .totals .grand { border-top:2px solid var(--charcoal); margin-top:6px; padding-top:8px; font-size:15px; }
  .signs { display:flex; gap:24px; margin-top:40px; }
  .sign { flex:1; }
  .sign .lbl { font-size:11px; color:var(--muted); margin-bottom:4px; }
  .sign .line { border-top:1px solid var(--charcoal); margin-top:52px; padding-top:6px; font-size:10px; color:var(--muted); }
  .foot { margin-top:26px; text-align:center; font-size:10px; color:var(--muted); border-top:1px solid var(--border); padding-top:10px; }
  @media print { body { background:#fff; } .bar { display:none; } .page { margin:0; box-shadow:none; width:auto; min-height:auto; padding:12mm; } }
</style>
</head>
<body>
<div class="bar">
  <button class="btn-print" onclick="window.print()">🖨️ Imprimer / PDF</button>
  <a class="btn-back" href="/modules/interventions.php">← Retour</a>
</div>

<div class="page">
  <div class="head">
    <div class="brand">
      <div class="logo">🖨️</div>
      <div>
        <div class="brand-name">CTP MAINTENANCE</div>
        <div class="brand-sub">PrePresse · Filiale Kodak</div>
      </div>
    </div>
    <div class="doc-title">
      <h1>BON D'INTERVENTION</h1>
      <div class="doc-num"><?= h($iv['numero']) ?></div>
      <div class="doc-date">Édité le <?= date('d/m/Y') ?></div>
    </div>
  </div>

  <div class="cols">
    <div class="box">
      <h3>Client</h3>
      <div class="row"><span class="k">Raison sociale</span><span class="v"><?= h($iv['raison_sociale']) ?></span></div>
      <div class="row"><span class="k">Contact</span><span class="v"><?= h($iv['contact_nom']) ?: '—' ?></span></div>
      <div class="row"><span class="k">Téléphone</span><span class="v"><?= h($iv['client_tel']) ?: '—' ?></span></div>
      <div class="row"><span class="k">Ville</span><span class="v"><?= h($iv['ville']) ?: '—' ?></span></div>
    </div>
    <div class="box">
      <h3>Machine CTP</h3>
      <div class="row"><span class="k">Modèle</span><span class="v"><?= h($iv['modele']) ?></span></div>
      <div class="row"><span class="k">N° série</span><span class="v"><?= h($iv['n_serie']) ?></span></div>
      <div class="row"><span class="k">Technologie</span><span class="v"><?= h($technoLabels[$iv['technologie']] ?? $iv['technologie']) ?></span></div>
      <div class="row"><span class="k">Compteur</span><span class="v"><?= number_format((int)$iv['compteur_plaques'], 0, ',', ' ') ?> pl.</span></div>
      <?php if ($iv['contrat_numero']): ?><div class="row"><span class="k">Contrat</span><span class="v"><?= h($iv['contrat_numero']) ?></span></div><?php endif; ?>
    </div>
  </div>

  <div class="chips">
    <span class="chip dark"><?= h($typeLabels[$iv['type']] ?? $iv['type']) ?></span>
    <span class="chip <?= in_array($iv['priorite'],['urgente','haute'])?'red':'dark' ?>">Priorité : <?= h($iv['priorite']) ?></span>
    <span class="chip <?= $iv['statut']==='cloturee'?'dark':'yellow' ?>"><?= h($statutLabels[$iv['statut']] ?? $iv['statut']) ?></span>
  </div>

  <div class="cols" style="margin-top:16px">
    <div class="box">
      <div class="row"><span class="k">Technicien</span><span class="v"><?= h($iv['technicien_nom']) ?: '—' ?></span></div>
      <div class="row"><span class="k">Compteur relevé</span><span class="v"><?= $iv['compteur_releve']!==null ? number_format((int)$iv['compteur_releve'],0,',',' ').' pl.' : '—' ?></span></div>
    </div>
    <div class="box">
      <div class="row"><span class="k">Début</span><span class="v"><?= fdt($iv['date_debut']) ?></span></div>
      <div class="row"><span class="k">Fin</span><span class="v"><?= fdt($iv['date_fin']) ?></span></div>
      <div class="row"><span class="k">Temps passé</span><span class="v"><?= rtrim(rtrim(number_format((float)$iv['temps_passe_h'],2,',',' '),'0'),',') ?> h</span></div>
    </div>
  </div>

  <div class="section"><h2>Objet / symptôme signalé</h2><div class="content"><?= h($iv['description']) ?: '—' ?></div></div>
  <div class="section"><h2>Diagnostic</h2><div class="content"><?= h($iv['diagnostic']) ?: '—' ?></div></div>
  <div class="section"><h2>Travaux réalisés / résolution</h2><div class="content"><?= h($iv['resolution']) ?: '—' ?></div></div>

  <?php if ($pieces): ?>
  <div class="section">
    <h2>Pièces utilisées</h2>
    <table>
      <thead><tr><th>Référence</th><th>Désignation</th><th class="num">Qté</th><th class="num">P.U.</th><th class="num">Total</th></tr></thead>
      <tbody>
        <?php foreach ($pieces as $p): ?>
        <tr>
          <td><?= h($p['reference']) ?></td>
          <td><?= h($p['designation']) ?></td>
          <td class="num"><?= (int)$p['quantite'] ?></td>
          <td class="num"><?= fmt($p['prix_unitaire']) ?></td>
          <td class="num"><?= fmt($p['quantite'] * $p['prix_unitaire']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <div class="totals">
    <div class="row"><span class="k">Total pièces</span><span class="v"><?= fmt($totalPieces) ?></span></div>
    <div class="row"><span class="k">Main d'œuvre</span><span class="v"><?= fmt($totalMO) ?></span></div>
    <div class="row grand"><span class="k">TOTAL</span><span class="v"><?= fmt($totalGlobal) ?></span></div>
  </div>

  <div class="signs">
    <div class="sign"><div class="lbl">Le technicien</div><div class="line"><?= h($iv['technicien_nom']) ?: 'Nom / signature' ?></div></div>
    <div class="sign"><div class="lbl">Le client (nom, cachet & signature)</div><div class="line"><?= h($iv['contact_nom']) ?: 'Bon pour accord' ?></div></div>
  </div>

  <div class="foot">CTP Maintenance — Service technique PrePresse CTP · Filiale Kodak · Bon d'intervention <?= h($iv['numero']) ?></div>
</div>
</body>
</html>
