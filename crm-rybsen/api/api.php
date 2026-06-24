<?php
require_once '../config.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? ($_GET['action'] ?? '');
$db = getDB();

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

error400('Action inconnue: ' . $action);
