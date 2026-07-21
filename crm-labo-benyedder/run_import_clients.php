<?php
// run_import_clients.php — importe le référentiel clients (clients_seed.csv) dans la base,
// protégé par jeton. Idempotent : insert-if-missing sur code_externe. Ne modifie jamais un
// client déjà importé (les corrections faites dans le CRM par l'admin sont préservées).
// Les clients sont créés avec le canal « point de vente » par défaut ; l'admin ajuste ensuite.
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'POST requis']); exit; }
$provided = $_SERVER['HTTP_X_MIGRATION_TOKEN'] ?? '';
if (!defined('MIGRATION_TOKEN') || MIGRATION_TOKEN === '' || !hash_equals(MIGRATION_TOKEN, $provided)) {
    http_response_code(403); echo json_encode(['error' => 'Jeton invalide']); exit;
}

$csv = __DIR__ . '/clients_seed.csv';
if (!is_readable($csv)) { http_response_code(500); echo json_encode(['error' => 'clients_seed.csv introuvable']); exit; }

$db = getDB();
// Sécurité : la colonne code_externe doit exister (schéma v2 appliqué)
if (!$db->query("SHOW COLUMNS FROM clients LIKE 'code_externe'")->fetchColumn()) {
    http_response_code(500); echo json_encode(['error' => 'Schéma clients non à jour (code_externe manquant) — lancez la migration']); exit;
}

$fh = fopen($csv, 'r');
$header = fgetcsv($fh);
$idx = array_flip($header);
$ins = $db->prepare("INSERT INTO clients (code_externe, nom, telephone, adresse, ville, matricule_fiscal,
        type_client, canal_point_vente, mode_paiement_defaut, actif)
    SELECT ?,?,?,?,?,?, 'terme', 1, 'comptant', 1
    WHERE NOT EXISTS (SELECT 1 FROM clients WHERE code_externe = ?)");

$inserted = 0; $skipped = 0; $lignes = 0;
$db->beginTransaction();
try {
    while (($row = fgetcsv($fh)) !== false) {
        if (!array_filter($row, fn($v) => trim((string)$v) !== '')) continue;
        $code = trim($row[$idx['code_externe']] ?? '');
        if ($code === '') { $skipped++; continue; }
        $lignes++;
        $ins->execute([
            $code,
            trim($row[$idx['nom']] ?? ''),
            trim($row[$idx['telephone']] ?? '') ?: null,
            trim($row[$idx['adresse']] ?? '') ?: null,
            trim($row[$idx['ville']] ?? '') ?: null,
            trim($row[$idx['matricule_fiscal']] ?? '') ?: null,
            $code,
        ]);
        if ($ins->rowCount() > 0) $inserted++;
    }
    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}
fclose($fh);
$total = $db->query("SELECT COUNT(*) FROM clients WHERE code_externe IS NOT NULL")->fetchColumn();
echo json_encode(['ok' => true, 'lignes_csv' => $lignes, 'inseres' => $inserted, 'deja_presents' => $lignes - $inserted, 'total_importes' => (int)$total], JSON_UNESCAPED_UNICODE);
