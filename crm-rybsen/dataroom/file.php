<?php
/**
 * RYBSEN DATA ROOM — Streaming sécurisé des fichiers
 * Jamais d'accès direct au fichier : session + NDA vérifiés, chaque vue journalisée.
 * Supporte les requêtes Range (HTTP 206) — requis pour la lecture/l'avance rapide vidéo.
 */
require_once __DIR__ . '/_dr.php';

set_time_limit(0);
if (function_exists('apache_setenv')) { @apache_setenv('no-gzip', 1); }
@ini_set('zlib.output_compression', 'Off');
while (ob_get_level() > 0) { @ob_end_clean(); }

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

$mime = $doc['mime'] ?: 'application/octet-stream';
$size = filesize($path);
$start = 0;
$end = $size - 1;

header('Accept-Ranges: bytes');
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="document"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow');

$isRange = false;
if (!empty($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
    $isRange = true;
    $start = ($m[1] === '') ? 0 : intval($m[1]);
    $end   = ($m[2] === '') ? $size - 1 : intval($m[2]);
    $end   = min($end, $size - 1);
    if ($start > $end || $start >= $size) {
        http_response_code(416);
        header('Content-Range: bytes */' . $size);
        exit;
    }
    http_response_code(206);
    header("Content-Range: bytes $start-$end/$size");
}

$length = $end - $start + 1;
header('Content-Length: ' . $length);

// La consultation est journalisée par viewer.php (point d'entrée unique de
// la vue) — pas ici, pour éviter les doublons avec les requêtes Range émises
// en rafale par les lecteurs vidéo.

$fh = fopen($path, 'rb');
fseek($fh, $start);
$bytesLeft = $length;
while ($bytesLeft > 0 && !feof($fh)) {
    $chunk = min(1024 * 1024, $bytesLeft);
    echo fread($fh, $chunk);
    $bytesLeft -= $chunk;
    if (ob_get_level() > 0) @ob_flush();
    @flush();
}
fclose($fh);
exit;
