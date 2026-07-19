<?php
require_once '../config.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? ($_GET['action'] ?? '');
$db = getDB();
$user = currentUser();

function respond($data) { echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function error400($msg) { http_response_code(400); respond(['error' => $msg]); }
function apiRequireRole(array $roles) {
    $u = currentUser();
    if (!$u || !in_array($u['role'], $roles, true)) {
        http_response_code(403);
        respond(['error' => 'Accès refusé pour ce rôle']);
    }
}

// ──────────────────────────────────────────
// DASHBOARD
// ──────────────────────────────────────────
if ($action === 'dashboard_stats') {
    $stats = [];
    $stats['clients_actifs'] = $db->query("SELECT COUNT(*) FROM clients WHERE actif=1")->fetchColumn();
    $stats['franchises'] = $db->query("SELECT COUNT(*) FROM franchises")->fetchColumn();
    $stats['points_vente'] = $db->query("SELECT COUNT(*) FROM points_vente WHERE actif=1")->fetchColumn();
    $stats['produits_actifs'] = $db->query("SELECT COUNT(*) FROM produits WHERE actif=1")->fetchColumn();
    $stats['commandes_en_cours'] = $db->query("SELECT COUNT(*) FROM commandes WHERE statut NOT IN ('livree','facturee','annulee')")->fetchColumn();
    $stats['matieres_stock_bas'] = $db->query("SELECT COUNT(*) FROM v_stock_bas")->fetchColumn();
    $stats['factures_impayees'] = $db->query("SELECT COUNT(*) FROM factures WHERE statut IN ('emise','partiellement_payee','impayee')")->fetchColumn();
    $stats['encours_total'] = $db->query("SELECT COALESCE(SUM(encours),0) FROM v_encours_clients")->fetchColumn();
    respond($stats);
}

// ──────────────────────────────────────────
// CLIENTS À TERME
// ──────────────────────────────────────────
if ($action === 'cli_list') {
    apiRequireRole(['admin','labo']);
    $rows = $db->query("SELECT * FROM clients WHERE type_client='terme' ORDER BY nom")->fetchAll();
    respond($rows);
}
if ($action === 'cli_save') {
    apiRequireRole(['admin','labo']);
    $d = $body;
    if (empty($d['nom'])) error400('Nom requis');
    if (!empty($d['id'])) {
        $stmt = $db->prepare("UPDATE clients SET nom=?,contact_nom=?,telephone=?,email=?,adresse=?,actif=? WHERE id=? AND type_client='terme'");
        $stmt->execute([$d['nom'],$d['contact_nom'],$d['telephone'],$d['email'],$d['adresse'],$d['actif']??1,$d['id']]);
    } else {
        $stmt = $db->prepare("INSERT INTO clients (nom,type_client,contact_nom,telephone,email,adresse,actif) VALUES (?,'terme',?,?,?,?,?)");
        $stmt->execute([$d['nom'],$d['contact_nom'],$d['telephone'],$d['email'],$d['adresse'],$d['actif']??1]);
    }
    respond(['ok' => true]);
}
if ($action === 'cli_delete') {
    apiRequireRole(['admin','labo']);
    $db->prepare("DELETE FROM clients WHERE id=? AND type_client='terme'")->execute([$body['id']]);
    respond(['ok' => true]);
}

// ──────────────────────────────────────────
// CATALOGUE — PRODUITS
// ──────────────────────────────────────────
if ($action === 'prod_list') {
    apiRequireRole(['admin','labo']);
    $rows = $db->query("SELECT * FROM produits ORDER BY categorie, nom")->fetchAll();
    respond($rows);
}
if ($action === 'prod_save') {
    apiRequireRole(['admin','labo']);
    $d = $body;
    if (empty($d['nom'])) error400('Nom requis');
    if (!empty($d['id'])) {
        $stmt = $db->prepare("UPDATE produits SET nom=?,categorie=?,prix_vente=?,unite=?,actif=? WHERE id=?");
        $stmt->execute([$d['nom'],$d['categorie']??'Viennoiserie',$d['prix_vente']??0,$d['unite']??'pièce',$d['actif']??1,$d['id']]);
    } else {
        $stmt = $db->prepare("INSERT INTO produits (nom,categorie,prix_vente,unite,actif) VALUES (?,?,?,?,?)");
        $stmt->execute([$d['nom'],$d['categorie']??'Viennoiserie',$d['prix_vente']??0,$d['unite']??'pièce',$d['actif']??1]);
    }
    respond(['ok' => true]);
}
if ($action === 'prod_delete') {
    apiRequireRole(['admin','labo']);
    $db->prepare("DELETE FROM produits WHERE id=?")->execute([$body['id']]);
    respond(['ok' => true]);
}

// ──────────────────────────────────────────
// CATALOGUE — MATIÈRES PREMIÈRES
// ──────────────────────────────────────────
if ($action === 'mp_list') {
    apiRequireRole(['admin','labo']);
    $rows = $db->query("SELECT m.*, f.nom as fournisseur_nom FROM matieres_premieres m LEFT JOIN fournisseurs f ON f.id=m.fournisseur_id ORDER BY m.nom")->fetchAll();
    respond($rows);
}
if ($action === 'mp_save') {
    apiRequireRole(['admin','labo']);
    $d = $body;
    if (empty($d['nom'])) error400('Nom requis');
    if (!empty($d['id'])) {
        $stmt = $db->prepare("UPDATE matieres_premieres SET nom=?,unite=?,stock_actuel=?,seuil_alerte=?,prix_unitaire=?,fournisseur_id=?,actif=? WHERE id=?");
        $stmt->execute([$d['nom'],$d['unite']??'kg',$d['stock_actuel']??0,$d['seuil_alerte']??0,$d['prix_unitaire']??0,$d['fournisseur_id']?:null,$d['actif']??1,$d['id']]);
    } else {
        $stmt = $db->prepare("INSERT INTO matieres_premieres (nom,unite,stock_actuel,seuil_alerte,prix_unitaire,fournisseur_id,actif) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$d['nom'],$d['unite']??'kg',$d['stock_actuel']??0,$d['seuil_alerte']??0,$d['prix_unitaire']??0,$d['fournisseur_id']?:null,$d['actif']??1]);
    }
    respond(['ok' => true]);
}
if ($action === 'mp_delete') {
    apiRequireRole(['admin','labo']);
    $db->prepare("DELETE FROM matieres_premieres WHERE id=?")->execute([$body['id']]);
    respond(['ok' => true]);
}

// ──────────────────────────────────────────
// CATALOGUE — RECETTES (nomenclature produit ↔ matières)
// ──────────────────────────────────────────
if ($action === 'recette_list') {
    apiRequireRole(['admin','labo']);
    $stmt = $db->prepare("SELECT r.*, m.nom as matiere_nom, m.unite as matiere_unite FROM recettes r JOIN matieres_premieres m ON m.id=r.matiere_id WHERE r.produit_id=? ORDER BY m.nom");
    $stmt->execute([$body['produit_id']]);
    respond($stmt->fetchAll());
}
if ($action === 'recette_save') {
    apiRequireRole(['admin','labo']);
    $d = $body;
    if (empty($d['produit_id']) || empty($d['matiere_id']) || !isset($d['quantite_necessaire'])) {
        error400('Produit, matière et quantité requis');
    }
    $stmt = $db->prepare("INSERT INTO recettes (produit_id,matiere_id,quantite_necessaire) VALUES (?,?,?)
        ON DUPLICATE KEY UPDATE quantite_necessaire=VALUES(quantite_necessaire)");
    $stmt->execute([$d['produit_id'],$d['matiere_id'],$d['quantite_necessaire']]);
    respond(['ok' => true]);
}
if ($action === 'recette_delete') {
    apiRequireRole(['admin','labo']);
    $db->prepare("DELETE FROM recettes WHERE id=?")->execute([$body['id']]);
    respond(['ok' => true]);
}

// ──────────────────────────────────────────
// FOURNISSEURS (pour select dans matières premières)
// ──────────────────────────────────────────
if ($action === 'four_list') {
    apiRequireRole(['admin','labo']);
    respond($db->query("SELECT * FROM fournisseurs ORDER BY nom")->fetchAll());
}
if ($action === 'four_save') {
    apiRequireRole(['admin','labo']);
    $d = $body;
    if (empty($d['nom'])) error400('Nom requis');
    $stmt = $db->prepare("INSERT INTO fournisseurs (nom,contact,telephone,email) VALUES (?,?,?,?)");
    $stmt->execute([$d['nom'],$d['contact'],$d['telephone'],$d['email']]);
    respond(['ok' => true, 'id' => $db->lastInsertId()]);
}

error400('Action inconnue: ' . $action);
