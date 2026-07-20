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

// Génère un numéro séquentiel type PREFIX-ANNEE-000
function nextDocNumero($db, string $table, string $prefix): string {
    $year = date('Y');
    $stmt = $db->prepare("SELECT numero FROM $table WHERE numero LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute(["$prefix-$year-%"]);
    $last = $stmt->fetchColumn();
    $next = 1;
    if ($last) {
        $parts = explode('-', $last);
        $next = intval(end($parts)) + 1;
    }
    return $prefix . '-' . $year . '-' . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
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
    // Lecture seule ouverte aussi aux portails externes (aucune donnée de coût/recette ici) ;
    // les portails externes ne voient que les produits actifs (commandables).
    apiRequireRole(['admin','labo','franchise','client_terme','point_vente']);
    if (in_array($user['role'], ['admin','labo'], true)) {
        $rows = $db->query("SELECT * FROM produits ORDER BY categorie, nom")->fetchAll();
    } else {
        $rows = $db->query("SELECT id,nom,categorie,prix_vente,unite FROM produits WHERE actif=1 ORDER BY categorie, nom")->fetchAll();
    }
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

// ──────────────────────────────────────────
// FRANCHISES
// ──────────────────────────────────────────
if ($action === 'fr_list') {
    apiRequireRole(['admin','labo']);
    $rows = $db->query("SELECT c.*, f.id as franchise_id, f.mode_paiement, f.territoire
        FROM clients c JOIN franchises f ON f.client_id = c.id
        WHERE c.type_client='franchise' ORDER BY c.nom")->fetchAll();
    respond($rows);
}
if ($action === 'fr_save') {
    apiRequireRole(['admin','labo']);
    $d = $body;
    if (empty($d['nom'])) error400('Nom requis');
    if (!empty($d['id'])) {
        $stmt = $db->prepare("UPDATE clients SET nom=?,contact_nom=?,telephone=?,email=?,adresse=?,actif=? WHERE id=? AND type_client='franchise'");
        $stmt->execute([$d['nom'],$d['contact_nom'],$d['telephone'],$d['email'],$d['adresse'],$d['actif']??1,$d['id']]);
        $stmt2 = $db->prepare("UPDATE franchises SET mode_paiement=?,territoire=? WHERE client_id=?");
        $stmt2->execute([$d['mode_paiement']??'libre_choix',$d['territoire'],$d['id']]);
    } else {
        $stmt = $db->prepare("INSERT INTO clients (nom,type_client,contact_nom,telephone,email,adresse,actif) VALUES (?,'franchise',?,?,?,?,?)");
        $stmt->execute([$d['nom'],$d['contact_nom'],$d['telephone'],$d['email'],$d['adresse'],$d['actif']??1]);
        $clientId = $db->lastInsertId();
        $stmt2 = $db->prepare("INSERT INTO franchises (client_id,mode_paiement,territoire) VALUES (?,?,?)");
        $stmt2->execute([$clientId,$d['mode_paiement']??'libre_choix',$d['territoire']]);
    }
    respond(['ok' => true]);
}
if ($action === 'fr_delete') {
    apiRequireRole(['admin','labo']);
    $db->prepare("DELETE FROM clients WHERE id=? AND type_client='franchise'")->execute([$body['id']]);
    respond(['ok' => true]);
}

// ──────────────────────────────────────────
// POINTS DE VENTE
// ──────────────────────────────────────────
if ($action === 'pv_list') {
    apiRequireRole(['admin','labo','point_vente']);
    respond($db->query("SELECT * FROM points_vente ORDER BY nom")->fetchAll());
}
if ($action === 'pv_save') {
    apiRequireRole(['admin','labo']);
    $d = $body;
    if (empty($d['nom'])) error400('Nom requis');
    if (!empty($d['id'])) {
        $stmt = $db->prepare("UPDATE points_vente SET nom=?,adresse=?,responsable=?,telephone=?,actif=? WHERE id=?");
        $stmt->execute([$d['nom'],$d['adresse'],$d['responsable'],$d['telephone'],$d['actif']??1,$d['id']]);
    } else {
        $stmt = $db->prepare("INSERT INTO points_vente (nom,adresse,responsable,telephone,actif) VALUES (?,?,?,?,?)");
        $stmt->execute([$d['nom'],$d['adresse'],$d['responsable'],$d['telephone'],$d['actif']??1]);
    }
    respond(['ok' => true]);
}
if ($action === 'pv_delete') {
    apiRequireRole(['admin','labo']);
    $db->prepare("DELETE FROM points_vente WHERE id=?")->execute([$body['id']]);
    respond(['ok' => true]);
}

// ──────────────────────────────────────────
// ÉVÉNEMENTS SPÉCIAUX
// ──────────────────────────────────────────
if ($action === 'evt_list') {
    apiRequireRole(['admin','labo']);
    respond($db->query("SELECT * FROM evenements_speciaux ORDER BY date_debut DESC")->fetchAll());
}
if ($action === 'evt_save') {
    apiRequireRole(['admin']);
    $d = $body;
    if (empty($d['nom']) || empty($d['date_debut']) || empty($d['date_fin'])) error400('Nom et dates requis');
    if (!empty($d['id'])) {
        $stmt = $db->prepare("UPDATE evenements_speciaux SET nom=?,type=?,date_debut=?,date_fin=?,notes=? WHERE id=?");
        $stmt->execute([$d['nom'],$d['type']??'autre',$d['date_debut'],$d['date_fin'],$d['notes'],$d['id']]);
    } else {
        $stmt = $db->prepare("INSERT INTO evenements_speciaux (nom,type,date_debut,date_fin,notes) VALUES (?,?,?,?,?)");
        $stmt->execute([$d['nom'],$d['type']??'autre',$d['date_debut'],$d['date_fin'],$d['notes']]);
    }
    respond(['ok' => true]);
}
if ($action === 'evt_delete') {
    apiRequireRole(['admin']);
    $db->prepare("DELETE FROM evenements_speciaux WHERE id=?")->execute([$body['id']]);
    respond(['ok' => true]);
}

// ──────────────────────────────────────────
// COMMANDES
// ──────────────────────────────────────────
function commandeDestinationLabel($db, $c) {
    if ($c['client_id']) {
        $s = $db->prepare("SELECT nom FROM clients WHERE id=?");
        $s->execute([$c['client_id']]);
        return $s->fetchColumn();
    }
    if ($c['point_vente_id']) {
        $s = $db->prepare("SELECT nom FROM points_vente WHERE id=?");
        $s->execute([$c['point_vente_id']]);
        return $s->fetchColumn();
    }
    return null;
}

if ($action === 'cmd_list') {
    apiRequireRole(['admin','labo','production']);
    $sql = "SELECT cmd.*,
                cl.nom as client_nom, pv.nom as point_vente_nom,
                (SELECT COALESCE(SUM(quantite*prix_unitaire),0) FROM lignes_commande WHERE commande_id=cmd.id) as montant_total
            FROM commandes cmd
            LEFT JOIN clients cl ON cl.id = cmd.client_id
            LEFT JOIN points_vente pv ON pv.id = cmd.point_vente_id
            ORDER BY cmd.date_commande DESC, cmd.id DESC";
    respond($db->query($sql)->fetchAll());
}
if ($action === 'cmd_get') {
    apiRequireRole(['admin','labo','production']);
    $stmt = $db->prepare("SELECT * FROM commandes WHERE id=?");
    $stmt->execute([$body['id']]);
    $cmd = $stmt->fetch();
    if (!$cmd) error400('Commande introuvable');
    $stmt2 = $db->prepare("SELECT lc.*, p.nom as produit_nom, p.unite FROM lignes_commande lc JOIN produits p ON p.id=lc.produit_id WHERE lc.commande_id=?");
    $stmt2->execute([$body['id']]);
    $cmd['lignes'] = $stmt2->fetchAll();
    $cmd['destination'] = commandeDestinationLabel($db, $cmd);
    respond($cmd);
}
if ($action === 'cmd_save') {
    apiRequireRole(['admin','labo']);
    $d = $body;
    $lignes = $d['lignes'] ?? [];
    if (empty($d['canal'])) error400('Canal requis');
    if (in_array($d['canal'], ['terme','franchise'], true) && empty($d['client_id'])) error400('Client requis pour ce canal');
    if ($d['canal'] === 'point_vente' && empty($d['point_vente_id'])) error400('Point de vente requis');
    if (empty($lignes)) error400('Au moins une ligne de produit est requise');

    if (!empty($d['id'])) {
        $stmt = $db->prepare("UPDATE commandes SET canal=?,type=?,client_id=?,point_vente_id=?,evenement_id=?,acompte=?,date_commande=?,date_livraison_prevue=?,notes=? WHERE id=?");
        $stmt->execute([$d['canal'],$d['type']??'ponctuelle',$d['client_id']?:null,$d['point_vente_id']?:null,$d['evenement_id']?:null,$d['acompte']?:null,$d['date_commande'],$d['date_livraison_prevue']?:null,$d['notes'],$d['id']]);
        $cmdId = $d['id'];
        $db->prepare("DELETE FROM lignes_commande WHERE commande_id=?")->execute([$cmdId]);
    } else {
        $stmt = $db->prepare("INSERT INTO commandes (canal,type,client_id,point_vente_id,evenement_id,acompte,statut,date_commande,date_livraison_prevue,notes,created_by) VALUES (?,?,?,?,?,?,'brouillon',?,?,?,?)");
        $stmt->execute([$d['canal'],$d['type']??'ponctuelle',$d['client_id']?:null,$d['point_vente_id']?:null,$d['evenement_id']?:null,$d['acompte']?:null,$d['date_commande'],$d['date_livraison_prevue']?:null,$d['notes'],$user['id']]);
        $cmdId = $db->lastInsertId();
    }
    $stmtL = $db->prepare("INSERT INTO lignes_commande (commande_id,produit_id,quantite,prix_unitaire) VALUES (?,?,?,?)");
    foreach ($lignes as $l) {
        $stmtL->execute([$cmdId, $l['produit_id'], $l['quantite'], $l['prix_unitaire']]);
    }
    respond(['ok' => true, 'id' => $cmdId]);
}
if ($action === 'cmd_delete') {
    apiRequireRole(['admin','labo']);
    $stmt = $db->prepare("SELECT statut FROM commandes WHERE id=?");
    $stmt->execute([$body['id']]);
    $statut = $stmt->fetchColumn();
    if ($statut && !in_array($statut, ['brouillon','annulee'], true)) {
        error400('Seules les commandes en brouillon ou annulées peuvent être supprimées');
    }
    $db->prepare("DELETE FROM commandes WHERE id=?")->execute([$body['id']]);
    respond(['ok' => true]);
}
if ($action === 'cmd_set_statut') {
    apiRequireRole(['admin','labo']);
    $allowed = ['brouillon','confirmee','annulee'];
    if (!in_array($body['statut'], $allowed, true)) {
        error400('Statut non modifiable manuellement (le pipeline production/livraison/facturation gère la suite automatiquement)');
    }
    $db->prepare("UPDATE commandes SET statut=? WHERE id=?")->execute([$body['statut'], $body['id']]);
    respond(['ok' => true]);
}

// ──────────────────────────────────────────
// PRODUCTION — ordres de fabrication
// ──────────────────────────────────────────
if ($action === 'of_list') {
    apiRequireRole(['admin','labo','production']);
    respond($db->query("SELECT * FROM ordres_fabrication ORDER BY id DESC")->fetchAll());
}
if ($action === 'of_get') {
    apiRequireRole(['admin','labo','production']);
    $stmt = $db->prepare("SELECT * FROM ordres_fabrication WHERE id=?");
    $stmt->execute([$body['id']]);
    $ordre = $stmt->fetch();
    if (!$ordre) error400('Ordre introuvable');
    $l = $db->prepare("SELECT lof.*, p.nom as produit_nom, p.unite FROM lignes_ordre_fabrication lof JOIN produits p ON p.id=lof.produit_id WHERE lof.ordre_id=?");
    $l->execute([$body['id']]);
    $ordre['lignes'] = $l->fetchAll();
    $c = $db->prepare("SELECT cmd.id, cmd.canal, cmd.statut FROM ordre_fabrication_commandes ofc JOIN commandes cmd ON cmd.id=ofc.commande_id WHERE ofc.ordre_id=?");
    $c->execute([$body['id']]);
    $ordre['commandes'] = $c->fetchAll();
    $lots = $db->prepare("SELECT * FROM lots WHERE ordre_id=?");
    $lots->execute([$body['id']]);
    $ordre['lots'] = $lots->fetchAll();
    respond($ordre);
}
if ($action === 'of_generate') {
    apiRequireRole(['admin','labo']);
    $cmds = $db->query("SELECT id FROM commandes WHERE statut='confirmee'")->fetchAll();
    if (!$cmds) error400('Aucune commande confirmée à produire');
    $cmdIds = array_column($cmds, 'id');
    $in = implode(',', array_fill(0, count($cmdIds), '?'));

    $agg = $db->prepare("SELECT produit_id, SUM(quantite) as total FROM lignes_commande WHERE commande_id IN ($in) GROUP BY produit_id");
    $agg->execute($cmdIds);
    $lignes = $agg->fetchAll();

    $db->beginTransaction();
    try {
        $db->prepare("INSERT INTO ordres_fabrication (date_ordre, statut) VALUES (CURDATE(), 'planifie')")->execute();
        $ordreId = $db->lastInsertId();

        $stmtL = $db->prepare("INSERT INTO lignes_ordre_fabrication (ordre_id, produit_id, quantite_totale) VALUES (?,?,?)");
        foreach ($lignes as $l) { $stmtL->execute([$ordreId, $l['produit_id'], $l['total']]); }

        $stmtC = $db->prepare("INSERT INTO ordre_fabrication_commandes (ordre_id, commande_id) VALUES (?,?)");
        foreach ($cmdIds as $cid) { $stmtC->execute([$ordreId, $cid]); }

        $upd = $db->prepare("UPDATE commandes SET statut='en_production' WHERE id IN ($in)");
        $upd->execute($cmdIds);

        $db->commit();
        respond(['ok' => true, 'id' => $ordreId]);
    } catch (Exception $e) {
        $db->rollBack();
        error400('Erreur lors de la génération: ' . $e->getMessage());
    }
}
if ($action === 'of_marquer_en_cours') {
    apiRequireRole(['admin','labo','production']);
    $db->prepare("UPDATE ordres_fabrication SET statut='en_cours' WHERE id=? AND statut='planifie'")->execute([$body['id']]);
    respond(['ok' => true]);
}
if ($action === 'of_terminer') {
    apiRequireRole(['admin','labo','production']);
    $stmt = $db->prepare("SELECT * FROM ordres_fabrication WHERE id=?");
    $stmt->execute([$body['id']]);
    $ordre = $stmt->fetch();
    if (!$ordre) error400('Ordre introuvable');
    if ($ordre['statut'] === 'termine') error400('Cet ordre est déjà terminé');

    $l = $db->prepare("SELECT * FROM lignes_ordre_fabrication WHERE ordre_id=?");
    $l->execute([$body['id']]);
    $lignes = $l->fetchAll();

    $dlcJours = (int)($body['dlc_jours'] ?? 3);

    $db->beginTransaction();
    try {
        $insLot = $db->prepare("INSERT INTO lots (produit_id, ordre_id, numero_lot, quantite_produite, date_fabrication, date_peremption) VALUES (?,?,?,?,CURDATE(),DATE_ADD(CURDATE(), INTERVAL ? DAY))");
        $insMvtProduit = $db->prepare("INSERT INTO mouvements_stock_produits (produit_id, type_mouvement, quantite, origine, reference_id, notes) VALUES (?,'entree',?,'production',?,?)");
        $recettes = $db->prepare("SELECT * FROM recettes WHERE produit_id=?");
        $getMp = $db->prepare("SELECT stock_actuel FROM matieres_premieres WHERE id=? FOR UPDATE");
        $updMp = $db->prepare("UPDATE matieres_premieres SET stock_actuel = stock_actuel - ? WHERE id=?");
        $insMvtMp = $db->prepare("INSERT INTO mouvements_stock_matieres (matiere_id, type_mouvement, quantite, origine, reference_id, notes) VALUES (?,'sortie',?,'production',?,?)");

        foreach ($lignes as $ligne) {
            $numeroLot = 'LOT-' . $ordre['id'] . '-' . $ligne['produit_id'] . '-' . date('Ymd');
            $insLot->execute([$ligne['produit_id'], $ordre['id'], $numeroLot, $ligne['quantite_totale'], $dlcJours]);
            $insMvtProduit->execute([$ligne['produit_id'], $ligne['quantite_totale'], $ordre['id'], 'Ordre #' . $ordre['id'] . ' — lot ' . $numeroLot]);

            $recettes->execute([$ligne['produit_id']]);
            foreach ($recettes->fetchAll() as $r) {
                $conso = $r['quantite_necessaire'] * $ligne['quantite_totale'];
                $updMp->execute([$conso, $r['matiere_id']]);
                $insMvtMp->execute([$r['matiere_id'], $conso, $ordre['id'], 'Consommé pour ordre #' . $ordre['id']]);
            }
        }
        $db->prepare("UPDATE ordres_fabrication SET statut='termine' WHERE id=?")->execute([$ordre['id']]);
        $db->commit();
        respond(['ok' => true]);
    } catch (Exception $e) {
        $db->rollBack();
        error400('Erreur lors de la clôture: ' . $e->getMessage());
    }
}

// ──────────────────────────────────────────
// STOCK — matières premières
// ──────────────────────────────────────────
if ($action === 'mp_mouvements_list') {
    apiRequireRole(['admin','labo']);
    $stmt = $db->prepare("SELECT m.*, mp.nom as matiere_nom, mp.unite FROM mouvements_stock_matieres m JOIN matieres_premieres mp ON mp.id=m.matiere_id WHERE (? IS NULL OR m.matiere_id=?) ORDER BY m.date_mouvement DESC LIMIT 100");
    $mid = $body['matiere_id'] ?? null;
    $stmt->execute([$mid, $mid]);
    respond($stmt->fetchAll());
}
if ($action === 'mp_ajuster') {
    apiRequireRole(['admin','labo']);
    $d = $body;
    if (empty($d['matiere_id']) || !isset($d['nouveau_stock'])) error400('Matière et nouveau stock requis');
    $stmt = $db->prepare("SELECT stock_actuel FROM matieres_premieres WHERE id=?");
    $stmt->execute([$d['matiere_id']]);
    $ancien = $stmt->fetchColumn();
    if ($ancien === false) error400('Matière introuvable');
    $delta = $d['nouveau_stock'] - $ancien;
    $db->prepare("UPDATE matieres_premieres SET stock_actuel=? WHERE id=?")->execute([$d['nouveau_stock'], $d['matiere_id']]);
    $db->prepare("INSERT INTO mouvements_stock_matieres (matiere_id,type_mouvement,quantite,origine,notes) VALUES (?,'ajustement',?,'correction',?)")
        ->execute([$d['matiere_id'], $delta, $d['notes'] ?? ('Correction manuelle: ' . $ancien . ' → ' . $d['nouveau_stock'])]);
    respond(['ok' => true]);
}

// ──────────────────────────────────────────
// STOCK — produits finis
// ──────────────────────────────────────────
if ($action === 'produit_stock_list') {
    apiRequireRole(['admin','labo']);
    respond($db->query("SELECT * FROM v_stock_produits ORDER BY nom")->fetchAll());
}
if ($action === 'produit_mouvements_list') {
    apiRequireRole(['admin','labo']);
    $stmt = $db->prepare("SELECT m.*, p.nom as produit_nom, p.unite FROM mouvements_stock_produits m JOIN produits p ON p.id=m.produit_id WHERE (? IS NULL OR m.produit_id=?) ORDER BY m.date_mouvement DESC LIMIT 100");
    $pid = $body['produit_id'] ?? null;
    $stmt->execute([$pid, $pid]);
    respond($stmt->fetchAll());
}

// ──────────────────────────────────────────
// PERTES / INVENDUS
// ──────────────────────────────────────────
if ($action === 'perte_list') {
    apiRequireRole(['admin','labo','point_vente']);
    $rows = $db->query("SELECT pe.*, p.nom as produit_nom, pv.nom as point_vente_nom FROM pertes pe
        JOIN produits p ON p.id=pe.produit_id LEFT JOIN points_vente pv ON pv.id=pe.point_vente_id
        ORDER BY pe.date_perte DESC LIMIT 100")->fetchAll();
    respond($rows);
}
if ($action === 'perte_save') {
    apiRequireRole(['admin','labo','point_vente']);
    $d = $body;
    if (empty($d['produit_id']) || empty($d['quantite'])) error400('Produit et quantité requis');
    $db->beginTransaction();
    try {
        $stmt = $db->prepare("INSERT INTO pertes (source,point_vente_id,produit_id,quantite,motif,date_perte) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$d['source'] ?? 'production', $d['point_vente_id'] ?: null, $d['produit_id'], $d['quantite'], $d['motif'], $d['date_perte'] ?? date('Y-m-d')]);
        $perteId = $db->lastInsertId();
        $db->prepare("INSERT INTO mouvements_stock_produits (produit_id,type_mouvement,quantite,origine,reference_id,notes) VALUES (?,'sortie',?,'perte',?,?)")
            ->execute([$d['produit_id'], $d['quantite'], $perteId, $d['motif'] ?? 'Perte/invendu']);
        if (!empty($d['point_vente_id'])) {
            $db->prepare("INSERT INTO stocks_points_vente (point_vente_id,produit_id,quantite) VALUES (?,?,0)
                ON DUPLICATE KEY UPDATE quantite = GREATEST(0, quantite - ?)")
                ->execute([$d['point_vente_id'], $d['produit_id'], $d['quantite']]);
        }
        $db->commit();
        respond(['ok' => true]);
    } catch (Exception $e) {
        $db->rollBack();
        error400('Erreur: ' . $e->getMessage());
    }
}

// ──────────────────────────────────────────
// STOCK PAR POINT DE VENTE
// ──────────────────────────────────────────
if ($action === 'spv_list') {
    apiRequireRole(['admin','labo','point_vente']);
    $rows = $db->query("SELECT spv.*, p.nom as produit_nom, p.unite, pv.nom as point_vente_nom
        FROM stocks_points_vente spv
        JOIN produits p ON p.id=spv.produit_id
        JOIN points_vente pv ON pv.id=spv.point_vente_id
        ORDER BY pv.nom, p.nom")->fetchAll();
    respond($rows);
}
if ($action === 'vente_passager_save') {
    apiRequireRole(['admin','labo','point_vente']);
    $d = $body;
    if (empty($d['point_vente_id']) || empty($d['produit_id']) || empty($d['quantite'])) error400('Point de vente, produit et quantité requis');
    $stmtP = $db->prepare("SELECT prix_vente FROM produits WHERE id=?");
    $stmtP->execute([$d['produit_id']]);
    $prix = $d['prix_unitaire'] ?? $stmtP->fetchColumn();
    $montant = $prix * $d['quantite'];
    $tva = 19;
    $ttc = round($montant * (1 + $tva/100), 3);

    $db->beginTransaction();
    try {
        $db->prepare("INSERT INTO stocks_points_vente (point_vente_id,produit_id,quantite) VALUES (?,?,0)
            ON DUPLICATE KEY UPDATE quantite = quantite")->execute([$d['point_vente_id'], $d['produit_id']]);
        $db->prepare("UPDATE stocks_points_vente SET quantite = quantite - ? WHERE point_vente_id=? AND produit_id=?")
            ->execute([$d['quantite'], $d['point_vente_id'], $d['produit_id']]);
        $db->prepare("INSERT INTO mouvements_stock_produits (produit_id,type_mouvement,quantite,origine,reference_id,notes) VALUES (?,'sortie',?,'vente',?,?)")
            ->execute([$d['produit_id'], $d['quantite'], $d['point_vente_id'], 'Vente passager']);

        $numero = nextDocNumero($db, 'factures', 'FAC');
        $db->prepare("INSERT INTO factures (numero,point_vente_id,montant_ht,taux_tva,montant_ttc,mode_paiement,statut,date_emission) VALUES (?,?,?,?,?,'comptant','payee',CURDATE())")
            ->execute([$numero, $d['point_vente_id'], $montant, $tva, $ttc]);
        $factureId = $db->lastInsertId();
        $db->prepare("INSERT INTO paiements (facture_id,montant,date_paiement,mode) VALUES (?,?,CURDATE(),'especes')")->execute([$factureId, $ttc]);

        $db->commit();
        respond(['ok' => true, 'facture_id' => $factureId, 'numero' => $numero, 'montant_ttc' => $ttc]);
    } catch (Exception $e) {
        $db->rollBack();
        error400('Erreur: ' . $e->getMessage());
    }
}

// ──────────────────────────────────────────
// LIVRAISONS / DISPATCH
// ──────────────────────────────────────────
if ($action === 'liv_candidats') {
    apiRequireRole(['admin','labo']);
    $sql = "SELECT cmd.*, cl.nom as client_nom, pv.nom as point_vente_nom,
                (SELECT COALESCE(SUM(quantite*prix_unitaire),0) FROM lignes_commande WHERE commande_id=cmd.id) as montant_total
            FROM commandes cmd
            LEFT JOIN clients cl ON cl.id = cmd.client_id
            LEFT JOIN points_vente pv ON pv.id = cmd.point_vente_id
            JOIN ordre_fabrication_commandes ofc ON ofc.commande_id = cmd.id
            JOIN ordres_fabrication of2 ON of2.id = ofc.ordre_id
            WHERE cmd.statut = 'en_production' AND of2.statut = 'termine'
            ORDER BY cmd.date_commande";
    respond($db->query($sql)->fetchAll());
}
if ($action === 'liv_list') {
    apiRequireRole(['admin','labo']);
    $sql = "SELECT l.*, cl.nom as client_nom, pv.nom as point_vente_nom
            FROM livraisons l
            LEFT JOIN clients cl ON cl.id = l.destination_client_id
            LEFT JOIN points_vente pv ON pv.id = l.destination_point_vente_id
            ORDER BY l.id DESC";
    respond($db->query($sql)->fetchAll());
}
if ($action === 'liv_get') {
    apiRequireRole(['admin','labo']);
    $stmt = $db->prepare("SELECT l.*, cl.nom as client_nom, pv.nom as point_vente_nom FROM livraisons l
        LEFT JOIN clients cl ON cl.id = l.destination_client_id
        LEFT JOIN points_vente pv ON pv.id = l.destination_point_vente_id WHERE l.id=?");
    $stmt->execute([$body['id']]);
    $liv = $stmt->fetch();
    if (!$liv) error400('Livraison introuvable');
    $lg = $db->prepare("SELECT ll.*, p.nom as produit_nom, lo.numero_lot FROM lignes_livraison ll JOIN produits p ON p.id=ll.produit_id LEFT JOIN lots lo ON lo.id=ll.lot_id WHERE ll.livraison_id=?");
    $lg->execute([$body['id']]);
    $liv['lignes'] = $lg->fetchAll();
    respond($liv);
}
if ($action === 'liv_creer') {
    apiRequireRole(['admin','labo']);
    $stmt = $db->prepare("SELECT * FROM commandes WHERE id=?");
    $stmt->execute([$body['commande_id']]);
    $cmd = $stmt->fetch();
    if (!$cmd) error400('Commande introuvable');
    if ($cmd['statut'] !== 'en_production') error400('Cette commande n\'est pas prête à être livrée (doit être en production et son ordre de fabrication terminé)');

    $of = $db->prepare("SELECT of2.id, of2.statut FROM ordre_fabrication_commandes ofc JOIN ordres_fabrication of2 ON of2.id=ofc.ordre_id WHERE ofc.commande_id=? LIMIT 1");
    $of->execute([$cmd['id']]);
    $ordre = $of->fetch();
    if (!$ordre || $ordre['statut'] !== 'termine') error400('L\'ordre de fabrication lié n\'est pas encore terminé');

    $lignes = $db->prepare("SELECT * FROM lignes_commande WHERE commande_id=?");
    $lignes->execute([$cmd['id']]);
    $lignesCmd = $lignes->fetchAll();

    $db->beginTransaction();
    try {
        $stmtLiv = $db->prepare("INSERT INTO livraisons (ordre_id,canal,destination_client_id,destination_point_vente_id,date_livraison,statut) VALUES (?,?,?,?,CURDATE(),'preparee')");
        $stmtLiv->execute([$ordre['id'], $cmd['canal'], $cmd['client_id'], $cmd['point_vente_id']]);
        $livId = $db->lastInsertId();

        $getLot = $db->prepare("SELECT id FROM lots WHERE produit_id=? AND ordre_id=? LIMIT 1");
        $insLigne = $db->prepare("INSERT INTO lignes_livraison (livraison_id,commande_id,produit_id,quantite,lot_id) VALUES (?,?,?,?,?)");
        $insMvt = $db->prepare("INSERT INTO mouvements_stock_produits (produit_id,type_mouvement,quantite,origine,reference_id,notes) VALUES (?,'sortie',?,'dispatch',?,?)");

        foreach ($lignesCmd as $l) {
            $getLot->execute([$l['produit_id'], $ordre['id']]);
            $lotId = $getLot->fetchColumn() ?: null;
            $insLigne->execute([$livId, $cmd['id'], $l['produit_id'], $l['quantite'], $lotId]);
            $insMvt->execute([$l['produit_id'], $l['quantite'], $livId, 'Dispatch commande #' . $cmd['id']]);
            if ($cmd['canal'] === 'point_vente') {
                $db->prepare("INSERT INTO stocks_points_vente (point_vente_id,produit_id,quantite) VALUES (?,?,0) ON DUPLICATE KEY UPDATE quantite = quantite")
                    ->execute([$cmd['point_vente_id'], $l['produit_id']]);
                $db->prepare("UPDATE stocks_points_vente SET quantite = quantite + ? WHERE point_vente_id=? AND produit_id=?")
                    ->execute([$l['quantite'], $cmd['point_vente_id'], $l['produit_id']]);
            }
        }
        $db->prepare("UPDATE commandes SET statut='livree' WHERE id=?")->execute([$cmd['id']]);
        $db->commit();
        respond(['ok' => true, 'id' => $livId]);
    } catch (Exception $e) {
        $db->rollBack();
        error400('Erreur: ' . $e->getMessage());
    }
}
if ($action === 'liv_set_statut') {
    apiRequireRole(['admin','labo']);
    if (!in_array($body['statut'], ['preparee','en_route','livree'], true)) error400('Statut invalide');
    $db->prepare("UPDATE livraisons SET statut=? WHERE id=?")->execute([$body['statut'], $body['id']]);
    respond(['ok' => true]);
}

// ──────────────────────────────────────────
// FACTURATION
// ──────────────────────────────────────────
if ($action === 'fact_candidats') {
    apiRequireRole(['admin','labo']);
    $sql = "SELECT cmd.*, cl.nom as client_nom, pv.nom as point_vente_nom,
                (SELECT COALESCE(SUM(quantite*prix_unitaire),0) FROM lignes_commande WHERE commande_id=cmd.id) as montant_total
            FROM commandes cmd
            LEFT JOIN clients cl ON cl.id = cmd.client_id
            LEFT JOIN points_vente pv ON pv.id = cmd.point_vente_id
            WHERE cmd.statut = 'livree'
            ORDER BY cmd.date_commande";
    respond($db->query($sql)->fetchAll());
}
if ($action === 'fact_creer') {
    apiRequireRole(['admin','labo']);
    $stmt = $db->prepare("SELECT * FROM commandes WHERE id=?");
    $stmt->execute([$body['commande_id']]);
    $cmd = $stmt->fetch();
    if (!$cmd) error400('Commande introuvable');
    if ($cmd['statut'] !== 'livree') error400('La commande doit être livrée avant facturation');

    $lignes = $db->prepare("SELECT COALESCE(SUM(quantite*prix_unitaire),0) as total FROM lignes_commande WHERE commande_id=?");
    $lignes->execute([$cmd['id']]);
    $montantHt = $lignes->fetchColumn();

    $tvaStmt = $db->prepare("SELECT valeur FROM parametres WHERE cle='tva_defaut'");
    $tvaStmt->execute();
    $tva = floatval($tvaStmt->fetchColumn() ?: 19);
    $montantTtc = round($montantHt * (1 + $tva/100), 3);

    $modePaiement = $body['mode_paiement'] ?? null;
    if (!$modePaiement) {
        if ($cmd['canal'] === 'terme') $modePaiement = 'terme';
        elseif ($cmd['canal'] === 'point_vente') $modePaiement = 'comptant';
        else {
            $fr = $db->prepare("SELECT mode_paiement FROM franchises WHERE client_id=?");
            $fr->execute([$cmd['client_id']]);
            $fm = $fr->fetchColumn();
            $modePaiement = ($fm === 'terme') ? 'terme' : 'comptant';
        }
    }
    $dateEcheance = $modePaiement === 'terme' ? date('Y-m-d', strtotime('+30 days')) : null;

    $db->beginTransaction();
    try {
        $numero = nextDocNumero($db, 'factures', 'FAC');
        $stmt = $db->prepare("INSERT INTO factures (numero,client_id,point_vente_id,commande_id,montant_ht,taux_tva,montant_ttc,mode_paiement,statut,date_emission,date_echeance) VALUES (?,?,?,?,?,?,?,?,'emise',CURDATE(),?)");
        $stmt->execute([$numero, $cmd['client_id'], $cmd['point_vente_id'], $cmd['id'], $montantHt, $tva, $montantTtc, $modePaiement, $dateEcheance]);
        $factureId = $db->lastInsertId();
        $db->prepare("UPDATE commandes SET statut='facturee' WHERE id=?")->execute([$cmd['id']]);
        $db->commit();
        respond(['ok' => true, 'id' => $factureId, 'numero' => $numero]);
    } catch (Exception $e) {
        $db->rollBack();
        error400('Erreur: ' . $e->getMessage());
    }
}
if ($action === 'fact_list') {
    apiRequireRole(['admin','labo']);
    $sql = "SELECT f.*, cl.nom as client_nom, pv.nom as point_vente_nom,
                COALESCE((SELECT SUM(montant) FROM paiements WHERE facture_id=f.id),0) as montant_paye
            FROM factures f
            LEFT JOIN clients cl ON cl.id = f.client_id
            LEFT JOIN points_vente pv ON pv.id = f.point_vente_id
            ORDER BY f.id DESC";
    respond($db->query($sql)->fetchAll());
}
if ($action === 'fact_get') {
    apiRequireRole(['admin','labo']);
    $stmt = $db->prepare("SELECT f.*, cl.nom as client_nom, pv.nom as point_vente_nom FROM factures f
        LEFT JOIN clients cl ON cl.id=f.client_id LEFT JOIN points_vente pv ON pv.id=f.point_vente_id WHERE f.id=?");
    $stmt->execute([$body['id']]);
    $fact = $stmt->fetch();
    if (!$fact) error400('Facture introuvable');
    $p = $db->prepare("SELECT * FROM paiements WHERE facture_id=? ORDER BY date_paiement");
    $p->execute([$body['id']]);
    $fact['paiements'] = $p->fetchAll();
    if ($fact['commande_id']) {
        $l = $db->prepare("SELECT lc.*, pr.nom as produit_nom FROM lignes_commande lc JOIN produits pr ON pr.id=lc.produit_id WHERE lc.commande_id=?");
        $l->execute([$fact['commande_id']]);
        $fact['lignes'] = $l->fetchAll();
    } else {
        $fact['lignes'] = [];
    }
    respond($fact);
}
if ($action === 'paiement_save') {
    apiRequireRole(['admin','labo']);
    $d = $body;
    if (empty($d['facture_id']) || empty($d['montant'])) error400('Facture et montant requis');
    $db->prepare("INSERT INTO paiements (facture_id,montant,date_paiement,mode,reference,notes) VALUES (?,?,?,?,?,?)")
        ->execute([$d['facture_id'], $d['montant'], $d['date_paiement'] ?? date('Y-m-d'), $d['mode'] ?? 'especes', $d['reference'], $d['notes']]);

    $ttcStmt = $db->prepare("SELECT montant_ttc FROM factures WHERE id=?");
    $ttcStmt->execute([$d['facture_id']]);
    $ttc = floatval($ttcStmt->fetchColumn());
    $paidStmt = $db->prepare("SELECT COALESCE(SUM(montant),0) FROM paiements WHERE facture_id=?");
    $paidStmt->execute([$d['facture_id']]);
    $paid = floatval($paidStmt->fetchColumn());
    $statut = $paid >= $ttc - 0.001 ? 'payee' : ($paid > 0 ? 'partiellement_payee' : 'emise');
    $db->prepare("UPDATE factures SET statut=? WHERE id=?")->execute([$statut, $d['facture_id']]);
    respond(['ok' => true]);
}
if ($action === 'paiement_delete') {
    apiRequireRole(['admin','labo']);
    $stmt = $db->prepare("SELECT facture_id FROM paiements WHERE id=?");
    $stmt->execute([$body['id']]);
    $factureId = $stmt->fetchColumn();
    $db->prepare("DELETE FROM paiements WHERE id=?")->execute([$body['id']]);
    if ($factureId) {
        $ttcStmt = $db->prepare("SELECT montant_ttc FROM factures WHERE id=?");
        $ttcStmt->execute([$factureId]);
        $ttc = floatval($ttcStmt->fetchColumn());
        $paidStmt = $db->prepare("SELECT COALESCE(SUM(montant),0) FROM paiements WHERE facture_id=?");
        $paidStmt->execute([$factureId]);
        $paid = floatval($paidStmt->fetchColumn());
        $statut = $paid >= $ttc - 0.001 ? 'payee' : ($paid > 0 ? 'partiellement_payee' : 'emise');
        $db->prepare("UPDATE factures SET statut=? WHERE id=?")->execute([$statut, $factureId]);
    }
    respond(['ok' => true]);
}

// ──────────────────────────────────────────
// STATISTIQUES
// ──────────────────────────────────────────
if ($action === 'stats_marge') {
    apiRequireRole(['admin']);
    respond($db->query("SELECT * FROM v_marge_produits ORDER BY marge_estimee DESC")->fetchAll());
}
if ($action === 'stats_stock_bas') {
    apiRequireRole(['admin']);
    respond($db->query("SELECT * FROM v_stock_bas")->fetchAll());
}
if ($action === 'stats_encours') {
    apiRequireRole(['admin']);
    respond($db->query("SELECT * FROM v_encours_clients WHERE encours > 0 ORDER BY encours DESC")->fetchAll());
}
if ($action === 'stats_consommation') {
    apiRequireRole(['admin']);
    $sql = "SELECT mp.nom, mp.unite, COALESCE(SUM(m.quantite),0) as consomme
            FROM matieres_premieres mp
            LEFT JOIN mouvements_stock_matieres m ON m.matiere_id = mp.id AND m.type_mouvement='sortie' AND m.origine='production'
            GROUP BY mp.id, mp.nom, mp.unite
            ORDER BY consomme DESC";
    respond($db->query($sql)->fetchAll());
}
if ($action === 'stats_ventes_canal') {
    apiRequireRole(['admin']);
    $sql = "SELECT cmd.canal,
                COUNT(DISTINCT cmd.id) as nb_commandes,
                COALESCE(SUM(lc.quantite * lc.prix_unitaire),0) as montant_total
            FROM commandes cmd
            LEFT JOIN lignes_commande lc ON lc.commande_id = cmd.id
            WHERE cmd.statut NOT IN ('brouillon','annulee')
            GROUP BY cmd.canal";
    respond($db->query($sql)->fetchAll());
}
if ($action === 'stats_produits_vendus') {
    apiRequireRole(['admin']);
    $sql = "SELECT p.nom, COALESCE(SUM(lc.quantite),0) as quantite_totale, COALESCE(SUM(lc.quantite*lc.prix_unitaire),0) as montant_total
            FROM produits p
            LEFT JOIN (
                SELECT lc.* FROM lignes_commande lc
                JOIN commandes cmd ON cmd.id = lc.commande_id AND cmd.statut NOT IN ('brouillon','annulee')
            ) lc ON lc.produit_id = p.id
            GROUP BY p.id, p.nom
            ORDER BY quantite_totale DESC";
    respond($db->query($sql)->fetchAll());
}

// ──────────────────────────────────────────
// UTILISATEURS (admin uniquement)
// ──────────────────────────────────────────
if ($action === 'user_list') {
    apiRequireRole(['admin']);
    $rows = $db->query("SELECT u.id,u.nom,u.email,u.role,u.avatar,u.actif,u.client_id,u.point_vente_id,u.created_at,
                cl.nom as client_nom, pv.nom as point_vente_nom
            FROM users u
            LEFT JOIN clients cl ON cl.id = u.client_id
            LEFT JOIN points_vente pv ON pv.id = u.point_vente_id
            ORDER BY u.id")->fetchAll();
    respond($rows);
}
if ($action === 'user_create') {
    apiRequireRole(['admin']);
    $d = $body;
    if (empty($d['nom']) || empty($d['email']) || empty($d['password'])) error400('Nom, email et mot de passe requis');
    $exists = $db->prepare("SELECT id FROM users WHERE email=?");
    $exists->execute([$d['email']]);
    if ($exists->fetch()) error400('Cet email existe déjà');
    $hash = password_hash($d['password'], PASSWORD_BCRYPT, ['cost' => 12]);
    $avatar = $d['avatar'] ?: strtoupper(substr($d['nom'],0,2));
    $stmt = $db->prepare("INSERT INTO users (nom,email,password_hash,role,avatar,actif,client_id,point_vente_id) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->execute([$d['nom'],$d['email'],$hash,$d['role'] ?? 'client_terme',$avatar,$d['actif'] ?? 1,$d['client_id'] ?: null,$d['point_vente_id'] ?: null]);
    respond(['ok' => true, 'id' => $db->lastInsertId()]);
}
if ($action === 'user_update') {
    apiRequireRole(['admin']);
    $d = $body;
    if (empty($d['id']) || empty($d['nom']) || empty($d['email'])) error400('Champs requis manquants');
    $exists = $db->prepare("SELECT id FROM users WHERE email=? AND id!=?");
    $exists->execute([$d['email'], $d['id']]);
    if ($exists->fetch()) error400('Cet email est déjà utilisé par un autre compte');
    if (!empty($d['password'])) {
        $hash = password_hash($d['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $db->prepare("UPDATE users SET nom=?,email=?,role=?,avatar=?,actif=?,client_id=?,point_vente_id=?,password_hash=? WHERE id=?");
        $stmt->execute([$d['nom'],$d['email'],$d['role'],$d['avatar'],$d['actif'] ?? 1,$d['client_id'] ?: null,$d['point_vente_id'] ?: null,$hash,$d['id']]);
    } else {
        $stmt = $db->prepare("UPDATE users SET nom=?,email=?,role=?,avatar=?,actif=?,client_id=?,point_vente_id=? WHERE id=?");
        $stmt->execute([$d['nom'],$d['email'],$d['role'],$d['avatar'],$d['actif'] ?? 1,$d['client_id'] ?: null,$d['point_vente_id'] ?: null,$d['id']]);
    }
    if ($d['id'] == $_SESSION['user_id']) {
        $_SESSION['user']['nom'] = $d['nom'];
        $_SESSION['user']['email'] = $d['email'];
        $_SESSION['user']['role'] = $d['role'];
        $_SESSION['user']['avatar'] = $d['avatar'];
    }
    respond(['ok' => true]);
}
if ($action === 'user_delete') {
    apiRequireRole(['admin']);
    if ($body['id'] == $_SESSION['user_id']) error400('Vous ne pouvez pas supprimer votre propre compte');
    $countAdmins = $db->query("SELECT COUNT(*) FROM users WHERE role='admin' AND actif=1")->fetchColumn();
    $target = $db->prepare("SELECT role FROM users WHERE id=?");
    $target->execute([$body['id']]);
    $targetRole = $target->fetchColumn();
    if ($targetRole === 'admin' && $countAdmins <= 1) error400('Impossible de supprimer le dernier administrateur');
    $db->prepare("DELETE FROM users WHERE id=?")->execute([$body['id']]);
    respond(['ok' => true]);
}
if ($action === 'user_toggle_actif') {
    apiRequireRole(['admin']);
    if ($body['id'] == $_SESSION['user_id']) error400('Vous ne pouvez pas désactiver votre propre compte');
    $db->prepare("UPDATE users SET actif = NOT actif WHERE id=?")->execute([$body['id']]);
    respond(['ok' => true]);
}
if ($action === 'me_change_password') {
    $d = $body;
    if (empty($d['current_password']) || empty($d['new_password'])) error400('Mot de passe actuel et nouveau requis');
    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id=?");
    $stmt->execute([$user['id']]);
    $hash = $stmt->fetchColumn();
    if (!$hash || !password_verify($d['current_password'], $hash)) error400('Mot de passe actuel incorrect');
    $newHash = password_hash($d['new_password'], PASSWORD_BCRYPT, ['cost' => 12]);
    $db->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$newHash, $user['id']]);
    respond(['ok' => true]);
}

// ──────────────────────────────────────────
// PARAMÈTRES
// ──────────────────────────────────────────
if ($action === 'param_list') {
    apiRequireRole(['admin']);
    respond($db->query("SELECT * FROM parametres ORDER BY cle")->fetchAll());
}
if ($action === 'param_save') {
    apiRequireRole(['admin']);
    $d = $body;
    if (empty($d['cle'])) error400('Clé requise');
    $db->prepare("INSERT INTO parametres (cle,valeur) VALUES (?,?) ON DUPLICATE KEY UPDATE valeur=VALUES(valeur)")
        ->execute([$d['cle'], $d['valeur']]);
    respond(['ok' => true]);
}

// ──────────────────────────────────────────
// PORTAILS EXTERNES — commandes en libre-service
// La portée (quelle entité) vient TOUJOURS de la session, jamais du body envoyé
// par le client, pour empêcher qu'une entité accède aux données d'une autre.
// ──────────────────────────────────────────
function monScope(array $user): array {
    if ($user['role'] === 'franchise') {
        if (empty($user['client_id'])) error400('Ce compte franchise n\'est rattaché à aucune franchise — contactez l\'administrateur');
        return ['canal' => 'franchise', 'col' => 'client_id', 'val' => $user['client_id']];
    }
    if ($user['role'] === 'client_terme') {
        if (empty($user['client_id'])) error400('Ce compte n\'est rattaché à aucun client — contactez l\'administrateur');
        return ['canal' => 'terme', 'col' => 'client_id', 'val' => $user['client_id']];
    }
    if ($user['role'] === 'point_vente') {
        if (empty($user['point_vente_id'])) error400('Ce compte n\'est rattaché à aucun point de vente — contactez l\'administrateur');
        return ['canal' => 'point_vente', 'col' => 'point_vente_id', 'val' => $user['point_vente_id']];
    }
    http_response_code(403);
    respond(['error' => 'Rôle non autorisé pour cette action']);
}

if ($action === 'mes_cmd_list') {
    apiRequireRole(['franchise','client_terme','point_vente']);
    $s = monScope($user);
    $stmt = $db->prepare("SELECT cmd.*,
            (SELECT COALESCE(SUM(quantite*prix_unitaire),0) FROM lignes_commande WHERE commande_id=cmd.id) as montant_total
        FROM commandes cmd WHERE cmd.canal = ? AND cmd.{$s['col']} = ? ORDER BY cmd.date_commande DESC, cmd.id DESC");
    $stmt->execute([$s['canal'], $s['val']]);
    respond($stmt->fetchAll());
}
if ($action === 'mes_cmd_get') {
    apiRequireRole(['franchise','client_terme','point_vente']);
    $s = monScope($user);
    $stmt = $db->prepare("SELECT * FROM commandes WHERE id=? AND canal=? AND {$s['col']} = ?");
    $stmt->execute([$body['id'], $s['canal'], $s['val']]);
    $cmd = $stmt->fetch();
    if (!$cmd) error400('Commande introuvable');
    $l = $db->prepare("SELECT lc.*, p.nom as produit_nom, p.unite FROM lignes_commande lc JOIN produits p ON p.id=lc.produit_id WHERE lc.commande_id=?");
    $l->execute([$cmd['id']]);
    $cmd['lignes'] = $l->fetchAll();
    respond($cmd);
}
if ($action === 'mes_cmd_save') {
    apiRequireRole(['franchise','client_terme','point_vente']);
    $s = monScope($user);
    $d = $body;
    $lignes = $d['lignes'] ?? [];
    if (empty($lignes)) error400('Au moins une ligne de produit est requise');

    if (!empty($d['id'])) {
        // Vérifie que la commande à modifier appartient bien à cette entité et est encore en brouillon
        $chk = $db->prepare("SELECT statut FROM commandes WHERE id=? AND canal=? AND {$s['col']} = ?");
        $chk->execute([$d['id'], $s['canal'], $s['val']]);
        $statutActuel = $chk->fetchColumn();
        if ($statutActuel === false) error400('Commande introuvable');
        if ($statutActuel !== 'brouillon') error400('Seule une commande en brouillon peut être modifiée');
        $stmt = $db->prepare("UPDATE commandes SET type=?,date_commande=?,date_livraison_prevue=?,notes=? WHERE id=?");
        $stmt->execute([$d['type'] ?? 'ponctuelle', $d['date_commande'], $d['date_livraison_prevue'] ?: null, $d['notes'], $d['id']]);
        $cmdId = $d['id'];
        $db->prepare("DELETE FROM lignes_commande WHERE commande_id=?")->execute([$cmdId]);
    } else {
        $clientId = $s['col'] === 'client_id' ? $s['val'] : null;
        $pvId = $s['col'] === 'point_vente_id' ? $s['val'] : null;
        $stmt = $db->prepare("INSERT INTO commandes (canal,type,client_id,point_vente_id,statut,date_commande,date_livraison_prevue,notes,created_by) VALUES (?,?,?,?,'brouillon',?,?,?,?)");
        $stmt->execute([$s['canal'], $d['type'] ?? 'ponctuelle', $clientId, $pvId, $d['date_commande'], $d['date_livraison_prevue'] ?: null, $d['notes'], $user['id']]);
        $cmdId = $db->lastInsertId();
    }
    $stmtL = $db->prepare("INSERT INTO lignes_commande (commande_id,produit_id,quantite,prix_unitaire) VALUES (?,?,?,?)");
    foreach ($lignes as $l) {
        $stmtL->execute([$cmdId, $l['produit_id'], $l['quantite'], $l['prix_unitaire']]);
    }
    respond(['ok' => true, 'id' => $cmdId]);
}
if ($action === 'mes_cmd_set_statut') {
    apiRequireRole(['franchise','client_terme','point_vente']);
    $s = monScope($user);
    if (!in_array($body['statut'], ['confirmee','annulee'], true)) error400('Statut non autorisé');
    $chk = $db->prepare("SELECT statut FROM commandes WHERE id=? AND canal=? AND {$s['col']} = ?");
    $chk->execute([$body['id'], $s['canal'], $s['val']]);
    $statutActuel = $chk->fetchColumn();
    if ($statutActuel === false) error400('Commande introuvable');
    if ($statutActuel !== 'brouillon') error400('Cette commande ne peut plus être modifiée');
    $db->prepare("UPDATE commandes SET statut=? WHERE id=?")->execute([$body['statut'], $body['id']]);
    respond(['ok' => true]);
}

// ──────────────────────────────────────────
// PORTAILS EXTERNES — factures & déclarations de paiement
// ──────────────────────────────────────────
if ($action === 'mes_fact_list') {
    apiRequireRole(['franchise','client_terme','point_vente']);
    $s = monScope($user);
    $stmt = $db->prepare("SELECT f.*, COALESCE((SELECT SUM(montant) FROM paiements WHERE facture_id=f.id),0) as montant_paye
        FROM factures f WHERE f.{$s['col']} = ? ORDER BY f.id DESC");
    $stmt->execute([$s['val']]);
    respond($stmt->fetchAll());
}
if ($action === 'mes_fact_get') {
    apiRequireRole(['franchise','client_terme','point_vente']);
    $s = monScope($user);
    $stmt = $db->prepare("SELECT * FROM factures WHERE id=? AND {$s['col']} = ?");
    $stmt->execute([$body['id'], $s['val']]);
    $fact = $stmt->fetch();
    if (!$fact) error400('Facture introuvable');
    $p = $db->prepare("SELECT * FROM paiements WHERE facture_id=? ORDER BY date_paiement");
    $p->execute([$fact['id']]);
    $fact['paiements'] = $p->fetchAll();
    $decl = $db->prepare("SELECT * FROM declarations_paiement WHERE facture_id=? ORDER BY created_at DESC");
    $decl->execute([$fact['id']]);
    $fact['declarations'] = $decl->fetchAll();
    if ($fact['commande_id']) {
        $l = $db->prepare("SELECT lc.*, pr.nom as produit_nom FROM lignes_commande lc JOIN produits pr ON pr.id=lc.produit_id WHERE lc.commande_id=?");
        $l->execute([$fact['commande_id']]);
        $fact['lignes'] = $l->fetchAll();
    } else {
        $fact['lignes'] = [];
    }
    respond($fact);
}
if ($action === 'paiement_declarer') {
    apiRequireRole(['franchise','client_terme']);
    $s = monScope($user);
    $d = $body;
    if (empty($d['facture_id']) || empty($d['montant'])) error400('Montant requis');
    $chk = $db->prepare("SELECT id FROM factures WHERE id=? AND {$s['col']} = ?");
    $chk->execute([$d['facture_id'], $s['val']]);
    if (!$chk->fetch()) error400('Facture introuvable');
    $stmt = $db->prepare("INSERT INTO declarations_paiement (facture_id,montant,date_declaration,mode,reference,notes,statut,declare_par) VALUES (?,?,?,?,?,?,'en_attente',?)");
    $stmt->execute([$d['facture_id'], $d['montant'], $d['date_declaration'] ?? date('Y-m-d'), $d['mode'] ?? 'virement', $d['reference'], $d['notes'], $user['id']]);
    respond(['ok' => true]);
}

// ──────────────────────────────────────────
// ADMIN — validation des déclarations de paiement
// ──────────────────────────────────────────
if ($action === 'declarations_list') {
    apiRequireRole(['admin','labo']);
    $sql = "SELECT dp.*, f.numero as facture_numero, f.montant_ttc, cl.nom as client_nom, pv.nom as point_vente_nom
            FROM declarations_paiement dp
            JOIN factures f ON f.id = dp.facture_id
            LEFT JOIN clients cl ON cl.id = f.client_id
            LEFT JOIN points_vente pv ON pv.id = f.point_vente_id
            ORDER BY FIELD(dp.statut,'en_attente','validee','rejetee'), dp.created_at DESC";
    respond($db->query($sql)->fetchAll());
}
if ($action === 'declaration_valider') {
    apiRequireRole(['admin','labo']);
    $stmt = $db->prepare("SELECT * FROM declarations_paiement WHERE id=?");
    $stmt->execute([$body['id']]);
    $decl = $stmt->fetch();
    if (!$decl) error400('Déclaration introuvable');
    if ($decl['statut'] !== 'en_attente') error400('Cette déclaration a déjà été traitée');

    $db->beginTransaction();
    try {
        $db->prepare("INSERT INTO paiements (facture_id,montant,date_paiement,mode,reference,notes) VALUES (?,?,?,?,?,?)")
            ->execute([$decl['facture_id'], $decl['montant'], $decl['date_declaration'], $decl['mode'], $decl['reference'], 'Validé depuis déclaration #' . $decl['id']]);
        $paiementId = $db->lastInsertId();

        $ttcStmt = $db->prepare("SELECT montant_ttc FROM factures WHERE id=?");
        $ttcStmt->execute([$decl['facture_id']]);
        $ttc = floatval($ttcStmt->fetchColumn());
        $paidStmt = $db->prepare("SELECT COALESCE(SUM(montant),0) FROM paiements WHERE facture_id=?");
        $paidStmt->execute([$decl['facture_id']]);
        $paid = floatval($paidStmt->fetchColumn());
        $statutFacture = $paid >= $ttc - 0.001 ? 'payee' : ($paid > 0 ? 'partiellement_payee' : 'emise');
        $db->prepare("UPDATE factures SET statut=? WHERE id=?")->execute([$statutFacture, $decl['facture_id']]);

        $db->prepare("UPDATE declarations_paiement SET statut='validee', valide_par=?, valide_le=NOW(), paiement_id=? WHERE id=?")
            ->execute([$user['id'], $paiementId, $decl['id']]);
        $db->commit();
        respond(['ok' => true]);
    } catch (Exception $e) {
        $db->rollBack();
        error400('Erreur: ' . $e->getMessage());
    }
}
if ($action === 'declaration_rejeter') {
    apiRequireRole(['admin','labo']);
    $stmt = $db->prepare("SELECT statut FROM declarations_paiement WHERE id=?");
    $stmt->execute([$body['id']]);
    $statutActuel = $stmt->fetchColumn();
    if ($statutActuel === false) error400('Déclaration introuvable');
    if ($statutActuel !== 'en_attente') error400('Cette déclaration a déjà été traitée');
    $db->prepare("UPDATE declarations_paiement SET statut='rejetee', valide_par=?, valide_le=NOW(), notes=CONCAT(COALESCE(notes,''), ?) WHERE id=?")
        ->execute([$user['id'], $body['motif'] ? (' — Rejet: ' . $body['motif']) : '', $body['id']]);
    respond(['ok' => true]);
}

// ──────────────────────────────────────────
// PORTAIL POINT DE VENTE — caisse & stock vitrine
// ──────────────────────────────────────────
if ($action === 'mon_stock_pv_list') {
    apiRequireRole(['point_vente']);
    if (empty($user['point_vente_id'])) error400('Ce compte n\'est rattaché à aucun point de vente — contactez l\'administrateur');
    $stmt = $db->prepare("SELECT spv.*, p.nom as produit_nom, p.unite FROM stocks_points_vente spv
        JOIN produits p ON p.id = spv.produit_id WHERE spv.point_vente_id = ? ORDER BY p.nom");
    $stmt->execute([$user['point_vente_id']]);
    respond($stmt->fetchAll());
}
if ($action === 'caisse_vente_save') {
    apiRequireRole(['point_vente']);
    if (empty($user['point_vente_id'])) error400('Ce compte n\'est rattaché à aucun point de vente — contactez l\'administrateur');
    $d = $body;
    if (empty($d['produit_id']) || empty($d['quantite'])) error400('Produit et quantité requis');
    $pvId = $user['point_vente_id'];

    $stmtP = $db->prepare("SELECT prix_vente FROM produits WHERE id=?");
    $stmtP->execute([$d['produit_id']]);
    $prix = $stmtP->fetchColumn();
    if ($prix === false) error400('Produit introuvable');
    $montant = $prix * $d['quantite'];
    $tvaStmt = $db->prepare("SELECT valeur FROM parametres WHERE cle='tva_defaut'");
    $tvaStmt->execute();
    $tva = floatval($tvaStmt->fetchColumn() ?: 19);
    $ttc = round($montant * (1 + $tva/100), 3);

    $db->beginTransaction();
    try {
        $db->prepare("INSERT INTO stocks_points_vente (point_vente_id,produit_id,quantite) VALUES (?,?,0)
            ON DUPLICATE KEY UPDATE quantite = quantite")->execute([$pvId, $d['produit_id']]);
        $db->prepare("UPDATE stocks_points_vente SET quantite = quantite - ? WHERE point_vente_id=? AND produit_id=?")
            ->execute([$d['quantite'], $pvId, $d['produit_id']]);
        $db->prepare("INSERT INTO mouvements_stock_produits (produit_id,type_mouvement,quantite,origine,reference_id,notes) VALUES (?,'sortie',?,'vente',?,?)")
            ->execute([$d['produit_id'], $d['quantite'], $pvId, 'Vente passager (caisse)']);

        $numero = nextDocNumero($db, 'factures', 'FAC');
        $db->prepare("INSERT INTO factures (numero,point_vente_id,montant_ht,taux_tva,montant_ttc,mode_paiement,statut,date_emission) VALUES (?,?,?,?,?,'comptant','payee',CURDATE())")
            ->execute([$numero, $pvId, $montant, $tva, $ttc]);
        $factureId = $db->lastInsertId();
        $db->prepare("INSERT INTO paiements (facture_id,montant,date_paiement,mode) VALUES (?,?,CURDATE(),'especes')")->execute([$factureId, $ttc]);

        $db->commit();
        respond(['ok' => true, 'numero' => $numero, 'montant_ttc' => $ttc]);
    } catch (Exception $e) {
        $db->rollBack();
        error400('Erreur: ' . $e->getMessage());
    }
}
if ($action === 'mes_ventes_list') {
    apiRequireRole(['point_vente']);
    if (empty($user['point_vente_id'])) error400('Ce compte n\'est rattaché à aucun point de vente — contactez l\'administrateur');
    $stmt = $db->prepare("SELECT f.*, COALESCE((SELECT SUM(montant) FROM paiements WHERE facture_id=f.id),0) as montant_paye
        FROM factures f WHERE f.point_vente_id = ? ORDER BY f.id DESC");
    $stmt->execute([$user['point_vente_id']]);
    respond($stmt->fetchAll());
}

// ──────────────────────────────────────────
// PORTAILS EXTERNES — tableau de bord
// ──────────────────────────────────────────
if ($action === 'mes_dashboard_stats') {
    apiRequireRole(['franchise','client_terme','point_vente']);
    $s = monScope($user);
    $stats = [];
    if ($user['role'] === 'point_vente') {
        $stmt = $db->prepare("SELECT COUNT(*), COALESCE(SUM(montant_ttc),0) FROM factures WHERE point_vente_id=? AND date_emission=CURDATE()");
        $stmt->execute([$s['val']]);
        [$stats['ventes_jour'], $stats['montant_jour']] = $stmt->fetch(PDO::FETCH_NUM);
        $stmt2 = $db->prepare("SELECT COUNT(*) FROM stocks_points_vente WHERE point_vente_id=? AND quantite <= 0");
        $stmt2->execute([$s['val']]);
        $stats['produits_epuises'] = $stmt2->fetchColumn();
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) FROM commandes WHERE {$s['col']}=? AND canal=? AND statut NOT IN ('facturee','annulee')");
        $stmt->execute([$s['val'], $s['canal']]);
        $stats['commandes_en_cours'] = $stmt->fetchColumn();
        $stmt2 = $db->prepare("SELECT encours FROM v_encours_clients WHERE client_id=?");
        $stmt2->execute([$s['val']]);
        $stats['encours'] = $stmt2->fetchColumn() ?: 0;
        $stmt3 = $db->prepare("SELECT COUNT(*) FROM declarations_paiement dp JOIN factures f ON f.id=dp.facture_id WHERE f.{$s['col']}=? AND dp.statut='en_attente'");
        $stmt3->execute([$s['val']]);
        $stats['declarations_en_attente'] = $stmt3->fetchColumn();
    }
    respond($stats);
}

error400('Action inconnue: ' . $action);
