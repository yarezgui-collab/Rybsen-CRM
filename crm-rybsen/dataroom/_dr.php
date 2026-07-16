<?php
/**
 * RYBSEN DATA ROOM — Bootstrap partagé
 * Session dédiée (séparée du CRM), i18n FR/EN, audit log, garde d'accès.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/security.php';

sendSecurityHeaders(true);
secureSessionStart('RYBSENDR');   // session distincte du CRM interne

// ── Langue FR / EN ──────────────────────────────────────────
if (isset($_GET['lang']) && in_array($_GET['lang'], ['fr', 'en'], true)) {
    $_SESSION['dr_lang'] = $_GET['lang'];
}
$DR_LANG = $_SESSION['dr_lang'] ?? 'fr';

$DR_T = [
'fr' => [
  'title'          => 'Data Room Investisseurs',
  'subtitle'       => 'Accès confidentiel — sur invitation uniquement',
  'email'          => 'Adresse email',
  'password'       => 'Mot de passe',
  'login'          => 'Accéder à la Data Room',
  'bad_creds'      => 'Identifiants incorrects.',
  'throttled'      => 'Trop de tentatives. Réessayez dans %d min.',
  'expired'        => 'Votre accès a expiré. Contactez RYBSEN pour le renouveler.',
  'nda_title'      => 'Accord de confidentialité (NDA)',
  'nda_intro'      => 'Avant d\'accéder aux documents, vous devez lire et signer électroniquement l\'accord de confidentialité ci-dessous. Faites défiler jusqu\'en bas pour activer la signature.',
  'nda_scroll'     => 'Faites défiler le document jusqu\'en bas pour signer',
  'nda_fullname'   => 'Nom complet (signature)',
  'nda_org'        => 'Organisation',
  'nda_check'      => 'J\'ai lu et j\'accepte les termes de cet accord de confidentialité. Ma signature électronique a la même valeur juridique qu\'une signature manuscrite (Art. 11).',
  'nda_sign'       => 'Signer et accéder à la Data Room',
  'nda_required'   => 'Veuillez remplir tous les champs et cocher la case.',
  'room_title'     => 'Data Room',
  'welcome'        => 'Bienvenue',
  'nda_signed_on'  => 'NDA signé le',
  'expires_on'     => 'Accès valable jusqu\'au',
  'docs'           => 'documents',
  'view'           => 'Consulter',
  'no_docs'        => 'Aucun document disponible pour le moment.',
  'suggest_title'  => 'Question / Suggestion',
  'suggest_doc'    => 'À propos du document (optionnel)',
  'suggest_general'=> 'Question générale',
  'suggest_ph'     => 'Votre question ou suggestion pour l\'équipe RYBSEN…',
  'suggest_send'   => 'Envoyer',
  'suggest_ok'     => 'Merci — votre message a bien été transmis à l\'équipe RYBSEN.',
  'suggest_empty'  => 'Le message ne peut pas être vide.',
  'logout'         => 'Déconnexion',
  'viewer_notice'  => 'Document confidentiel — consultation uniquement, téléchargement désactivé.',
  'back_room'      => '← Retour à la Data Room',
  'confidential'   => 'CONFIDENTIEL',
  'footer_notice'  => 'Tous les documents sont strictement confidentiels et couverts par le NDA signé. Chaque consultation est journalisée.',
],
'en' => [
  'title'          => 'Investor Data Room',
  'subtitle'       => 'Confidential access — by invitation only',
  'email'          => 'Email address',
  'password'       => 'Password',
  'login'          => 'Enter Data Room',
  'bad_creds'      => 'Invalid credentials.',
  'throttled'      => 'Too many attempts. Try again in %d min.',
  'expired'        => 'Your access has expired. Please contact RYBSEN to renew it.',
  'nda_title'      => 'Non-Disclosure Agreement (NDA)',
  'nda_intro'      => 'Before accessing any document, you must read and electronically sign the confidentiality agreement below. Scroll to the bottom to enable signature.',
  'nda_scroll'     => 'Scroll to the bottom of the document to sign',
  'nda_fullname'   => 'Full name (signature)',
  'nda_org'        => 'Organization',
  'nda_check'      => 'I have read and accept the terms of this Non-Disclosure Agreement. My electronic signature has the same legal validity as a handwritten signature (Art. 11).',
  'nda_sign'       => 'Sign and enter the Data Room',
  'nda_required'   => 'Please fill in all fields and tick the box.',
  'room_title'     => 'Data Room',
  'welcome'        => 'Welcome',
  'nda_signed_on'  => 'NDA signed on',
  'expires_on'     => 'Access valid until',
  'docs'           => 'documents',
  'view'           => 'View',
  'no_docs'        => 'No document available yet.',
  'suggest_title'  => 'Question / Suggestion',
  'suggest_doc'    => 'About document (optional)',
  'suggest_general'=> 'General question',
  'suggest_ph'     => 'Your question or suggestion for the RYBSEN team…',
  'suggest_send'   => 'Send',
  'suggest_ok'     => 'Thank you — your message has been sent to the RYBSEN team.',
  'suggest_empty'  => 'Message cannot be empty.',
  'logout'         => 'Log out',
  'viewer_notice'  => 'Confidential document — view only, download disabled.',
  'back_room'      => '← Back to Data Room',
  'confidential'   => 'CONFIDENTIAL',
  'footer_notice'  => 'All documents are strictly confidential and covered by the signed NDA. Every view is logged.',
],
];
function t(string $k): string { global $DR_T, $DR_LANG; return $DR_T[$DR_LANG][$k] ?? $k; }

// ── Audit log ───────────────────────────────────────────────
function drLog(PDO $db, ?int $accesId, string $action, ?int $docId = null, string $detail = ''): void {
    static $geo = null;
    $ip = clientIp();
    // Géoloc une seule fois par requête, uniquement pour les actions clés
    if ($geo === null && in_array($action, ['login', 'login_echec', 'nda_signe'], true)) {
        $geo = geoLookup($ip);
    }
    try {
        $db->prepare("INSERT INTO dataroom_logs (acces_id, document_id, action, ip, pays_ip, ville_ip, user_agent, detail)
                      VALUES (?,?,?,?,?,?,?,?)")
           ->execute([
               $accesId, $docId, $action, $ip,
               $geo[0] ?? '', $geo[1] ?? '',
               substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 490),
               substr($detail, 0, 490),
           ]);
    } catch (Exception $e) {}
}

// ── Garde d'accès ───────────────────────────────────────────
function drCurrentAccess(PDO $db): ?array {
    if (empty($_SESSION['dr_acces_id'])) return null;
    $stmt = $db->prepare("SELECT * FROM dataroom_acces WHERE id=? AND actif=1");
    $stmt->execute([$_SESSION['dr_acces_id']]);
    $a = $stmt->fetch();
    if (!$a) return null;
    if ($a['date_expiration'] && strtotime($a['date_expiration'] . ' 23:59:59') < time()) return null;
    return $a;
}

/** Exige un investisseur connecté ; sinon redirige vers le login. */
function drRequireLogin(PDO $db): array {
    $a = drCurrentAccess($db);
    if (!$a) {
        session_destroy();
        header('Location: /dataroom/');
        exit;
    }
    return $a;
}

