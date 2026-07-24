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

// Portée du portail client : toujours dérivée de la session, jamais du body.
function monClientId() {
    $u = currentUser();
    if (empty($u['client_id'])) { http_response_code(403); respond(['error' => 'Compte non rattaché à un client']); }
    return (int)$u['client_id'];
}

// Numéro séquentiel type PREFIX-ANNEE-000
function nextDocNumero($db, string $table, string $prefix): string {
    $year = date('Y');
    $stmt = $db->prepare("SELECT numero FROM $table WHERE numero LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute(["$prefix-$year-%"]);
    $last = $stmt->fetchColumn();
    $next = 1;
    if ($last) { $parts = explode('-', $last); $next = intval(end($parts)) + 1; }
    return $prefix . '-' . $year . '-' . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
}

// Mouvement de stock tracé : met à jour pieces.stock et journalise dans mouvements_stock.
function moveStock($db, int $pieceId, int $delta, string $type, ?string $motif, ?string $refType, ?int $refId, ?int $userId) {
    $db->prepare("UPDATE pieces SET stock = stock + ? WHERE id = ?")->execute([$delta, $pieceId]);
    $stockApres = (int)$db->query("SELECT stock FROM pieces WHERE id = " . (int)$pieceId)->fetchColumn();
    $st = $db->prepare("INSERT INTO mouvements_stock (piece_id,type,quantite,stock_apres,motif,ref_type,ref_id,user_id)
                        VALUES (?,?,?,?,?,?,?,?)");
    $st->execute([$pieceId, $type, $delta, $stockApres, $motif, $refType, $refId, $userId]);
}

// ──────────────────────────────────────────
// DASHBOARD (interne)
// ──────────────────────────────────────────
if ($action === 'dashboard_stats') {
    apiRequireRole(['admin','technicien','magasinier']);
    $s = [];
    $s['machines_en_service'] = (int)$db->query("SELECT COUNT(*) FROM machines WHERE statut='en_service'")->fetchColumn();
    $s['machines_en_panne']   = (int)$db->query("SELECT COUNT(*) FROM machines WHERE statut='en_panne'")->fetchColumn();
    $s['interventions_ouvertes'] = (int)$db->query("SELECT COUNT(*) FROM interventions WHERE statut NOT IN ('cloturee','annulee')")->fetchColumn();
    $s['maintenances_dues']   = (int)$db->query("SELECT COUNT(*) FROM v_maintenances_planifiees WHERE jours_restants <= 30")->fetchColumn();
    $s['pieces_stock_bas']    = (int)$db->query("SELECT COUNT(*) FROM v_pieces_stock_bas")->fetchColumn();
    $s['commandes_en_cours']  = (int)$db->query("SELECT COUNT(*) FROM commandes_pieces WHERE statut IN ('commandee','partielle')")->fetchColumn();
    respond($s);
}
if ($action === 'dashboard_interventions') {
    apiRequireRole(['admin','technicien','magasinier']);
    $rows = $db->query("SELECT id, numero, priorite, statut, modele, n_serie, raison_sociale
        FROM v_interventions_ouvertes
        ORDER BY FIELD(priorite,'urgente','haute','normale','basse'), created_at ASC LIMIT 8")->fetchAll();
    respond($rows);
}
if ($action === 'dashboard_maintenance') {
    apiRequireRole(['admin','technicien','magasinier']);
    // Calendrier réel : prochaines visites préventives planifiées (contrats Kodak).
    $rows = $db->query("SELECT mp.*, mp.date_prevue AS prochaine_maintenance
        FROM v_maintenances_planifiees mp
        WHERE mp.jours_restants <= 45 ORDER BY mp.jours_restants ASC LIMIT 8")->fetchAll();
    respond($rows);
}
if ($action === 'mes_dashboard_stats') {
    apiRequireRole(['client']);
    $cid = monClientId();
    $s = [];
    $st = $db->prepare("SELECT COUNT(*) FROM machines WHERE client_id=?"); $st->execute([$cid]);
    $s['mes_machines'] = (int)$st->fetchColumn();
    $st = $db->prepare("SELECT COUNT(*) FROM machines WHERE client_id=? AND statut='en_panne'"); $st->execute([$cid]);
    $s['mes_machines_en_panne'] = (int)$st->fetchColumn();
    $st = $db->prepare("SELECT COUNT(*) FROM interventions WHERE client_id=? AND statut NOT IN ('cloturee','annulee')"); $st->execute([$cid]);
    $s['mes_interventions_ouvertes'] = (int)$st->fetchColumn();
    respond($s);
}

// ──────────────────────────────────────────
// CLIENTS
// ──────────────────────────────────────────
if ($action === 'cli_list') {
    apiRequireRole(['admin','technicien']);
    $rows = $db->query("SELECT c.*,
            (SELECT COUNT(*) FROM machines m WHERE m.client_id=c.id) AS nb_machines,
            (SELECT COUNT(*) FROM interventions i WHERE i.client_id=c.id AND i.statut NOT IN ('cloturee','annulee')) AS nb_interventions
        FROM clients c ORDER BY c.raison_sociale")->fetchAll();
    respond($rows);
}
if ($action === 'cli_get') {
    apiRequireRole(['admin','technicien']);
    $st = $db->prepare("SELECT * FROM clients WHERE id=?"); $st->execute([$body['id'] ?? 0]);
    $c = $st->fetch(); if (!$c) error400('Client introuvable');
    respond($c);
}
if ($action === 'cli_save') {
    apiRequireRole(['admin','technicien']);
    $d = $body;
    if (empty(trim($d['raison_sociale'] ?? ''))) error400('Raison sociale requise');
    $code = trim((string)($d['code_client'] ?? '')) ?: null;
    if ($code) {
        $chk = $db->prepare("SELECT id FROM clients WHERE code_client=? AND id<>?");
        $chk->execute([$code, $d['id'] ?? 0]);
        if ($chk->fetch()) error400('Ce code client est déjà utilisé');
    }
    $cols = "code_client=?,raison_sociale=?,contact_nom=?,telephone=?,email=?,adresse=?,ville=?,secteur=?,actif=?,notes=?";
    $vals = [$code, $d['raison_sociale'], $d['contact_nom']??null, $d['telephone']??null, $d['email']??null,
             $d['adresse']??null, $d['ville']??null, $d['secteur']??null, $d['actif']??1, $d['notes']??null];
    if (!empty($d['id'])) {
        $db->prepare("UPDATE clients SET $cols WHERE id=?")->execute(array_merge($vals, [$d['id']]));
        respond(['ok'=>true, 'id'=>$d['id']]);
    } else {
        $db->prepare("INSERT INTO clients SET $cols")->execute($vals);
        respond(['ok'=>true, 'id'=>$db->lastInsertId()]);
    }
}
if ($action === 'cli_toggle_actif') {
    apiRequireRole(['admin','technicien']);
    $db->prepare("UPDATE clients SET actif = NOT actif WHERE id=?")->execute([$body['id']]);
    respond(['ok'=>true]);
}
if ($action === 'cli_delete') {
    apiRequireRole(['admin']);
    try {
        $db->prepare("DELETE FROM clients WHERE id=?")->execute([$body['id']]);
        respond(['ok'=>true]);
    } catch (Exception $e) {
        error400('Impossible de supprimer : ce client a des machines ou interventions. Désactivez-le plutôt.');
    }
}

// ──────────────────────────────────────────
// MACHINES (parc CTP)
// ──────────────────────────────────────────
if ($action === 'mac_list') {
    apiRequireRole(['admin','technicien','magasinier']);
    $rows = $db->query("SELECT m.*, c.raison_sociale,
            (SELECT COUNT(*) FROM interventions i WHERE i.machine_id=m.id AND i.statut NOT IN ('cloturee','annulee')) AS nb_interventions
        FROM machines m JOIN clients c ON c.id=m.client_id ORDER BY c.raison_sociale, m.modele")->fetchAll();
    respond($rows);
}
if ($action === 'mac_get') {
    apiRequireRole(['admin','technicien','magasinier']);
    $st = $db->prepare("SELECT m.*, c.raison_sociale FROM machines m JOIN clients c ON c.id=m.client_id WHERE m.id=?");
    $st->execute([$body['id'] ?? 0]);
    $m = $st->fetch(); if (!$m) error400('Machine introuvable');
    // historique des interventions
    $h = $db->prepare("SELECT id,numero,type,statut,date_debut,date_fin,description FROM interventions WHERE machine_id=? ORDER BY created_at DESC LIMIT 20");
    $h->execute([$body['id']]);
    $m['historique'] = $h->fetchAll();
    respond($m);
}
if ($action === 'mac_save') {
    apiRequireRole(['admin','technicien']);
    $d = $body;
    if (empty($d['client_id'])) error400('Client requis');
    if (empty(trim($d['modele'] ?? ''))) error400('Modèle requis');
    if (empty(trim($d['n_serie'] ?? ''))) error400('Numéro de série requis');
    $chk = $db->prepare("SELECT id FROM machines WHERE n_serie=? AND id<>?");
    $chk->execute([$d['n_serie'], $d['id'] ?? 0]);
    if ($chk->fetch()) error400('Ce numéro de série existe déjà');
    $tech = in_array($d['technologie'] ?? '', ['thermique','violet','uv','flexo','autre'], true) ? $d['technologie'] : 'thermique';
    $stat = in_array($d['statut'] ?? '', ['en_service','maintenance','en_panne','hors_service','retire'], true) ? $d['statut'] : 'en_service';
    $cols = "client_id=?,modele=?,gamme=?,n_serie=?,technologie=?,format=?,date_installation=?,date_fin_garantie=?,compteur_plaques=?,localisation=?,statut=?,notes=?";
    $vals = [$d['client_id'], $d['modele'], $d['gamme']??null, $d['n_serie'], $tech, $d['format']??null,
             $d['date_installation']?:null, $d['date_fin_garantie']?:null, (int)($d['compteur_plaques']??0),
             $d['localisation']??null, $stat, $d['notes']??null];
    if (!empty($d['id'])) {
        $db->prepare("UPDATE machines SET $cols WHERE id=?")->execute(array_merge($vals, [$d['id']]));
        respond(['ok'=>true, 'id'=>$d['id']]);
    } else {
        $db->prepare("INSERT INTO machines SET $cols")->execute($vals);
        respond(['ok'=>true, 'id'=>$db->lastInsertId()]);
    }
}
if ($action === 'mac_delete') {
    apiRequireRole(['admin']);
    try {
        $db->prepare("DELETE FROM machines WHERE id=?")->execute([$body['id']]);
        respond(['ok'=>true]);
    } catch (Exception $e) { error400('Impossible : cette machine a des interventions. Passez-la en "retiré".'); }
}
if ($action === 'mac_options') {
    apiRequireRole(['admin','technicien','magasinier']);
    respond([
        'clients' => $db->query("SELECT id, raison_sociale FROM clients WHERE actif=1 ORDER BY raison_sociale")->fetchAll(),
    ]);
}

// ──────────────────────────────────────────
// CONTRATS
// ──────────────────────────────────────────
if ($action === 'ctr_list') {
    apiRequireRole(['admin','technicien']);
    $rows = $db->query("SELECT c.*, cl.raison_sociale, m.modele, m.n_serie,
            DATEDIFF(c.prochaine_maintenance, CURDATE()) AS jours_restants
        FROM contrats c JOIN clients cl ON cl.id=c.client_id
        LEFT JOIN machines m ON m.id=c.machine_id
        ORDER BY c.statut='actif' DESC, c.prochaine_maintenance IS NULL, c.prochaine_maintenance")->fetchAll();
    respond($rows);
}
if ($action === 'ctr_get') {
    apiRequireRole(['admin','technicien']);
    $st = $db->prepare("SELECT * FROM contrats WHERE id=?"); $st->execute([$body['id'] ?? 0]);
    $c = $st->fetch(); if (!$c) error400('Contrat introuvable');
    respond($c);
}
if ($action === 'ctr_save') {
    apiRequireRole(['admin','technicien']);
    $d = $body;
    if (empty($d['client_id'])) error400('Client requis');
    if (empty($d['date_debut'])) error400('Date de début requise');
    $type = in_array($d['type'] ?? '', ['preventif','full_service','garantie','a_la_demande'], true) ? $d['type'] : 'preventif';
    $stat = in_array($d['statut'] ?? '', ['actif','suspendu','expire'], true) ? $d['statut'] : 'actif';
    $freq = ($d['frequence_jours'] ?? '') !== '' ? (int)$d['frequence_jours'] : null;
    $proch = $d['prochaine_maintenance'] ?: null;
    // Si fréquence définie sans prochaine échéance, on l'initialise depuis la date de début.
    if ($freq && !$proch && !empty($d['date_debut'])) {
        $proch = date('Y-m-d', strtotime($d['date_debut'] . ' +' . $freq . ' days'));
    }
    $cols = "client_id=?,machine_id=?,type=?,date_debut=?,date_fin=?,frequence_jours=?,prochaine_maintenance=?,montant_annuel=?,sla_heures=?,statut=?,notes=?";
    $vals = [$d['client_id'], $d['machine_id']?:null, $type, $d['date_debut'], $d['date_fin']?:null, $freq, $proch,
             $d['montant_annuel']?:0, ($d['sla_heures']??'')!==''?(int)$d['sla_heures']:null, $stat, $d['notes']??null];
    if (!empty($d['id'])) {
        $db->prepare("UPDATE contrats SET $cols WHERE id=?")->execute(array_merge($vals, [$d['id']]));
        respond(['ok'=>true, 'id'=>$d['id']]);
    } else {
        $num = nextDocNumero($db, 'contrats', 'CTR');
        $db->prepare("INSERT INTO contrats SET numero=?, $cols")->execute(array_merge([$num], $vals));
        respond(['ok'=>true, 'id'=>$db->lastInsertId(), 'numero'=>$num]);
    }
}
if ($action === 'ctr_delete') {
    apiRequireRole(['admin']);
    $db->prepare("DELETE FROM contrats WHERE id=?")->execute([$body['id']]);
    respond(['ok'=>true]);
}

// ──────────────────────────────────────────
// MAINTENANCE PRÉVENTIVE (calendrier)
// ──────────────────────────────────────────
if ($action === 'maint_list') {
    apiRequireRole(['admin','technicien']);
    $rows = $db->query("SELECT d.*, m.modele, m.n_serie, c.frequence_jours
        FROM v_maintenance_due d
        JOIN contrats c ON c.id=d.contrat_id
        LEFT JOIN machines m ON m.id=d.machine_id
        ORDER BY d.jours_restants ASC")->fetchAll();
    respond($rows);
}
// Planifie une intervention préventive à partir d'un contrat + fait avancer l'échéance.
if ($action === 'maint_planifier') {
    apiRequireRole(['admin','technicien']);
    $ctrId = (int)($body['contrat_id'] ?? 0);
    $st = $db->prepare("SELECT * FROM contrats WHERE id=?"); $st->execute([$ctrId]);
    $ctr = $st->fetch(); if (!$ctr) error400('Contrat introuvable');
    // machine cible : celle du contrat, sinon la 1ère machine du client
    $machineId = $ctr['machine_id'];
    if (!$machineId) {
        $mst = $db->prepare("SELECT id FROM machines WHERE client_id=? ORDER BY id LIMIT 1");
        $mst->execute([$ctr['client_id']]);
        $machineId = $mst->fetchColumn();
        if (!$machineId) error400('Aucune machine rattachée à ce client');
    }
    $datePlan = $body['date_planifiee'] ?: ($ctr['prochaine_maintenance'] ? $ctr['prochaine_maintenance'].' 09:00:00' : date('Y-m-d 09:00:00'));
    $tech = ($body['technicien_id'] ?? '') !== '' ? (int)$body['technicien_id'] : null;
    $db->beginTransaction();
    try {
        $num = nextDocNumero($db, 'interventions', 'INT');
        $ins = $db->prepare("INSERT INTO interventions (numero,machine_id,client_id,contrat_id,type,origine,priorite,statut,technicien_id,date_planifiee,description)
            VALUES (?,?,?,?,'preventive','preventif','normale','planifiee',?,?,?)");
        $ins->execute([$num, $machineId, $ctr['client_id'], $ctrId, $tech, $datePlan,
            'Maintenance préventive planifiée (contrat '.$ctr['numero'].')']);
        // avance la prochaine échéance
        if (!empty($ctr['frequence_jours'])) {
            $base = $ctr['prochaine_maintenance'] ?: date('Y-m-d');
            $next = date('Y-m-d', strtotime($base.' +'.(int)$ctr['frequence_jours'].' days'));
            $db->prepare("UPDATE contrats SET prochaine_maintenance=? WHERE id=?")->execute([$next, $ctrId]);
        }
        $db->commit();
        respond(['ok'=>true, 'numero'=>$num]);
    } catch (Exception $e) { $db->rollBack(); error400('Échec de la planification : '.$e->getMessage()); }
}

// ──────────────────────────────────────────
// MAINTENANCES PRÉVENTIVES PLANIFIÉES (calendrier contractuel Kodak)
// ──────────────────────────────────────────
if ($action === 'mp_list') {
    apiRequireRole(['admin','technicien']);
    // Visites planifiées à venir (+ celles en retard). Option : réalisées récentes.
    $rows = $db->query("SELECT * FROM v_maintenances_planifiees ORDER BY date_prevue ASC")->fetchAll();
    respond($rows);
}
if ($action === 'mp_historique') {
    apiRequireRole(['admin','technicien']);
    $rows = $db->query("SELECT mp.*, m.modele, m.n_serie, cl.raison_sociale
        FROM maintenances_planifiees mp
        JOIN machines m ON m.id=mp.machine_id JOIN clients cl ON cl.id=mp.client_id
        WHERE mp.statut='realisee' ORDER BY mp.date_realisee DESC LIMIT 100")->fetchAll();
    respond($rows);
}
if ($action === 'mp_options') {
    apiRequireRole(['admin','technicien']);
    respond([
        // machines rattachées à un contrat (visites préventives)
        'machines' => $db->query("SELECT m.id, m.modele, m.n_serie, c.raison_sociale,
                (SELECT ct.id FROM contrats ct WHERE ct.machine_id=m.id OR (ct.machine_id IS NULL AND ct.client_id=m.client_id) ORDER BY ct.machine_id IS NULL LIMIT 1) AS contrat_id
            FROM machines m JOIN clients c ON c.id=m.client_id
            WHERE m.statut<>'retire' ORDER BY c.raison_sociale, m.modele")->fetchAll(),
        'techniciens' => $db->query("SELECT id, nom FROM users WHERE role IN ('technicien','admin') AND actif=1 ORDER BY nom")->fetchAll(),
    ]);
}
if ($action === 'mp_add') {
    apiRequireRole(['admin','technicien']);
    $machineId = (int)($body['machine_id'] ?? 0);
    $date = $body['date_prevue'] ?? '';
    if (!$machineId || !$date) error400('Machine et date requises');
    $mst = $db->prepare("SELECT client_id FROM machines WHERE id=?"); $mst->execute([$machineId]);
    $clientId = $mst->fetchColumn(); if (!$clientId) error400('Machine introuvable');
    $type = in_array($body['type'] ?? '', ['preventive','previsionnelle'], true) ? $body['type'] : 'preventive';
    // contrat rattaché (machine directe, sinon contrat "tout le parc" du client)
    $cst = $db->prepare("SELECT id FROM contrats WHERE (machine_id=? OR (machine_id IS NULL AND client_id=?)) ORDER BY machine_id IS NULL LIMIT 1");
    $cst->execute([$machineId, $clientId]);
    $contratId = $cst->fetchColumn() ?: null;
    try {
        $db->prepare("INSERT INTO maintenances_planifiees (contrat_id,machine_id,client_id,type,rang,date_prevue,statut,notes)
                      VALUES (?,?,?,?,?,?,'planifiee',?)")
           ->execute([$contratId, $machineId, $clientId, $type, ($body['rang']??null)?:null, $date, $body['notes']??null]);
        respond(['ok'=>true]);
    } catch (Exception $e) { error400('Une visite existe déjà pour cette machine à cette date et ce type'); }
}
if ($action === 'mp_reporter') {
    apiRequireRole(['admin','technicien']);
    $id = (int)($body['id'] ?? 0);
    $date = $body['date_prevue'] ?? '';
    if (!$date) error400('Nouvelle date requise');
    $db->prepare("UPDATE maintenances_planifiees SET date_prevue=?, statut='planifiee' WHERE id=?")->execute([$date, $id]);
    respond(['ok'=>true]);
}
if ($action === 'mp_annuler') {
    apiRequireRole(['admin','technicien']);
    $db->prepare("UPDATE maintenances_planifiees SET statut='annulee' WHERE id=?")->execute([(int)($body['id'] ?? 0)]);
    respond(['ok'=>true]);
}
if ($action === 'mp_delete') {
    apiRequireRole(['admin']);
    $db->prepare("DELETE FROM maintenances_planifiees WHERE id=?")->execute([(int)($body['id'] ?? 0)]);
    respond(['ok'=>true]);
}
// Marque une visite comme réalisée → crée une intervention préventive clôturée et la rattache.
if ($action === 'mp_marquer_realisee') {
    apiRequireRole(['admin','technicien']);
    $id = (int)($body['id'] ?? 0);
    $st = $db->prepare("SELECT * FROM maintenances_planifiees WHERE id=?"); $st->execute([$id]);
    $mp = $st->fetch(); if (!$mp) error400('Visite introuvable');
    if ($mp['statut'] === 'realisee') error400('Visite déjà marquée réalisée');
    $dateReal = $body['date_realisee'] ?: date('Y-m-d');
    $tech = ($body['technicien_id'] ?? '') !== '' ? (int)$body['technicien_id'] : null;
    $db->beginTransaction();
    try {
        $num = nextDocNumero($db, 'interventions', 'INT');
        $typeLabel = $mp['type'] === 'preventive' ? 'Maintenance préventive' : 'Visite prévisionnelle';
        $ins = $db->prepare("INSERT INTO interventions
            (numero,machine_id,client_id,contrat_id,type,origine,priorite,statut,technicien_id,date_debut,date_fin,description,resolution,temps_passe_h)
            VALUES (?,?,?,?,'preventive','preventif','normale','cloturee',?,?,?,?,?,?)");
        $desc = $typeLabel . ' planifiée (visite #' . ($mp['rang'] ?: '-') . ')';
        $ins->execute([$num, $mp['machine_id'], $mp['client_id'], $mp['contrat_id'], $tech, $dateReal, $dateReal,
            $desc, $body['resolution'] ?? $typeLabel . ' effectuée', $body['temps_passe_h'] ?? 0]);
        $intId = (int)$db->lastInsertId();
        $db->prepare("UPDATE maintenances_planifiees SET statut='realisee', date_realisee=?, intervention_id=? WHERE id=?")
           ->execute([$dateReal, $intId, $id]);
        $db->commit();
        respond(['ok'=>true, 'numero'=>$num]);
    } catch (Exception $e) { $db->rollBack(); error400('Échec : '.$e->getMessage()); }
}

// ──────────────────────────────────────────
// INTERVENTIONS
// ──────────────────────────────────────────
if ($action === 'int_list') {
    apiRequireRole(['admin','technicien','magasinier']);
    $where = []; $params = [];
    if (!empty($body['statut'])) { $where[] = "i.statut=?"; $params[] = $body['statut']; }
    if (!empty($body['client_id'])) { $where[] = "i.client_id=?"; $params[] = $body['client_id']; }
    if (!empty($body['technicien_id'])) { $where[] = "i.technicien_id=?"; $params[] = $body['technicien_id']; }
    if (isset($body['ouvertes']) && $body['ouvertes']) { $where[] = "i.statut NOT IN ('cloturee','annulee')"; }
    $sql = "SELECT i.*, m.modele, m.n_serie, cl.raison_sociale, u.nom AS technicien_nom
        FROM interventions i
        JOIN machines m ON m.id=i.machine_id
        JOIN clients cl ON cl.id=i.client_id
        LEFT JOIN users u ON u.id=i.technicien_id";
    if ($where) $sql .= " WHERE " . implode(' AND ', $where);
    $sql .= " ORDER BY FIELD(i.statut,'nouvelle','en_cours','en_attente_piece','planifiee','resolue','cloturee','annulee'),
              FIELD(i.priorite,'urgente','haute','normale','basse'), i.created_at DESC";
    $st = $db->prepare($sql); $st->execute($params);
    respond($st->fetchAll());
}
if ($action === 'int_get') {
    apiRequireRole(['admin','technicien','magasinier']);
    $st = $db->prepare("SELECT i.*, m.modele, m.n_serie, m.compteur_plaques, cl.raison_sociale, u.nom AS technicien_nom
        FROM interventions i
        JOIN machines m ON m.id=i.machine_id
        JOIN clients cl ON cl.id=i.client_id
        LEFT JOIN users u ON u.id=i.technicien_id WHERE i.id=?");
    $st->execute([$body['id'] ?? 0]);
    $i = $st->fetch(); if (!$i) error400('Intervention introuvable');
    $p = $db->prepare("SELECT ip.*, pc.reference, pc.designation FROM intervention_pieces ip JOIN pieces pc ON pc.id=ip.piece_id WHERE ip.intervention_id=?");
    $p->execute([$body['id']]);
    $i['pieces'] = $p->fetchAll();
    respond($i);
}
if ($action === 'int_save') {
    apiRequireRole(['admin','technicien']);
    $d = $body;
    if (empty($d['machine_id'])) error400('Machine requise');
    // client dérivé de la machine
    $mst = $db->prepare("SELECT client_id FROM machines WHERE id=?"); $mst->execute([$d['machine_id']]);
    $clientId = $mst->fetchColumn(); if (!$clientId) error400('Machine introuvable');
    $type = in_array($d['type'] ?? '', ['preventive','corrective','installation','mise_a_jour'], true) ? $d['type'] : 'corrective';
    $prio = in_array($d['priorite'] ?? '', ['basse','normale','haute','urgente'], true) ? $d['priorite'] : 'normale';
    $stat = in_array($d['statut'] ?? '', ['nouvelle','planifiee','en_cours','en_attente_piece','resolue','cloturee','annulee'], true) ? $d['statut'] : 'nouvelle';
    $tech = ($d['technicien_id'] ?? '') !== '' ? (int)$d['technicien_id'] : null;
    $cols = "machine_id=?,client_id=?,contrat_id=?,type=?,priorite=?,statut=?,technicien_id=?,
             date_planifiee=?,date_debut=?,date_fin=?,compteur_releve=?,description=?,diagnostic=?,resolution=?,
             temps_passe_h=?,cout_main_oeuvre=?";
    $vals = [$d['machine_id'], $clientId, $d['contrat_id']?:null, $type, $prio, $stat, $tech,
             $d['date_planifiee']?:null, $d['date_debut']?:null, $d['date_fin']?:null,
             ($d['compteur_releve']??'')!==''?(int)$d['compteur_releve']:null,
             $d['description']??null, $d['diagnostic']??null, $d['resolution']??null,
             $d['temps_passe_h']?:0, $d['cout_main_oeuvre']?:0];
    if (!empty($d['id'])) {
        $db->prepare("UPDATE interventions SET $cols WHERE id=?")->execute(array_merge($vals, [$d['id']]));
        respond(['ok'=>true, 'id'=>$d['id']]);
    } else {
        $num = nextDocNumero($db, 'interventions', 'INT');
        $origine = in_array($d['origine'] ?? '', ['client','preventif','interne'], true) ? $d['origine'] : 'interne';
        $db->prepare("INSERT INTO interventions SET numero=?, origine=?, $cols")
           ->execute(array_merge([$num, $origine], $vals));
        respond(['ok'=>true, 'id'=>$db->lastInsertId(), 'numero'=>$num]);
    }
}
if ($action === 'int_change_statut') {
    apiRequireRole(['admin','technicien']);
    $valid = ['nouvelle','planifiee','en_cours','en_attente_piece','resolue','cloturee','annulee'];
    $s = $body['statut'] ?? ''; if (!in_array($s, $valid, true)) error400('Statut invalide');
    $id = (int)($body['id'] ?? 0);
    // horodatage auto début / fin
    if ($s === 'en_cours') {
        $db->prepare("UPDATE interventions SET statut=?, date_debut=COALESCE(date_debut,NOW()) WHERE id=?")->execute([$s, $id]);
    } elseif (in_array($s, ['resolue','cloturee'], true)) {
        $db->prepare("UPDATE interventions SET statut=?, date_fin=COALESCE(date_fin,NOW()) WHERE id=?")->execute([$s, $id]);
    } else {
        $db->prepare("UPDATE interventions SET statut=? WHERE id=?")->execute([$s, $id]);
    }
    respond(['ok'=>true]);
}
if ($action === 'int_delete') {
    apiRequireRole(['admin']);
    // rendre les pièces consommées au stock avant suppression
    $db->beginTransaction();
    try {
        $p = $db->prepare("SELECT piece_id, quantite FROM intervention_pieces WHERE intervention_id=?");
        $p->execute([$body['id']]);
        foreach ($p->fetchAll() as $ln) {
            moveStock($db, (int)$ln['piece_id'], (int)$ln['quantite'], 'entree', 'Annulation intervention', 'intervention', (int)$body['id'], (int)currentUser()['id']);
        }
        $db->prepare("DELETE FROM interventions WHERE id=?")->execute([$body['id']]);
        $db->commit();
        respond(['ok'=>true]);
    } catch (Exception $e) { $db->rollBack(); error400('Suppression impossible : '.$e->getMessage()); }
}
// Consommation d'une pièce sur une intervention (décrémente le stock, mouvement tracé)
if ($action === 'int_add_piece') {
    apiRequireRole(['admin','technicien','magasinier']);
    $intId = (int)($body['intervention_id'] ?? 0);
    $pieceId = (int)($body['piece_id'] ?? 0);
    $qte = max(1, (int)($body['quantite'] ?? 1));
    $pst = $db->prepare("SELECT * FROM pieces WHERE id=?"); $pst->execute([$pieceId]);
    $piece = $pst->fetch(); if (!$piece) error400('Pièce introuvable');
    $ist = $db->prepare("SELECT id FROM interventions WHERE id=?"); $ist->execute([$intId]);
    if (!$ist->fetch()) error400('Intervention introuvable');
    $pu = ($body['prix_unitaire'] ?? '') !== '' ? $body['prix_unitaire'] : $piece['prix_vente'];
    $db->beginTransaction();
    try {
        $db->prepare("INSERT INTO intervention_pieces (intervention_id,piece_id,quantite,prix_unitaire) VALUES (?,?,?,?)")
           ->execute([$intId, $pieceId, $qte, $pu]);
        moveStock($db, $pieceId, -$qte, 'sortie', 'Consommation intervention', 'intervention', $intId, (int)currentUser()['id']);
        $db->commit();
        respond(['ok'=>true]);
    } catch (Exception $e) { $db->rollBack(); error400('Échec : '.$e->getMessage()); }
}
if ($action === 'int_remove_piece') {
    apiRequireRole(['admin','technicien','magasinier']);
    $lid = (int)($body['ligne_id'] ?? 0);
    $st = $db->prepare("SELECT * FROM intervention_pieces WHERE id=?"); $st->execute([$lid]);
    $ln = $st->fetch(); if (!$ln) error400('Ligne introuvable');
    $db->beginTransaction();
    try {
        $db->prepare("DELETE FROM intervention_pieces WHERE id=?")->execute([$lid]);
        moveStock($db, (int)$ln['piece_id'], (int)$ln['quantite'], 'entree', 'Retour pièce intervention', 'intervention', (int)$ln['intervention_id'], (int)currentUser()['id']);
        $db->commit();
        respond(['ok'=>true]);
    } catch (Exception $e) { $db->rollBack(); error400('Échec : '.$e->getMessage()); }
}
if ($action === 'int_options') {
    apiRequireRole(['admin','technicien','magasinier']);
    respond([
        'machines' => $db->query("SELECT m.id, m.modele, m.n_serie, c.raison_sociale FROM machines m JOIN clients c ON c.id=m.client_id WHERE m.statut<>'retire' ORDER BY c.raison_sociale, m.modele")->fetchAll(),
        'techniciens' => $db->query("SELECT id, nom FROM users WHERE role IN ('technicien','admin') AND actif=1 ORDER BY nom")->fetchAll(),
        'pieces' => $db->query("SELECT id, reference, designation, prix_vente, stock FROM pieces WHERE actif=1 ORDER BY reference")->fetchAll(),
    ]);
}

// ──────────────────────────────────────────
// PIÈCES DÉTACHÉES
// ──────────────────────────────────────────
if ($action === 'piece_list') {
    apiRequireRole(['admin','technicien','magasinier']);
    $rows = $db->query("SELECT * FROM pieces ORDER BY reference")->fetchAll();
    respond($rows);
}
if ($action === 'piece_get') {
    apiRequireRole(['admin','technicien','magasinier']);
    $st = $db->prepare("SELECT * FROM pieces WHERE id=?"); $st->execute([$body['id'] ?? 0]);
    $p = $st->fetch(); if (!$p) error400('Pièce introuvable');
    respond($p);
}
if ($action === 'piece_save') {
    apiRequireRole(['admin','magasinier']);
    $d = $body;
    if (empty(trim($d['reference'] ?? ''))) error400('Référence requise');
    if (empty(trim($d['designation'] ?? ''))) error400('Désignation requise');
    $chk = $db->prepare("SELECT id FROM pieces WHERE reference=? AND id<>?");
    $chk->execute([$d['reference'], $d['id'] ?? 0]);
    if ($chk->fetch()) error400('Cette référence existe déjà');
    $cols = "reference=?,designation=?,categorie=?,compatibilite=?,fournisseur=?,prix_achat=?,prix_vente=?,seuil_alerte=?,emplacement=?,actif=?";
    $vals = [$d['reference'], $d['designation'], $d['categorie']??null, $d['compatibilite']??null, $d['fournisseur']??null,
             $d['prix_achat']?:0, $d['prix_vente']?:0, (int)($d['seuil_alerte']??0), $d['emplacement']??null, $d['actif']??1];
    if (!empty($d['id'])) {
        // le stock ne se modifie jamais ici — uniquement via mouvements tracés
        $db->prepare("UPDATE pieces SET $cols WHERE id=?")->execute(array_merge($vals, [$d['id']]));
        respond(['ok'=>true, 'id'=>$d['id']]);
    } else {
        $stockInit = (int)($d['stock']??0);
        $db->prepare("INSERT INTO pieces SET stock=?, $cols")->execute(array_merge([$stockInit], $vals));
        $pid = (int)$db->lastInsertId();
        if ($stockInit !== 0) {
            $db->prepare("INSERT INTO mouvements_stock (piece_id,type,quantite,stock_apres,motif,ref_type,user_id) VALUES (?,?,?,?,?,?,?)")
               ->execute([$pid, 'entree', $stockInit, $stockInit, 'Stock initial', 'manuel', (int)currentUser()['id']]);
        }
        respond(['ok'=>true, 'id'=>$pid]);
    }
}
if ($action === 'piece_ajuster_stock') {
    apiRequireRole(['admin','magasinier']);
    $pieceId = (int)($body['id'] ?? 0);
    $delta = (int)($body['delta'] ?? 0);
    if ($delta === 0) error400('Quantité nulle');
    $pst = $db->prepare("SELECT id FROM pieces WHERE id=?"); $pst->execute([$pieceId]);
    if (!$pst->fetch()) error400('Pièce introuvable');
    $type = $delta > 0 ? 'entree' : 'sortie';
    moveStock($db, $pieceId, $delta, ($body['type'] ?? $type) === 'ajustement' ? 'ajustement' : $type,
        $body['motif'] ?? 'Ajustement manuel', 'manuel', null, (int)currentUser()['id']);
    respond(['ok'=>true]);
}
if ($action === 'piece_mouvements') {
    apiRequireRole(['admin','technicien','magasinier']);
    $st = $db->prepare("SELECT mv.*, u.nom AS user_nom FROM mouvements_stock mv LEFT JOIN users u ON u.id=mv.user_id WHERE mv.piece_id=? ORDER BY mv.id DESC LIMIT 50");
    $st->execute([$body['id'] ?? 0]);
    respond($st->fetchAll());
}
if ($action === 'piece_delete') {
    apiRequireRole(['admin']);
    try {
        $db->prepare("DELETE FROM pieces WHERE id=?")->execute([$body['id']]);
        respond(['ok'=>true]);
    } catch (Exception $e) { error400('Impossible : cette pièce est utilisée. Désactivez-la plutôt.'); }
}

// ──────────────────────────────────────────
// COMMANDES DE PIÈCES (fournisseur)
// ──────────────────────────────────────────
if ($action === 'cmd_list') {
    apiRequireRole(['admin','magasinier']);
    $rows = $db->query("SELECT cp.*, (SELECT COUNT(*) FROM commande_lignes l WHERE l.commande_id=cp.id) AS nb_lignes
        FROM commandes_pieces cp ORDER BY cp.created_at DESC")->fetchAll();
    respond($rows);
}
if ($action === 'cmd_get') {
    apiRequireRole(['admin','magasinier']);
    $st = $db->prepare("SELECT * FROM commandes_pieces WHERE id=?"); $st->execute([$body['id'] ?? 0]);
    $c = $st->fetch(); if (!$c) error400('Commande introuvable');
    $l = $db->prepare("SELECT l.*, p.reference, p.designation FROM commande_lignes l JOIN pieces p ON p.id=l.piece_id WHERE l.commande_id=?");
    $l->execute([$body['id']]);
    $c['lignes'] = $l->fetchAll();
    respond($c);
}
if ($action === 'cmd_save') {
    apiRequireRole(['admin','magasinier']);
    $d = $body;
    $lignes = $d['lignes'] ?? [];
    $total = 0;
    foreach ($lignes as $ln) { $total += (float)($ln['prix_unitaire'] ?? 0) * (int)($ln['quantite'] ?? 0); }
    $db->beginTransaction();
    try {
        if (!empty($d['id'])) {
            $st = $db->prepare("SELECT statut FROM commandes_pieces WHERE id=?"); $st->execute([$d['id']]);
            $cur = $st->fetchColumn();
            if (in_array($cur, ['recue','partielle'], true)) error400('Commande déjà (partiellement) reçue : modification des lignes bloquée');
            $db->prepare("UPDATE commandes_pieces SET fournisseur=?,date_commande=?,date_reception_prevue=?,montant_total=?,notes=? WHERE id=?")
               ->execute([$d['fournisseur']??null, $d['date_commande']?:null, $d['date_reception_prevue']?:null, $total, $d['notes']??null, $d['id']]);
            $db->prepare("DELETE FROM commande_lignes WHERE commande_id=?")->execute([$d['id']]);
            $cmdId = (int)$d['id'];
        } else {
            $num = nextDocNumero($db, 'commandes_pieces', 'CMD');
            $db->prepare("INSERT INTO commandes_pieces (numero,fournisseur,statut,date_commande,date_reception_prevue,montant_total,notes)
                          VALUES (?,?,?,?,?,?,?)")
               ->execute([$num, $d['fournisseur']??null, 'brouillon', $d['date_commande']?:null, $d['date_reception_prevue']?:null, $total, $d['notes']??null]);
            $cmdId = (int)$db->lastInsertId();
        }
        $ins = $db->prepare("INSERT INTO commande_lignes (commande_id,piece_id,quantite,prix_unitaire) VALUES (?,?,?,?)");
        foreach ($lignes as $ln) {
            if (empty($ln['piece_id']) || (int)($ln['quantite'] ?? 0) < 1) continue;
            $ins->execute([$cmdId, (int)$ln['piece_id'], (int)$ln['quantite'], (float)($ln['prix_unitaire'] ?? 0)]);
        }
        $db->commit();
        respond(['ok'=>true, 'id'=>$cmdId]);
    } catch (Exception $e) { if ($db->inTransaction()) $db->rollBack(); error400('Échec : '.$e->getMessage()); }
}
if ($action === 'cmd_changer_statut') {
    apiRequireRole(['admin','magasinier']);
    $valid = ['brouillon','commandee','annulee'];
    $s = $body['statut'] ?? ''; if (!in_array($s, $valid, true)) error400('Statut invalide (la réception passe par cmd_recevoir)');
    $upd = $s === 'commandee'
        ? "UPDATE commandes_pieces SET statut=?, date_commande=COALESCE(date_commande,CURDATE()) WHERE id=?"
        : "UPDATE commandes_pieces SET statut=? WHERE id=?";
    $db->prepare($upd)->execute([$s, (int)($body['id'] ?? 0)]);
    respond(['ok'=>true]);
}
// Réception : entre en stock les quantités reçues (tracées), gère réception partielle.
if ($action === 'cmd_recevoir') {
    apiRequireRole(['admin','magasinier']);
    $cmdId = (int)($body['id'] ?? 0);
    $recues = $body['recues'] ?? []; // [ligne_id => quantite_recue_maintenant]
    $st = $db->prepare("SELECT * FROM commandes_pieces WHERE id=?"); $st->execute([$cmdId]);
    $cmd = $st->fetch(); if (!$cmd) error400('Commande introuvable');
    if ($cmd['statut'] === 'annulee') error400('Commande annulée');
    $lst = $db->prepare("SELECT * FROM commande_lignes WHERE commande_id=?"); $lst->execute([$cmdId]);
    $lignes = $lst->fetchAll();
    $db->beginTransaction();
    try {
        $uid = (int)currentUser()['id'];
        foreach ($lignes as $ln) {
            $reste = (int)$ln['quantite'] - (int)$ln['quantite_recue'];
            $q = isset($recues[$ln['id']]) ? min((int)$recues[$ln['id']], $reste) : $reste; // défaut : tout le reste
            if ($q <= 0) continue;
            $db->prepare("UPDATE commande_lignes SET quantite_recue = quantite_recue + ? WHERE id=?")->execute([$q, $ln['id']]);
            moveStock($db, (int)$ln['piece_id'], $q, 'entree', 'Réception commande '.$cmd['numero'], 'commande', $cmdId, $uid);
        }
        // recalcul du statut
        $chk = $db->prepare("SELECT SUM(quantite) tot, SUM(quantite_recue) recu FROM commande_lignes WHERE commande_id=?");
        $chk->execute([$cmdId]); $agg = $chk->fetch();
        $newStatut = ((int)$agg['recu'] >= (int)$agg['tot'] && (int)$agg['tot'] > 0) ? 'recue' : 'partielle';
        $setDate = $newStatut === 'recue' ? ", date_reception=CURDATE()" : "";
        $db->prepare("UPDATE commandes_pieces SET statut=? $setDate WHERE id=?")->execute([$newStatut, $cmdId]);
        $db->commit();
        respond(['ok'=>true, 'statut'=>$newStatut]);
    } catch (Exception $e) { $db->rollBack(); error400('Échec réception : '.$e->getMessage()); }
}
if ($action === 'cmd_delete') {
    apiRequireRole(['admin','magasinier']);
    $st = $db->prepare("SELECT statut FROM commandes_pieces WHERE id=?"); $st->execute([$body['id'] ?? 0]);
    $s = $st->fetchColumn();
    if (in_array($s, ['recue','partielle'], true)) error400('Impossible de supprimer une commande reçue (stock déjà entré).');
    $db->prepare("DELETE FROM commandes_pieces WHERE id=?")->execute([$body['id']]);
    respond(['ok'=>true]);
}
if ($action === 'cmd_options') {
    apiRequireRole(['admin','magasinier']);
    respond(['pieces' => $db->query("SELECT id, reference, designation, prix_achat FROM pieces WHERE actif=1 ORDER BY reference")->fetchAll()]);
}

// ──────────────────────────────────────────
// UTILISATEURS (admin)
// ──────────────────────────────────────────
if ($action === 'user_list') {
    apiRequireRole(['admin']);
    $rows = $db->query("SELECT u.id,u.nom,u.email,u.role,u.client_id,u.telephone,u.avatar,u.actif,u.created_at,
            c.raison_sociale FROM users u LEFT JOIN clients c ON c.id=u.client_id ORDER BY u.nom")->fetchAll();
    respond($rows);
}
if ($action === 'user_get') {
    apiRequireRole(['admin']);
    $st = $db->prepare("SELECT id,nom,email,role,client_id,telephone,avatar,actif FROM users WHERE id=?");
    $st->execute([$body['id'] ?? 0]);
    $u = $st->fetch(); if (!$u) error400('Utilisateur introuvable');
    respond($u);
}
if ($action === 'user_save') {
    apiRequireRole(['admin']);
    $d = $body;
    if (empty(trim($d['nom'] ?? ''))) error400('Nom requis');
    if (empty(trim($d['email'] ?? ''))) error400('Email requis');
    $role = in_array($d['role'] ?? '', ['admin','technicien','magasinier','client'], true) ? $d['role'] : 'client';
    $clientId = $role === 'client' ? ($d['client_id'] ?: null) : null;
    if ($role === 'client' && !$clientId) error400('Un compte client doit être rattaché à un client');
    $avatar = strtoupper(substr(trim($d['avatar'] ?? '') ?: mb_substr($d['nom'],0,2), 0, 2));
    $chk = $db->prepare("SELECT id FROM users WHERE email=? AND id<>?");
    $chk->execute([$d['email'], $d['id'] ?? 0]);
    if ($chk->fetch()) error400('Cet email est déjà utilisé');
    if (!empty($d['id'])) {
        $db->prepare("UPDATE users SET nom=?,email=?,role=?,client_id=?,telephone=?,avatar=?,actif=? WHERE id=?")
           ->execute([$d['nom'],$d['email'],$role,$clientId,$d['telephone']??null,$avatar,$d['actif']??1,$d['id']]);
        if (!empty($d['password'])) {
            $db->prepare("UPDATE users SET password_hash=? WHERE id=?")
               ->execute([password_hash($d['password'], PASSWORD_DEFAULT), $d['id']]);
        }
        respond(['ok'=>true, 'id'=>$d['id']]);
    } else {
        if (empty($d['password'])) error400('Mot de passe requis à la création');
        $db->prepare("INSERT INTO users (nom,email,password_hash,role,client_id,telephone,avatar,actif) VALUES (?,?,?,?,?,?,?,?)")
           ->execute([$d['nom'],$d['email'],password_hash($d['password'], PASSWORD_DEFAULT),$role,$clientId,$d['telephone']??null,$avatar,$d['actif']??1]);
        respond(['ok'=>true, 'id'=>$db->lastInsertId()]);
    }
}
if ($action === 'user_toggle_actif') {
    apiRequireRole(['admin']);
    if ((int)$body['id'] === (int)currentUser()['id']) error400('Vous ne pouvez pas désactiver votre propre compte');
    $db->prepare("UPDATE users SET actif = NOT actif WHERE id=?")->execute([$body['id']]);
    respond(['ok'=>true]);
}
if ($action === 'user_delete') {
    apiRequireRole(['admin']);
    if ((int)$body['id'] === (int)currentUser()['id']) error400('Vous ne pouvez pas supprimer votre propre compte');
    try {
        $db->prepare("DELETE FROM users WHERE id=?")->execute([$body['id']]);
        respond(['ok'=>true]);
    } catch (Exception $e) { error400('Impossible : cet utilisateur est référencé (interventions). Désactivez-le plutôt.'); }
}
if ($action === 'user_clients') {
    apiRequireRole(['admin']);
    respond($db->query("SELECT id, raison_sociale FROM clients WHERE actif=1 ORDER BY raison_sociale")->fetchAll());
}

// ──────────────────────────────────────────
// PORTAIL CLIENT (portée dérivée de la session)
// ──────────────────────────────────────────
if ($action === 'mes_machines') {
    apiRequireRole(['client']);
    $st = $db->prepare("SELECT * FROM machines WHERE client_id=? ORDER BY modele");
    $st->execute([monClientId()]);
    respond($st->fetchAll());
}
if ($action === 'mes_interventions') {
    apiRequireRole(['client']);
    $st = $db->prepare("SELECT i.id,i.numero,i.type,i.priorite,i.statut,i.description,i.date_planifiee,i.date_fin,i.created_at,
            m.modele,m.n_serie FROM interventions i JOIN machines m ON m.id=i.machine_id
            WHERE i.client_id=? ORDER BY i.created_at DESC");
    $st->execute([monClientId()]);
    respond($st->fetchAll());
}
if ($action === 'mes_intervention_get') {
    apiRequireRole(['client']);
    $st = $db->prepare("SELECT i.id,i.numero,i.type,i.priorite,i.statut,i.description,i.diagnostic,i.resolution,
            i.date_planifiee,i.date_debut,i.date_fin,i.created_at,m.modele,m.n_serie
            FROM interventions i JOIN machines m ON m.id=i.machine_id
            WHERE i.id=? AND i.client_id=?");
    $st->execute([$body['id'] ?? 0, monClientId()]);
    $i = $st->fetch(); if (!$i) error400('Intervention introuvable');
    respond($i);
}
// Signalement d'une panne par le client → intervention corrective "nouvelle"
if ($action === 'mes_signaler') {
    apiRequireRole(['client']);
    $cid = monClientId();
    $machineId = (int)($body['machine_id'] ?? 0);
    // la machine DOIT appartenir au client de la session
    $chk = $db->prepare("SELECT id FROM machines WHERE id=? AND client_id=?");
    $chk->execute([$machineId, $cid]);
    if (!$chk->fetch()) error400('Machine invalide');
    $desc = trim($body['description'] ?? '');
    if ($desc === '') error400('Décrivez le problème rencontré');
    $prio = in_array($body['priorite'] ?? '', ['basse','normale','haute','urgente'], true) ? $body['priorite'] : 'normale';
    $num = nextDocNumero($db, 'interventions', 'INT');
    $db->prepare("INSERT INTO interventions (numero,machine_id,client_id,type,origine,priorite,statut,description)
                  VALUES (?,?,?,'corrective','client',?,'nouvelle',?)")
       ->execute([$num, $machineId, $cid, $prio, $desc]);
    respond(['ok'=>true, 'numero'=>$num]);
}

error400('Action inconnue: ' . $action);
