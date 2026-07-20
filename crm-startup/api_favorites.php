<?php
// API JSON : ajouter/retirer un programme des favoris (toggle AJAX)
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    die(json_encode(['error' => 'unauthorized']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'method_not_allowed']));
}

$token = $_POST['csrf_token'] ?? '';
if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    http_response_code(403);
    die(json_encode(['error' => 'csrf']));
}

$db  = getDB();
$uid = (int)$_SESSION['fm_user_id'];
$pid = (int)($_POST['program_id'] ?? 0);

if (!$pid) {
    http_response_code(400);
    die(json_encode(['error' => 'invalid']));
}

$chk = $db->prepare("SELECT COUNT(*) FROM fm_favorites WHERE user_id=? AND program_id=?");
$chk->execute([$uid, $pid]);

if ((int)$chk->fetchColumn() > 0) {
    $db->prepare('DELETE FROM fm_favorites WHERE user_id=? AND program_id=?')->execute([$uid, $pid]);
    echo json_encode(['favorited' => false]);
} else {
    $db->prepare('INSERT INTO fm_favorites (user_id, program_id) VALUES (?,?)')->execute([$uid, $pid]);
    echo json_encode(['favorited' => true]);
}