/** Exige connecté + NDA signé ; sinon redirige vers la page NDA. */
function drRequireNda(PDO $db): array {
    $a = drRequireLogin($db);
    if (!intval($a['nda_signe'])) {
        header('Location: /dataroom/nda.php');
        exit;
    }
    return $a;
}

/** Vrai si ce document est masqué pour cet investisseur. */
function drDocRestricted(PDO $db, int $accesId, int $docId): bool {
    try {
        $stmt = $db->prepare("SELECT 1 FROM dataroom_doc_restrictions WHERE acces_id=? AND document_id=?");
        $stmt->execute([$accesId, $docId]);
        return (bool) $stmt->fetchColumn();
    } catch (Exception $e) { return false; } // table absente → ne rien masquer
}

/** Documents actifs visibles par cet investisseur (hors restrictions). */
function drVisibleDocs(PDO $db, int $accesId): array {
    try {
        $stmt = $db->prepare("
            SELECT * FROM dataroom_documents
            WHERE actif=1
              AND id NOT IN (SELECT document_id FROM dataroom_doc_restrictions WHERE acces_id=?)
            ORDER BY ordre, id");
        $stmt->execute([$accesId]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        // Fallback si la table de restrictions n'existe pas encore
        return $db->query("SELECT * FROM dataroom_documents WHERE actif=1 ORDER BY ordre, id")->fetchAll();
    }
}

function e(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
