<?php
/**
 * RYBSEN CRM — Upload de documents Data Room (admin uniquement)
 * PDF / JPG / PNG / WEBP · 30 Mo max · nom de stockage aléatoire · hors racine web
 */
require_once '../config.php';
require_once '../includes/security.php';
sendSecurityHeaders(true);
secureSessionStart();

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

if (empty($_FILES['fichier']) || $_FILES['fichier']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die(json_encode(['error' => 'Aucun fichier reçu (vérifiez la taille max du serveur)']));
}

$f = $_FILES['fichier'];
if ($f['size'] > 30 * 1024 * 1024) {
    http_response_code(400);
    die(json_encode(['error' => 'Fichier trop volumineux (30 Mo max)']));
}

// Whitelist stricte extension + MIME réel (le "pas de téléchargement" repose sur
// le viewer canvas → seuls PDF et images sont acceptés ; convertir les
// Excel/PowerPoint en PDF avant upload)
$allowed = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp',
];
$ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
if (!isset($allowed[$ext])) {
    http_response_code(400);
    die(json_encode(['error' => 'Format non autorisé. Acceptés : PDF, JPG, PNG, WEBP (convertir les autres formats en PDF)']));
}
$realMime = mime_content_type($f['tmp_name']) ?: '';
if ($realMime !== $allowed[$ext]) {
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
