<?php
/**
 * RYBSEN CRM — Upload de documents Data Room (admin uniquement)
 * PDF / JPG / PNG / WEBP (30 Mo max) · MP4 / WEBM (500 Mo max)
 * Nom de stockage aléatoire · hors racine web
 */
require_once '../config.php';
require_once '../includes/security.php';
sendSecurityHeaders(true);
secureSessionStart();
set_time_limit(0);

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
    http_response_code(403);
    die(json_encode(['error' => 'Requête non autorisée']));
}

requireLogin();
$u = currentUser();
if (($u['role'] ?? '') !== 'admin') {
    http_response_code(403);
    die(json_encode(['error' => 'Accès réservé aux administrateurs']));
}

if (empty($_FILES['fichier'])) {
    http_response_code(400);
    die(json_encode(['error' => 'Aucun fichier reçu']));
}
if ($_FILES['fichier']['error'] === UPLOAD_ERR_INI_SIZE || $_FILES['fichier']['error'] === UPLOAD_ERR_FORM_SIZE) {
    http_response_code(400);
    die(json_encode(['error' => 'Fichier trop volumineux pour la configuration actuelle du serveur (upload_max_filesize/post_max_size)']));
}
if ($_FILES['fichier']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die(json_encode(['error' => 'Échec de l\'upload (code ' . $_FILES['fichier']['error'] . ')']));
}

$f = $_FILES['fichier'];

// Whitelist stricte extension + MIME réel (documents/images : rendu canvas,
// aucun téléchargement possible ; convertir Excel/PowerPoint en PDF avant upload)
$allowed = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp',
    'mp4'  => 'video/mp4',
    'webm' => 'video/webm',
];
$videoExts = ['mp4', 'webm'];
$ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
if (!isset($allowed[$ext])) {
    http_response_code(400);
    die(json_encode(['error' => 'Format non autorisé. Acceptés : PDF, JPG, PNG, WEBP, MP4, WEBM']));
}

$maxSize = in_array($ext, $videoExts, true) ? 500 * 1024 * 1024 : 30 * 1024 * 1024;
if ($f['size'] > $maxSize) {
    http_response_code(400);
    die(json_encode(['error' => 'Fichier trop volumineux (' . round($maxSize / 1024 / 1024) . ' Mo max pour ce format)']));
}

$realMime = mime_content_type($f['tmp_name']) ?: '';
$mimeOk = $realMime === $allowed[$ext]
    // Certains encodeurs produisent un MP4 détecté comme video/quicktime par finfo
    || ($ext === 'mp4' && in_array($realMime, ['video/mp4', 'video/quicktime'], true));
if (!$mimeOk) {
    http_response_code(400);
    die(json_encode(['error' => 'Le contenu du fichier ne correspond pas à son extension']));
}

$stored = bin2hex(random_bytes(16)) . '.' . $ext;
$dest = drFilesDir() . '/' . $stored;
if (!move_uploaded_file($f['tmp_name'], $dest)) {
    http_response_code(500);
    die(json_encode(['error' => 'Impossible d\'enregistrer le fichier sur le disque']));
}
@chmod($dest, 0640);

$db = getDB();
$titre = trim($_POST['titre'] ?? '') ?: pathinfo($f['name'], PATHINFO_FILENAME);
$db->prepare("INSERT INTO dataroom_documents
    (categorie, titre, titre_en, description, nom_fichier, nom_original, mime, taille_octets, version, ordre, actif, created_by)
    VALUES (?,?,?,?,?,?,?,?,?,?,1,?)")
   ->execute([
       trim($_POST['categorie'] ?? 'Autre'),
       substr($titre, 0, 200),
       substr(trim($_POST['titre_en'] ?? ''), 0, 200),
       trim($_POST['description'] ?? ''),
       $stored,
       substr($f['name'], 0, 250),
       $allowed[$ext],
       intval($f['size']),
       substr(trim($_POST['version'] ?? 'v1'), 0, 20),
       intval($_POST['ordre'] ?? 0),
       $_SESSION['user_id'],
   ]);

echo json_encode(['ok' => true, 'id' => $db->lastInsertId()]);
