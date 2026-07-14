<?php
require_once '../config.php';
require_once '../includes/security.php';
sendSecurityHeaders(true);
secureSessionStart();

// Anti-CSRF : le header custom X-Requested-With force un preflight CORS,
// impossible à envoyer depuis un site tiers sans autorisation explicite.
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode(['error' => 'Requête non autorisée']));
}

requireLogin();
header('Content-Type: application/json; charset=utf-8');

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? ($_GET['action'] ?? '');
$db = getDB();

// Rôle "viewer" = lecture seule sur toute l'API
$__u = currentUser();
if (($__u['role'] ?? '') === 'viewer' &&
    preg_match('/_(save|delete|create|update|toggle|set_statut|done|reply|upload)/', $action)) {
    http_response_code(403);
    die(json_encode(['error' => 'Compte en lecture seule']));
}

function respond($data) { echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function error400($msg) { http_response_code(400); respond(['error' => $msg]); }
function requireAdmin() {
    $u = currentUser();
    if (!$u || $u['role'] !== 'admin') {
        http_response_code(403);
        respond(['error' => 'Accès réservé aux administrateurs']);
    }
}

// ──────────────────────────────────────────
// INVESTISSEURS
// ──────────────────────────────────────────
if ($action === 'inv_list') {
    $rows = $db->query("SELECT * FROM investisseurs ORDER BY 
        CASE score_chaleur WHEN '🔥 Chaud' THEN 1 WHEN '🟡 Tiède' THEN 2 ELSE 3 END,
        date_prochain_contact ASC")->fetchAll();
    respond($rows);
}
if ($action === 'inv_save') {
    $d = $body;
    if (!empty($d['id'])) {
        $stmt = $db->prepare("UPDATE investisseurs SET nom=?,organisation=?,type=?,pays=?,email=?,linkedin=?,ticket_min=?,ticket_max=?,devise=?,statut=?,score_chaleur=?,connexions_communes=?,date_premier_contact=?,date_dernier_contact=?,date_prochain_contact=?,source_rencontre=?,notes=? WHERE id=?");
        $stmt->execute([$d['nom'],$d['organisation'],$d['type'],$d['pays'],$d['email'],$d['linkedin'],
            $d['ticket_min']??0,$d['ticket_max']??0,$d['devise']??'EUR',$d['statut'],$d['score_chaleur'],
            $d['connexions_communes']??0,$d['date_premier_contact']??null,$d['date_dernier_contact']??null,
            $d['date_prochain_contact']??null,$d['source_rencontre'],$d['notes'],$d['id']]);
    } else {
        $stmt = $db->prepare("INSERT INTO investisseurs (nom,organisation,type,pays,email,linkedin,ticket_min,ticket_max,devise,statut,score_chaleur,connexions_communes,date_premier_contact,date_dernier_contact,date_prochain_contact,source_rencontre,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$d['nom'],$d['organisation'],$d['type'],$d['pays'],$d['email'],$d['linkedin'],
            $d['ticket_min']??0,$d['ticket_max']??0,$d['devise']??'EUR',$d['statut'],$d['score_chaleur'],
            $d['connexions_communes']??0,$d['date_premier_contact']??null,$d['date_dernier_contact']??null,
            $d['date_prochain_contact']??null,$d['source_rencontre'],$d['notes'],$_SESSION['user_id']]);
    }
    respond(['ok' => true]);
}
if ($action === 'inv_delete') {
    $db->prepare("DELETE FROM investisseurs WHERE id=?")->execute([$body['id']]);
    respond(['ok' => true]);
}

// ──────────────────────────────────────────
// CLIENTS / PROSPECTS
// ──────────────────────────────────────────
if ($action === 'cli_list') {
    $rows = $db->query("SELECT * FROM clients_prospects ORDER BY updated_at DESC")->fetchAll();
    respond($rows);
}
if ($action === 'cli_save') {
    $d = $body;
    if (!empty($d['id'])) {
        $stmt = $db->prepare("UPDATE clients_prospects SET nom_entreprise=?,pays=?,ville=?,secteur=?,source=?,contact_nom=?,contact_email=?,contact_tel=?,stade=?,probabilite_closing=?,version_aquaclean=?,machine_attribuee=?,prix_ht=?,devise=?,roi_estime_mois=?,date_premier_contact=?,date_devis=?,date_closing_prevu=?,notes=? WHERE id=?");
        $stmt->execute([$d['nom_entreprise'],$d['pays'],$d['ville'],$d['secteur'],$d['source'],$d['contact_nom'],$d['contact_email'],$d['contact_tel'],$d['stade'],$d['probabilite_closing']??0,$d['version_aquaclean']??'V1',$d['machine_attribuee'],$d['prix_ht']??30000,$d['devise']??'EUR',$d['roi_estime_mois']??12,$d['date_premier_contact']??null,$d['date_devis']??null,$d['date_closing_prevu']??null,$d['notes'],$d['id']]);
    } else {
        $stmt = $db->prepare("INSERT INTO clients_prospects (nom_entreprise,pays,ville,secteur,source,contact_nom,contact_email,contact_tel,stade,probabilite_closing,version_aquaclean,machine_attribuee,prix_ht,devise,roi_estime_mois,date_premier_contact,date_devis,date_closing_prevu,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$d['nom_entreprise'],$d['pays'],$d['ville'],$d['secteur'],$d['source'],$d['contact_nom'],$d['contact_email'],$d['contact_tel'],$d['stade'],$d['probabilite_closing']??0,$d['version_aquaclean']??'V1',$d['machine_attribuee'],$d['prix_ht']??30000,$d['devise']??'EUR',$d['roi_estime_mois']??12,$d['date_premier_contact']??null,$d['date_devis']??null,$d['date_closing_prevu']??null,$d['notes'],$_SESSION['user_id']]);
    }
    respond(['ok' => true]);
}
if ($action === 'cli_delete') {
    $db->prepare("DELETE FROM clients_prospects WHERE id=?")->execute([$body['id']]);
    respond(['ok' => true]);
}
if ($action === 'cli_search') {
    $q = '%' . trim($body['q'] ?? '') . '%';
    $stmt = $db->prepare("SELECT id, nom_entreprise, contact_email, pays, ville FROM clients_prospects WHERE nom_entreprise LIKE ? ORDER BY nom_entreprise LIMIT 10");
    $stmt->execute([$q]);
    respond(['results' => $stmt->fetchAll()]);
}

// ──────────────────────────────────────────
// CANDIDATURES
// ──────────────────────────────────────────
if ($action === 'cand_list') {
    $rows = $db->query("SELECT * FROM candidatures ORDER BY FIELD(priorite,'🔴 Urgent','🟡 Important','🟢 Normal'), date_reponse_prevue ASC")->fetchAll();
    respond($rows);
}
if ($action === 'cand_save') {
    $d = $body;
    if (!empty($d['id'])) {
        $stmt = $db->prepare("UPDATE candidatures SET programme=?,organisme=?,type=?,pays=?,montant_demande=?,devise=?,statut=?,date_soumission=?,date_reponse_prevue=?,date_reponse_reelle=?,contact_referent=?,contact_email=?,documents_soumis=?,priorite=?,notes=? WHERE id=?");
        $stmt->execute([$d['programme'],$d['organisme'],$d['type'],$d['pays'],$d['montant_demande']??0,$d['devise']??'TND',$d['statut'],$d['date_soumission']??null,$d['date_reponse_prevue']??null,$d['date_reponse_reelle']??null,$d['contact_referent'],$d['contact_email'],$d['documents_soumis']??0,$d['priorite'],$d['notes'],$d['id']]);
    } else {
        $stmt = $db->prepare("INSERT INTO candidatures (programme,organisme,type,pays,montant_demande,devise,statut,date_soumission,date_reponse_prevue,date_reponse_reelle,contact_referent,contact_email,documents_soumis,priorite,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$d['programme'],$d['organisme'],$d['type'],$d['pays'],$d['montant_demande']??0,$d['devise']??'TND',$d['statut'],$d['date_soumission']??null,$d['date_reponse_prevue']??null,$d['date_reponse_reelle']??null,$d['contact_referent'],$d['contact_email'],$d['documents_soumis']??0,$d['priorite'],$d['notes']]);
    }
    respond(['ok' => true]);
}
if ($action === 'cand_delete') {
    $db->prepare("DELETE FROM candidatures WHERE id=?")->execute([$body['id']]);
    respond(['ok' => true]);
}

// ──────────────────────────────────────────
// TÂCHES
// ──────────────────────────────────────────
if ($action === 'task_list') {
    $rows = $db->query("SELECT t.*, u.nom as resp_nom FROM taches t LEFT JOIN users u ON t.responsable_id=u.id ORDER BY FIELD(t.priorite,'🔴 Urgent','🟡 Important','🟢 Normal'), t.deadline ASC")->fetchAll();
    respond($rows);
}
if ($action === 'task_save') {
    $d = $body;
    if (!empty($d['id'])) {
        $stmt = $db->prepare("UPDATE taches SET titre=?,module_lie=?,priorite=?,responsable_id=?,deadline=?,statut=?,notes=? WHERE id=?");
        $stmt->execute([$d['titre'],$d['module_lie'],$d['priorite'],$d['responsable_id']??null,$d['deadline']??null,$d['statut'],$d['notes'],$d['id']]);
    } else {
        $stmt = $db->prepare("INSERT INTO taches (titre,module_lie,priorite,responsable_id,deadline,statut,notes,created_by) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$d['titre'],$d['module_lie'],$d['priorite'],$d['responsable_id']??null,$d['deadline']??null,$d['statut'],$d['notes'],$_SESSION['user_id']]);
    }
    respond(['ok' => true]);
}
if ($action === 'task_done') {
    $db->prepare("UPDATE taches SET statut='Terminé' WHERE id=?")->execute([$body['id']]);
    respond(['ok' => true]);
}
if ($action === 'task_delete') {
    $db->prepare("DELETE FROM taches WHERE id=?")->execute([$body['id']]);
    respond(['ok' => true]);
}

// ──────────────────────────────────────────
// PARTENAIRES
// ──────────────────────────────────────────
if ($action === 'part_list') {
    $rows = $db->query("SELECT * FROM partenaires ORDER BY statut, nom")->fetchAll();
    respond($rows);
}
if ($action === 'part_save') {
    $d = $body;
    if (!empty($d['id'])) {
        $stmt = $db->prepare("UPDATE partenaires SET nom=?,type=?,territoire=?,pays=?,contact_nom=?,contact_email=?,contact_tel=?,contrat_signe=?,type_contrat=?,date_signature=?,date_expiration=?,volume_objectif=?,volume_realise=?,marge_pct=?,statut=?,notes=? WHERE id=?");
        $stmt->execute([$d['nom'],$d['type'],$d['territoire'],$d['pays'],$d['contact_nom'],$d['contact_email'],$d['contact_tel'],$d['contrat_signe']??0,$d['type_contrat'],$d['date_signature']??null,$d['date_expiration']??null,$d['volume_objectif']??0,$d['volume_realise']??0,$d['marge_pct']??0,$d['statut'],$d['notes'],$d['id']]);
    } else {
        $stmt = $db->prepare("INSERT INTO partenaires (nom,type,territoire,pays,contact_nom,contact_email,contact_tel,contrat_signe,type_contrat,date_signature,date_expiration,volume_objectif,volume_realise,marge_pct,statut,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$d['nom'],$d['type'],$d['territoire'],$d['pays'],$d['contact_nom'],$d['contact_email'],$d['contact_tel'],$d['contrat_signe']??0,$d['type_contrat'],$d['date_signature']??null,$d['date_expiration']??null,$d['volume_objectif']??0,$d['volume_realise']??0,$d['marge_pct']??0,$d['statut'],$d['notes']]);
    }
    respond(['ok' => true]);
}
if ($action === 'part_delete') {
    $db->prepare("DELETE FROM partenaires WHERE id=?")->execute([$body['id']]);
    respond(['ok' => true]);
}

// ──────────────────────────────────────────
// FABRICATION
// ──────────────────────────────────────────
if ($action === 'fab_list') {
    $rows = $db->query("SELECT f.*, c.nom_entreprise as client_nom FROM fabrication f LEFT JOIN clients_prospects c ON f.client_id=c.id ORDER BY f.machine_id")->fetchAll();
    respond($rows);
}
if ($action === 'fab_save') {
    $d = $body;
    if (!empty($d['id'])) {
        $stmt = $db->prepare("UPDATE fabrication SET machine_id=?,client_id=?,version=?,pays=?,statut=?,pompes_recues=?,hydraulique_recu=?,filtres_recus=?,assemblage_nielsen_ok=?,date_lancement=?,date_installation=?,numero_serie=?,blocages=?,notes=? WHERE id=?");
        $stmt->execute([$d['machine_id'],$d['client_id']??null,$d['version']??'V1',$d['pays'],$d['statut'],$d['pompes_recues']??0,$d['hydraulique_recu']??0,$d['filtres_recus']??0,$d['assemblage_nielsen_ok']??0,$d['date_lancement']??null,$d['date_installation']??null,$d['numero_serie'],$d['blocages'],$d['notes'],$d['id']]);
    } else {
        $stmt = $db->prepare("INSERT INTO fabrication (machine_id,client_id,version,pays,statut,pompes_recues,hydraulique_recu,filtres_recus,assemblage_nielsen_ok,date_lancement,date_installation,numero_serie,blocages,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$d['machine_id'],$d['client_id']??null,$d['version']??'V1',$d['pays'],$d['statut'],$d['pompes_recues']??0,$d['hydraulique_recu']??0,$d['filtres_recus']??0,$d['assemblage_nielsen_ok']??0,$d['date_lancement']??null,$d['date_installation']??null,$d['numero_serie'],$d['blocages'],$d['notes']]);
    }
    respond(['ok' => true]);
}

// ──────────────────────────────────────────
// LICENSING
// ──────────────────────────────────────────
if ($action === 'lic_list') {
    $rows = $db->query("SELECT * FROM licensing ORDER BY FIELD(priorite,'🔴 Priorité 1','🟡 Priorité 2','🟢 Priorité 3')")->fetchAll();
    respond($rows);
}
if ($action === 'lic_save') {
    $d = $body;
    if (!empty($d['id'])) {
        $stmt = $db->prepare("UPDATE licensing SET constructeur=?,pays=?,parc_machines_mondial=?,contact_nom=?,contact_email=?,statut=?,priorite=?,prerequis_brevet=?,notes=? WHERE id=?");
        $stmt->execute([$d['constructeur'],$d['pays'],$d['parc_machines_mondial']??0,$d['contact_nom'],$d['contact_email'],$d['statut'],$d['priorite'],$d['prerequis_brevet']??0,$d['notes'],$d['id']]);
    } else {
        $stmt = $db->prepare("INSERT INTO licensing (constructeur,pays,parc_machines_mondial,contact_nom,contact_email,statut,priorite,prerequis_brevet,notes) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$d['constructeur'],$d['pays'],$d['parc_machines_mondial']??0,$d['contact_nom'],$d['contact_email'],$d['statut'],$d['priorite'],$d['prerequis_brevet']??0,$d['notes']]);
    }
    respond(['ok' => true]);
}

// ──────────────────────────────────────────
// MESSAGES LOG
// ──────────────────────────────────────────
if ($action === 'msg_list') {
    $rows = $db->query("SELECT * FROM messages_log ORDER BY date_envoi DESC, created_at DESC")->fetchAll();
    respond($rows);
}
if ($action === 'msg_save') {
    $d = $body;
    if (!empty($d['id'])) {
        $stmt = $db->prepare("UPDATE messages_log SET destinataire=?,organisation=?,canal=?,objet=?,statut=?,date_envoi=?,date_reponse=?,module_lie=?,notes=? WHERE id=?");
        $stmt->execute([$d['destinataire'],$d['organisation'],$d['canal'],$d['objet'],$d['statut'],$d['date_envoi']??null,$d['date_reponse']??null,$d['module_lie'],$d['notes'],$d['id']]);
    } else {
        $stmt = $db->prepare("INSERT INTO messages_log (destinataire,organisation,canal,objet,statut,date_envoi,date_reponse,module_lie,notes) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$d['destinataire'],$d['organisation'],$d['canal'],$d['objet'],$d['statut'],$d['date_envoi']??null,$d['date_reponse']??null,$d['module_lie'],$d['notes']]);
    }
    respond(['ok' => true]);
}
if ($action === 'msg_delete') {
    $db->prepare("DELETE FROM messages_log WHERE id=?")->execute([$body['id']]);
    respond(['ok' => true]);
}

// ──────────────────────────────────────────
// CALENDRIER ÉDITORIAL LINKEDIN
// ──────────────────────────────────────────
if ($action === 'linkedin_list') {
    $rows = $db->query("SELECT * FROM linkedin_calendar ORDER BY date_publication ASC, numero ASC")->fetchAll();
    respond($rows);
}
if ($action === 'linkedin_save') {
    $d = $body;
    if (!empty($d['id'])) {
        $stmt = $db->prepare("UPDATE linkedin_calendar SET numero=?,titre=?,semaine=?,jour=?,heure=?,date_publication=?,texte_post=?,prompt_image=?,hashtags=?,secteur=?,statut=?,lien_post=?,notes=? WHERE id=?");
        $stmt->execute([$d['numero']??null,$d['titre'],$d['semaine'],$d['jour'],$d['heure']??'08:00:00',$d['date_publication']??null,$d['texte_post'],$d['prompt_image'],$d['hashtags'],$d['secteur'],$d['statut'],$d['lien_post'],$d['notes'],$d['id']]);
    } else {
        $stmt = $db->prepare("INSERT INTO linkedin_calendar (numero,titre,semaine,jour,heure,date_publication,texte_post,prompt_image,hashtags,secteur,statut,lien_post,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$d['numero']??null,$d['titre'],$d['semaine'],$d['jour'],$d['heure']??'08:00:00',$d['date_publication']??null,$d['texte_post'],$d['prompt_image'],$d['hashtags'],$d['secteur'],$d['statut']??'À programmer',$d['lien_post'],$d['notes']]);
    }
    respond(['ok' => true]);
}
if ($action === 'linkedin_delete') {
    $db->prepare("DELETE FROM linkedin_calendar WHERE id=?")->execute([$body['id']]);
    respond(['ok' => true]);
}
if ($action === 'linkedin_set_statut') {
    $db->prepare("UPDATE linkedin_calendar SET statut=? WHERE id=?")->execute([$body['statut'], $body['id']]);
    respond(['ok' => true]);
}

// ──────────────────────────────────────────
// DASHBOARD STATS
// ──────────────────────────────────────────
if ($action === 'dashboard_stats') {
    $stats = [];
    $stats['investisseurs_chauds'] = $db->query("SELECT COUNT(*) FROM investisseurs WHERE score_chaleur='🔥 Chaud'")->fetchColumn();
    $stats['investisseurs_total'] = $db->query("SELECT COUNT(*) FROM investisseurs")->fetchColumn();
    $stats['clients_pipeline'] = $db->query("SELECT COUNT(*) FROM clients_prospects WHERE stade NOT IN ('Installé','Perdu')")->fetchColumn();
    $stats['machines_installees'] = $db->query("SELECT COUNT(*) FROM fabrication WHERE statut='Installé'")->fetchColumn();
    $stats['taches_urgentes'] = $db->query("SELECT COUNT(*) FROM taches WHERE priorite='🔴 Urgent' AND statut!='Terminé'")->fetchColumn();
    $stats['candidatures_en_cours'] = $db->query("SELECT COUNT(*) FROM candidatures WHERE statut IN ('Soumis','En attente décision')")->fetchColumn();
    $stats['ca_2024'] = 229385;
    $stats['ca_2025'] = 29395;
    $stats['relances_today'] = $db->query("SELECT COUNT(*) FROM investisseurs WHERE date_prochain_contact <= CURDATE() AND statut NOT IN ('Investi','Refusé')")->fetchColumn();
    $stats['alertes_brevet'] = $db->query("SELECT COUNT(*) FROM taches WHERE alerte_brevet=1 AND statut!='Terminé'")->fetchColumn();
    respond($stats);
}

// ──────────────────────────────────────────
// USERS LIST (for selects)
// ──────────────────────────────────────────
if ($action === 'users_list') {
    $rows = $db->query("SELECT id, nom, email, role, avatar, actif, created_at FROM users ORDER BY id")->fetchAll();
    respond($rows);
}
if ($action === 'users_list_select') {
    $rows = $db->query("SELECT id, nom, avatar, role FROM users WHERE actif=1")->fetchAll();
    respond($rows);
}

// ──────────────────────────────────────────
// GESTION DES UTILISATEURS (admin uniquement)
// ──────────────────────────────────────────
if ($action === 'user_create') {
    requireAdmin();
    $d = $body;
    if (empty($d['nom']) || empty($d['email']) || empty($d['password'])) {
        error400('Nom, email et mot de passe requis');
    }
    $exists = $db->prepare("SELECT id FROM users WHERE email = ?");
    $exists->execute([$d['email']]);
    if ($exists->fetch()) error400('Cet email existe déjà');

    $hash = password_hash($d['password'], PASSWORD_BCRYPT, ['cost' => 12]);
    $avatar = $d['avatar'] ?: strtoupper(substr($d['nom'],0,1) . (strpos($d['nom'],' ') ? substr(strstr($d['nom'],' '),1,1) : ''));
    $stmt = $db->prepare("INSERT INTO users (nom,email,password_hash,role,avatar,actif) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$d['nom'], $d['email'], $hash, $d['role'] ?? 'viewer', $avatar, $d['actif'] ?? 1]);
    respond(['ok' => true, 'id' => $db->lastInsertId()]);
}
if ($action === 'user_update') {
    requireAdmin();
    $d = $body;
    if (empty($d['id']) || empty($d['nom']) || empty($d['email'])) error400('Champs requis manquants');

    $exists = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $exists->execute([$d['email'], $d['id']]);
    if ($exists->fetch()) error400('Cet email est déjà utilisé par un autre compte');

    if (!empty($d['password'])) {
        $hash = password_hash($d['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $db->prepare("UPDATE users SET nom=?,email=?,role=?,avatar=?,actif=?,password_hash=? WHERE id=?");
        $stmt->execute([$d['nom'], $d['email'], $d['role'], $d['avatar'], $d['actif'] ?? 1, $hash, $d['id']]);
    } else {
        $stmt = $db->prepare("UPDATE users SET nom=?,email=?,role=?,avatar=?,actif=? WHERE id=?");
        $stmt->execute([$d['nom'], $d['email'], $d['role'], $d['avatar'], $d['actif'] ?? 1, $d['id']]);
    }
    // Update session if editing own account
    if ($d['id'] == $_SESSION['user_id']) {
        $_SESSION['user']['nom'] = $d['nom'];
        $_SESSION['user']['email'] = $d['email'];
        $_SESSION['user']['role'] = $d['role'];
        $_SESSION['user']['avatar'] = $d['avatar'];
    }
    respond(['ok' => true]);
}
if ($action === 'user_delete') {
    requireAdmin();
    if ($body['id'] == $_SESSION['user_id']) error400('Vous ne pouvez pas supprimer votre propre compte');
    $countAdmins = $db->query("SELECT COUNT(*) FROM users WHERE role='admin' AND actif=1")->fetchColumn();
    $target = $db->prepare("SELECT role FROM users WHERE id=?");
    $target->execute([$body['id']]);
    $targetRole = $target->fetchColumn();
    if ($targetRole === 'admin' && $countAdmins <= 1) {
        error400('Impossible de supprimer le dernier administrateur');
    }
    $db->prepare("DELETE FROM users WHERE id=?")->execute([$body['id']]);
    respond(['ok' => true]);
}
if ($action === 'user_toggle_actif') {
    requireAdmin();
    if ($body['id'] == $_SESSION['user_id']) error400('Vous ne pouvez pas désactiver votre propre compte');
    $db->prepare("UPDATE users SET actif = NOT actif WHERE id=?")->execute([$body['id']]);
    respond(['ok' => true]);
}

// ──────────────────────────────────────────
// FACTURATION
// ──────────────────────────────────────────
if ($action === 'doc_list') {
    $type = $body['type'] ?? null;
    if ($type) {
        $stmt = $db->prepare("SELECT d.*, COUNT(p.id) as nb_paiements, COALESCE(SUM(p.montant),0) as montant_paye FROM documents d LEFT JOIN paiements_recus p ON p.document_id=d.id WHERE d.type=? GROUP BY d.id ORDER BY d.date_document DESC, d.created_at DESC");
        $stmt->execute([$type]);
    } else {
        $stmt = $db->query("SELECT d.*, COUNT(p.id) as nb_paiements, COALESCE(SUM(p.montant),0) as montant_paye FROM documents d LEFT JOIN paiements_recus p ON p.document_id=d.id GROUP BY d.id ORDER BY d.date_document DESC, d.created_at DESC");
    }
    respond($stmt->fetchAll());
}

if ($action === 'doc_get') {
    $stmt = $db->prepare("SELECT * FROM documents WHERE id=?");
    $stmt->execute([$body['id']]);
    $doc = $stmt->fetch();
    if (!$doc) error400('Document introuvable');
    $stmt2 = $db->prepare("SELECT * FROM document_lignes WHERE document_id=? ORDER BY position");
    $stmt2->execute([$body['id']]);
    $doc['lignes'] = $stmt2->fetchAll();
    $stmt3 = $db->prepare("SELECT * FROM paiements_recus WHERE document_id=? ORDER BY date_paiement");
    $stmt3->execute([$body['id']]);
    $doc['paiements'] = $stmt3->fetchAll();
    respond($doc);
}

if ($action === 'doc_next_numero') {
    $type = $body['type'] ?? 'Facture';
    $year = date('Y');
    $prefixes = ['Devis'=>'DEV','Facture'=>'FAC','Pro forma'=>'PF','Bon de livraison'=>'BL'];
    $prefix = ($prefixes[$type] ?? 'DOC') . '-' . $year . '-';
    $stmt = $db->prepare("SELECT numero FROM documents WHERE type=? AND YEAR(created_at)=? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$type, $year]);
    $last = $stmt->fetchColumn();
    $next = 1;
    if ($last) {
        $parts = explode('-', $last);
        $next = intval(end($parts)) + 1;
    }
    respond(['numero' => $prefix . str_pad($next, 3, '0', STR_PAD_LEFT)]);
}

if ($action === 'doc_save') {
    $d = $body;
    $lignes = $d['lignes'] ?? [];

    // Auto-create or link client in clients_prospects
    $clientId = isset($d['client_id']) && $d['client_id'] ? intval($d['client_id']) : null;
    if (!$clientId && !empty($d['client_nom'])) {
        $cstmt = $db->prepare("SELECT id FROM clients_prospects WHERE nom_entreprise = ? LIMIT 1");
        $cstmt->execute([trim($d['client_nom'])]);
        $existingId = $cstmt->fetchColumn();
        if ($existingId) {
            $clientId = intval($existingId);
        } else {
            $ins = $db->prepare("INSERT INTO clients_prospects (nom_entreprise, pays, ville, secteur, source, contact_nom, contact_email, contact_tel, stade, probabilite_closing, version_aquaclean, machine_attribuee, prix_ht, devise, roi_estime_mois, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->execute([
                trim($d['client_nom']),
                $d['client_pays'] ?? '',
                $d['client_adresse'] ?? '',
                '',
                'Facturation',
                '',
                $d['client_email'] ?? '',
                '',
                'Client',
                0,
                'V1',
                null,
                0,
                $d['devise'] ?? 'TND',
                0,
                !empty($d['client_mf']) ? 'MF: ' . $d['client_mf'] : '',
                $_SESSION['user_id']
            ]);
            $clientId = intval($db->lastInsertId());
        }
    }
    $d['client_id'] = $clientId;

    // Recalculate totals server-side
    $sousTotal = 0;
    foreach ($lignes as $l) {
        $sousTotal += floatval($l['quantite']) * floatval($l['prix_unitaire_ht']);
    }
    $tva = round($sousTotal * floatval($d['taux_tva'] ?? 19) / 100, 3);
    $timbre = floatval($d['timbre'] ?? 1);
    $ttc = round($sousTotal + $tva + $timbre, 3);

    if (!empty($d['id'])) {
        $stmt = $db->prepare("UPDATE documents SET type=?,statut=?,client_id=?,client_nom=?,client_adresse=?,client_mf=?,client_pays=?,client_email=?,date_document=?,date_echeance=?,date_validite=?,sous_total_ht=?,taux_tva=?,montant_tva=?,timbre=?,total_ttc=?,devise=?,mode_paiement=?,document_lie_id=?,notes=? WHERE id=?");
        $stmt->execute([$d['type'],$d['statut'],$d['client_id']??null,$d['client_nom'],$d['client_adresse'],$d['client_mf'],$d['client_pays'],$d['client_email'],$d['date_document']??null,$d['date_echeance']??null,$d['date_validite']??null,$sousTotal,$d['taux_tva']??19,$tva,$timbre,$ttc,$d['devise']??'TND',$d['mode_paiement'],$d['document_lie_id']??null,$d['notes'],$d['id']]);
        $docId = $d['id'];
        $db->prepare("DELETE FROM document_lignes WHERE document_id=?")->execute([$docId]);
    } else {
        // Check numero uniqueness
        $check = $db->prepare("SELECT id FROM documents WHERE numero=?");
        $check->execute([$d['numero']]);
        if ($check->fetch()) error400('Ce numéro de document existe déjà');
        $stmt = $db->prepare("INSERT INTO documents (numero,type,statut,client_id,client_nom,client_adresse,client_mf,client_pays,client_email,date_document,date_echeance,date_validite,sous_total_ht,taux_tva,montant_tva,timbre,total_ttc,devise,mode_paiement,document_lie_id,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$d['numero'],$d['type'],$d['statut']??'Brouillon',$d['client_id']??null,$d['client_nom'],$d['client_adresse'],$d['client_mf'],$d['client_pays'],$d['client_email'],$d['date_document']??null,$d['date_echeance']??null,$d['date_validite']??null,$sousTotal,$d['taux_tva']??19,$tva,$timbre,$ttc,$d['devise']??'TND',$d['mode_paiement'],$d['document_lie_id']??null,$d['notes'],$_SESSION['user_id']]);
        $docId = $db->lastInsertId();
    }

    // Insert lines
    $pos = 1;
    $stmtL = $db->prepare("INSERT INTO document_lignes (document_id,position,description,quantite,prix_unitaire_ht,total_ht) VALUES (?,?,?,?,?,?)");
    foreach ($lignes as $l) {
        $qte = floatval($l['quantite']);
        $pu = floatval($l['prix_unitaire_ht']);
        $stmtL->execute([$docId, $pos++, $l['description'], $qte, $pu, round($qte * $pu, 3)]);
    }
    respond(['ok' => true, 'id' => $docId]);
}

if ($action === 'doc_delete') {
    $db->prepare("DELETE FROM documents WHERE id=?")->execute([$body['id']]);
    respond(['ok' => true]);
}

if ($action === 'doc_set_statut') {
    $db->prepare("UPDATE documents SET statut=? WHERE id=?")->execute([$body['statut'], $body['id']]);
    respond(['ok' => true]);
}

if ($action === 'paiement_save') {
    $d = $body;
    $stmt = $db->prepare("INSERT INTO paiements_recus (document_id,date_paiement,montant,mode,reference,notes) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$d['document_id'],$d['date_paiement']??null,$d['montant']??0,$d['mode']??'Virement',$d['reference'],$d['notes']]);
    // Update document statut if fully paid
    $docStmt = $db->prepare("SELECT total_ttc FROM documents WHERE id=?");
    $docStmt->execute([$d['document_id']]);
    $ttc = floatval($docStmt->fetchColumn());
    $paidStmt = $db->prepare("SELECT COALESCE(SUM(montant),0) FROM paiements_recus WHERE document_id=?");
    $paidStmt->execute([$d['document_id']]);
    $paid = floatval($paidStmt->fetchColumn());
    if ($paid >= $ttc - 0.01) {
        $db->prepare("UPDATE documents SET statut='Payé' WHERE id=?")->execute([$d['document_id']]);
    } elseif ($paid > 0) {
        $db->prepare("UPDATE documents SET statut='Partiellement payé' WHERE id=?")->execute([$d['document_id']]);
    }
    respond(['ok' => true]);
}

if ($action === 'paiement_delete') {
    $db->prepare("DELETE FROM paiements_recus WHERE id=?")->execute([$body['id']]);
    respond(['ok' => true]);
}

// ──────────────────────────────────────────
// DATA ROOM (admin uniquement)
// ──────────────────────────────────────────
if (str_starts_with($action, 'dr_')) {
    requireAdmin();
}

if ($action === 'dr_acces_list') {
    $rows = $db->query("
        SELECT a.*,
          (SELECT COUNT(*) FROM dataroom_logs l WHERE l.acces_id=a.id AND l.action='login') AS nb_connexions,
          (SELECT COUNT(*) FROM dataroom_logs l WHERE l.acces_id=a.id AND l.action='vue_document') AS nb_vues,
          (SELECT COUNT(*) FROM dataroom_suggestions s WHERE s.acces_id=a.id) AS nb_suggestions,
          (SELECT l.pays_ip FROM dataroom_logs l WHERE l.acces_id=a.id AND l.action='login' AND l.pays_ip<>'' ORDER BY l.id DESC LIMIT 1) AS dernier_pays,
          (SELECT l.ip FROM dataroom_logs l WHERE l.acces_id=a.id AND l.action='login' ORDER BY l.id DESC LIMIT 1) AS derniere_ip
        FROM dataroom_acces a ORDER BY a.created_at DESC")->fetchAll();
    foreach ($rows as &$r) unset($r['password_hash']);
    respond($rows);
}
if ($action === 'dr_acces_save') {
    $d = $body;
    if (empty($d['nom']) || empty($d['email'])) error400('Nom et email requis');
    if (!empty($d['id'])) {
        $sql = "UPDATE dataroom_acces SET nom=?,prenom=?,email=?,societe=?,pays=?,telephone=?,langue=?,investisseur_id=?,date_expiration=?,actif=?,notes=?";
        $params = [$d['nom'],$d['prenom']??'',$d['email'],$d['societe']??'',$d['pays']??'',$d['telephone']??'',
                   in_array($d['langue']??'fr',['fr','en'])?$d['langue']:'fr',
                   !empty($d['investisseur_id'])?intval($d['investisseur_id']):null,
                   $d['date_expiration']??null, intval($d['actif']??1), $d['notes']??''];
        if (!empty($d['password'])) {
            if (strlen($d['password']) < 8) error400('Mot de passe : 8 caractères minimum');
            $sql .= ",password_hash=?";
            $params[] = password_hash($d['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        }
        $sql .= " WHERE id=?"; $params[] = $d['id'];
        $db->prepare($sql)->execute($params);
        respond(['ok' => true]);
    } else {
        if (empty($d['password']) || strlen($d['password']) < 8) error400('Mot de passe : 8 caractères minimum');
        $exists = $db->prepare("SELECT id FROM dataroom_acces WHERE email=?");
        $exists->execute([$d['email']]);
        if ($exists->fetch()) error400('Cet email a déjà un accès Data Room');
        $db->prepare("INSERT INTO dataroom_acces (nom,prenom,email,password_hash,societe,pays,telephone,langue,investisseur_id,date_expiration,actif,notes,created_by)
                      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
           ->execute([$d['nom'],$d['prenom']??'',$d['email'],
                      password_hash($d['password'], PASSWORD_BCRYPT, ['cost' => 12]),
                      $d['societe']??'',$d['pays']??'',$d['telephone']??'',
                      in_array($d['langue']??'fr',['fr','en'])?$d['langue']:'fr',
                      !empty($d['investisseur_id'])?intval($d['investisseur_id']):null,
                      $d['date_expiration']??null, intval($d['actif']??1), $d['notes']??'', $_SESSION['user_id']]);
        respond(['ok' => true, 'id' => $db->lastInsertId()]);
    }
}
if ($action === 'dr_acces_delete') {
    $db->prepare("DELETE FROM dataroom_acces WHERE id=?")->execute([$body['id']]);
    respond(['ok' => true]);
}
if ($action === 'dr_acces_toggle') {
    $db->prepare("UPDATE dataroom_acces SET actif = NOT actif WHERE id=?")->execute([$body['id']]);
    respond(['ok' => true]);
}
if ($action === 'dr_acces_reset_nda') {
    $db->prepare("UPDATE dataroom_acces SET nda_signe=0, nda_date=NULL, nda_ip=NULL, nda_nom_signe=NULL, nda_organisation=NULL WHERE id=?")
       ->execute([$body['id']]);
    respond(['ok' => true]);
}

if ($action === 'dr_doc_list') {
    $rows = $db->query("
        SELECT d.*,
          (SELECT COUNT(*) FROM dataroom_logs l WHERE l.document_id=d.id AND l.action='vue_document') AS nb_vues,
          (SELECT COUNT(DISTINCT l.acces_id) FROM dataroom_logs l WHERE l.document_id=d.id AND l.action='vue_document') AS nb_lecteurs
        FROM dataroom_documents d ORDER BY d.ordre, d.id")->fetchAll();
    respond($rows);
}
if ($action === 'dr_doc_save') {
    $d = $body;
    if (empty($d['id'])) error400('ID requis (upload via le formulaire dédié)');
    $db->prepare("UPDATE dataroom_documents SET categorie=?,titre=?,titre_en=?,description=?,version=?,ordre=?,actif=? WHERE id=?")
       ->execute([$d['categorie']??'Autre',$d['titre'],$d['titre_en']??'',$d['description']??'',
                  $d['version']??'v1', intval($d['ordre']??0), intval($d['actif']??1), $d['id']]);
    respond(['ok' => true]);
}
if ($action === 'dr_doc_delete') {
    $stmt = $db->prepare("SELECT nom_fichier FROM dataroom_documents WHERE id=?");
    $stmt->execute([$body['id']]);
    $f = $stmt->fetchColumn();
    $db->prepare("DELETE FROM dataroom_documents WHERE id=?")->execute([$body['id']]);
    if ($f) @unlink(drFilesDir() . '/' . basename($f));
    respond(['ok' => true]);
}

if ($action === 'dr_sugg_list') {
    $rows = $db->query("
        SELECT s.*, a.nom AS acces_nom, a.prenom AS acces_prenom, a.societe, a.email,
               d.titre AS doc_titre
        FROM dataroom_suggestions s
        JOIN dataroom_acces a ON s.acces_id=a.id
        LEFT JOIN dataroom_documents d ON s.document_id=d.id
        ORDER BY FIELD(s.statut,'nouveau','lu','répondu'), s.created_at DESC")->fetchAll();
    respond($rows);
}
if ($action === 'dr_sugg_reply') {
    $db->prepare("UPDATE dataroom_suggestions SET statut=?, reponse=?, reponse_date=IF(?<>'' , CURRENT_TIMESTAMP, reponse_date) WHERE id=?")
       ->execute([$body['statut']??'lu', $body['reponse']??'', $body['reponse']??'', $body['id']]);
    respond(['ok' => true]);
}
if ($action === 'dr_sugg_delete') {
    $db->prepare("DELETE FROM dataroom_suggestions WHERE id=?")->execute([$body['id']]);
    respond(['ok' => true]);
}

if ($action === 'dr_logs_list') {
    $accesId = intval($body['acces_id'] ?? 0);
    if ($accesId) {
        $stmt = $db->prepare("
            SELECT l.*, a.nom AS acces_nom, a.prenom AS acces_prenom, a.societe, d.titre AS doc_titre
            FROM dataroom_logs l
            LEFT JOIN dataroom_acces a ON l.acces_id=a.id
            LEFT JOIN dataroom_documents d ON l.document_id=d.id
            WHERE l.acces_id=? ORDER BY l.id DESC LIMIT 300");
        $stmt->execute([$accesId]);
    } else {
        $stmt = $db->query("
            SELECT l.*, a.nom AS acces_nom, a.prenom AS acces_prenom, a.societe, d.titre AS doc_titre
            FROM dataroom_logs l
            LEFT JOIN dataroom_acces a ON l.acces_id=a.id
            LEFT JOIN dataroom_documents d ON l.document_id=d.id
            ORDER BY l.id DESC LIMIT 300");
    }
    respond($stmt->fetchAll());
}

if ($action === 'dr_stats') {
    $s = [];
    $s['acces_total']   = $db->query("SELECT COUNT(*) FROM dataroom_acces")->fetchColumn();
    $s['acces_actifs']  = $db->query("SELECT COUNT(*) FROM dataroom_acces WHERE actif=1")->fetchColumn();
    $s['nda_signes']    = $db->query("SELECT COUNT(*) FROM dataroom_acces WHERE nda_signe=1")->fetchColumn();
    $s['docs_actifs']   = $db->query("SELECT COUNT(*) FROM dataroom_documents WHERE actif=1")->fetchColumn();
    $s['connexions_7j'] = $db->query("SELECT COUNT(*) FROM dataroom_logs WHERE action='login' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
    $s['vues_7j']       = $db->query("SELECT COUNT(*) FROM dataroom_logs WHERE action='vue_document' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
    $s['sugg_nouvelles']= $db->query("SELECT COUNT(*) FROM dataroom_suggestions WHERE statut='nouveau'")->fetchColumn();
    $s['echecs_login_7j'] = $db->query("SELECT COUNT(*) FROM dataroom_logs WHERE action='login_echec' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
    // Top documents consultés
    $s['top_docs'] = $db->query("
        SELECT d.titre, COUNT(*) AS vues, COUNT(DISTINCT l.acces_id) AS lecteurs
        FROM dataroom_logs l JOIN dataroom_documents d ON l.document_id=d.id
        WHERE l.action='vue_document'
        GROUP BY d.id ORDER BY vues DESC LIMIT 8")->fetchAll();
    // Répartition par pays de connexion
    $s['pays'] = $db->query("
        SELECT pays_ip AS pays, COUNT(*) AS n
        FROM dataroom_logs WHERE action='login' AND pays_ip<>''
        GROUP BY pays_ip ORDER BY n DESC LIMIT 10")->fetchAll();
    // Activité 14 derniers jours (logins + vues)
    $s['timeline'] = $db->query("
        SELECT DATE(created_at) AS jour,
               SUM(action='login') AS logins,
               SUM(action='vue_document') AS vues
        FROM dataroom_logs
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
        GROUP BY DATE(created_at) ORDER BY jour")->fetchAll();
    // Dernière activité par investisseur
    $s['par_investisseur'] = $db->query("
        SELECT a.id, a.nom, a.prenom, a.societe, a.pays, a.nda_signe, a.derniere_connexion,
          (SELECT COUNT(*) FROM dataroom_logs l WHERE l.acces_id=a.id AND l.action='vue_document') AS vues,
          (SELECT COUNT(*) FROM dataroom_logs l WHERE l.acces_id=a.id AND l.action='login') AS logins,
          (SELECT l.pays_ip FROM dataroom_logs l WHERE l.acces_id=a.id AND l.pays_ip<>'' ORDER BY l.id DESC LIMIT 1) AS dernier_pays,
          (SELECT l.ip FROM dataroom_logs l WHERE l.acces_id=a.id AND l.action='login' ORDER BY l.id DESC LIMIT 1) AS derniere_ip
        FROM dataroom_acces a WHERE a.actif=1
        ORDER BY a.derniere_connexion DESC")->fetchAll();
    respond($s);
}

error400('Action inconnue: ' . $action);
