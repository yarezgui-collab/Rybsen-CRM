<?php
/**
 * RYBSEN DATA ROOM — Streaming sécurisé des fichiers
 * Jamais d'accès direct au fichier : session + NDA vérifiés, chaque vue journalisée.
 */
require_once __DIR__ . '/_dr.php';

$db  = getDB();
$acc = drRequireNda($db);

$id = intval($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM dataroom_documents WHERE id=? AND actif=1");
$stmt->execute([$id]);
$doc = $stmt->fetch();
if (!$doc) { http_response_code(404); die('Not found'); }

// Document masqué pour cet investisseur → accès refusé + journalisé
if (drDocRestricted($db, intval($acc['id']), $id)) {
    drLog($db, intval($acc['id']), 'acces_refuse', $id, 'Document restreint');
    http_response_code(403);
    die('Forbidden');
}

// basename() → aucune traversée de répertoire possible
$path = drFilesDir() . '/' . basename($doc['nom_fichier']);
if (!is_file($path)) {
    drLog($db, intval($acc['id']), 'acces_refuse', $id, 'Fichier manquant sur le disque');
    http_response_code(404);
    die('File missing');
}

drLog($db, intval($acc['id']), 'vue_document', $id);

$mime = $doc['mime'] ?: 'application/pdf';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="document"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
readfile($path);
exit;
