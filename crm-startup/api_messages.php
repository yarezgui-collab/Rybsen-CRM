<?php
// API JSON pour la messagerie quasi temps réel (polling AJAX).
// Hébergement mutualisé Hostinger = pas de WebSocket serveur persistant ;
// le polling toutes les 3s donne un ressenti quasi instantané sans changer d'infra.
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    die(json_encode(['error' => 'unauthorized']));
}

$db     = getDB();
$uid    = (int)$_SESSION['fm_user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Présence : on marque l'utilisateur actif à chaque appel (throttlé à 20s
// pour éviter d'écrire en BDD à chaque poll de 3s)
if (!isset($_SESSION['last_activity_write']) || time() - $_SESSION['last_activity_write'] > 20) {
    $db->prepare('UPDATE fm_users SET last_activity = NOW() WHERE id = ?')->execute([$uid]);
    $_SESSION['last_activity_write'] = time();
}

function isOnline(?string $lastActivity): bool {
    return $lastActivity && strtotime($lastActivity) >= time() - 300; // 5 min
}

function checkCsrfApi(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die(json_encode(['error' => 'csrf']));
    }
}

switch ($action) {

    // ── Nombre de messages non lus (badge nav, pollé sur toutes les pages) ──
    case 'unread_count':
        $n = $db->prepare('SELECT COUNT(*) FROM fm_messages WHERE receiver_id = ? AND is_read = 0');
        $n->execute([$uid]);
        echo json_encode(['unread' => (int)$n->fetchColumn()]);
        break;

    // ── Liste des conversations (sidebar), pour rafraîchissement sans reload ──
    case 'conversations':
        $convs = $db->prepare("
            SELECT
                u.id, u.startup_name, u.last_activity,
                m.body AS last_msg, m.created_at AS last_time, m.sender_id AS last_sender,
                (SELECT COUNT(*) FROM fm_messages WHERE sender_id=u.id AND receiver_id=? AND is_read=0) AS unread
            FROM (
                SELECT
                    CASE WHEN sender_id=? THEN receiver_id ELSE sender_id END AS partner_id,
                    MAX(id) AS last_msg_id
                FROM fm_messages
                WHERE sender_id=? OR receiver_id=?
                GROUP BY partner_id
            ) lastm
            JOIN fm_messages m ON m.id = lastm.last_msg_id
            JOIN fm_users u ON u.id = lastm.partner_id
            WHERE u.is_active=1
            ORDER BY m.created_at DESC
        ");
        $convs->execute([$uid, $uid, $uid, $uid]);
        $out = array_map(function ($c) {
            return [
                'id' => (int)$c['id'],
                'name' => $c['startup_name'],
                'last_msg' => mb_substr($c['last_msg'], 0, 50),
                'last_time' => $c['last_time'],
                'is_mine' => (int)$c['last_sender'] === (int)$_SESSION['fm_user_id'],
                'unread' => (int)$c['unread'],
                'online' => isOnline($c['last_activity']),
            ];
        }, $convs->fetchAll());
        echo json_encode(['conversations' => $out]);
        break;

    // ── Nouveaux messages d'une conversation depuis un ID donné ──
    case 'poll':
        $to    = (int)($_GET['to'] ?? 0);
        $since = (int)($_GET['since'] ?? 0);
        if (!$to) { echo json_encode(['messages' => [], 'online' => false, 'typing' => false]); break; }

        // Marquer comme lus les messages reçus dans cette conversation
        $db->prepare('UPDATE fm_messages SET is_read=1 WHERE receiver_id=? AND sender_id=?')->execute([$uid, $to]);

        $msgs = $db->prepare('SELECT id, sender_id, body, created_at, is_read FROM fm_messages
            WHERE id > ? AND ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?))
            ORDER BY id ASC');
        $msgs->execute([$since, $uid, $to, $to, $uid]);

        $partner = $db->prepare('SELECT last_activity FROM fm_users WHERE id=? LIMIT 1');
        $partner->execute([$to]);
        $partner = $partner->fetch();

        $typing = $db->prepare('SELECT updated_at FROM fm_typing WHERE user_id=? AND receiver_id=? AND updated_at >= ?');
        $typing->execute([$to, $uid, date('Y-m-d H:i:s', time() - 4)]);

        // Statut de lecture du dernier message que j'ai envoyé (pour faire évoluer ✓ → ✓✓ sans reload)
        $lastSent = $db->prepare('SELECT is_read FROM fm_messages WHERE sender_id=? AND receiver_id=? ORDER BY id DESC LIMIT 1');
        $lastSent->execute([$uid, $to]);
        $lastSent = $lastSent->fetch();

        echo json_encode([
            'messages' => array_map(function ($m) use ($uid) {
                return [
                    'id' => (int)$m['id'],
                    'sender_id' => (int)$m['sender_id'],
                    'is_sent' => (int)$m['sender_id'] === $uid,
                    'body' => nl2br(h($m['body'])),
                    'time' => date('H:i', strtotime($m['created_at'])),
                    'date' => date('d/m/Y', strtotime($m['created_at'])),
                    'is_read' => (bool)$m['is_read'],
                ];
            }, $msgs->fetchAll()),
            'online' => isOnline($partner['last_activity'] ?? null),
            'typing' => (bool)$typing->fetch(),
            'my_last_sent_read' => $lastSent ? (bool)$lastSent['is_read'] : null,
        ]);
        break;

    // ── Envoyer un message (AJAX, avec repli formulaire classique si JS coupé) ──
    case 'send':
        checkCsrfApi();
        $to   = (int)($_POST['receiver_id'] ?? 0);
        $body = trim($_POST['body'] ?? '');
        if (!$to || $to === $uid || $body === '') {
            http_response_code(400);
            die(json_encode(['error' => 'invalid']));
        }
        $chk = $db->prepare('SELECT id FROM fm_users WHERE id=? AND is_active=1 LIMIT 1');
        $chk->execute([$to]);
        if (!$chk->fetch()) {
            http_response_code(404);
            die(json_encode(['error' => 'recipient_not_found']));
        }
        $db->prepare('INSERT INTO fm_messages (sender_id, receiver_id, body) VALUES (?,?,?)')->execute([$uid, $to, $body]);
        $mid = (int)$db->lastInsertId();
        auditLog('send_message', 'message', $to);
        // On efface l'indicateur de saisie dès l'envoi
        $db->prepare('DELETE FROM fm_typing WHERE user_id=? AND receiver_id=?')->execute([$uid, $to]);
        echo json_encode(['id' => $mid, 'time' => date('H:i'), 'date' => date('d/m/Y')]);
        break;

    // ── Signaler "en train d'écrire" (ping toutes les ~2s côté client) ──
    case 'typing':
        checkCsrfApi();
        $to = (int)($_POST['receiver_id'] ?? 0);
        if ($to && $to !== $uid) {
            $db->prepare('INSERT INTO fm_typing (user_id, receiver_id, updated_at) VALUES (?,?,NOW())
                ON DUPLICATE KEY UPDATE receiver_id=VALUES(receiver_id), updated_at=VALUES(updated_at)')
               ->execute([$uid, $to]);
        }
        echo json_encode(['ok' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'unknown_action']);
}
