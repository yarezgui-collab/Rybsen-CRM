<?php
// run_import_produits.php — importe le catalogue produits (products_seed.csv) dans la base,
// protégé par jeton. Idempotent : insert-if-missing sur code_externe (référence article).
// Ne modifie jamais un produit déjà importé (les corrections faites dans le CRM sont préservées).
// Crée aussi les catégories manquantes (une par famille) pour l'affectation aux cuisines.
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'POST requis']); exit; }
$provided = $_SERVER['HTTP_X_MIGRATION_TOKEN'] ?? '';
if (!defined('MIGRATION_TOKEN') || MIGRATION_TOKEN === '' || !hash_equals(MIGRATION_TOKEN, $provided)) {
    http_response_code(403); echo json_encode(['error' => 'Jeton invalide']); exit;
}

$csv = __DIR__ . '/products_seed.csv';
if (!is_readable($csv)) { http_response_code(500); echo json_encode(['error' => 'products_seed.csv introuvable']); exit; }

$db = getDB();
if (!$db->query("SHOW COLUMNS FROM produits LIKE 'code_externe'")->fetchColumn()) {
    http_response_code(500); echo json_encode(['error' => 'Schéma produits non à jour (code_externe manquant) — lancez la migration']); exit;
}

$fh = fopen($csv, 'r');
$header = fgetcsv($fh);
$idx = array_flip($header);
$ins = $db->prepare("INSERT INTO produits (code_externe, nom, categorie, prix_vente, unite, origine, taux_tva, actif)
    SELECT ?,?,?,?,?,?,?,1
    WHERE NOT EXISTS (SELECT 1 FROM produits WHERE code_externe = ?)");

$inserted = 0; $lignes = 0;
$db->beginTransaction();
try {
    while (($row = fgetcsv($fh)) !== false) {
        if (!array_filter($row, fn($v) => trim((string)$v) !== '')) continue;
        $code = trim($row[$idx['code_externe']] ?? '');
        if ($code === '') continue;
        $lignes++;
        $origine = (trim($row[$idx['origine']] ?? '') === 'achete') ? 'achete' : 'fabrique';
        $tva = is_numeric($row[$idx['taux_tva']] ?? '') ? (float)$row[$idx['taux_tva']] : 19;
        $prix = is_numeric($row[$idx['prix_vente']] ?? '') ? (float)$row[$idx['prix_vente']] : 0;
        $ins->execute([
            $code,
            trim($row[$idx['nom']] ?? ''),
            trim($row[$idx['categorie']] ?? '') ?: 'Non classé',
            $prix,
            trim($row[$idx['unite']] ?? '') ?: 'pièce',
            $origine,
            $tva,
            $code,
        ]);
        if ($ins->rowCount() > 0) $inserted++;
    }
    // Crée les catégories manquantes (une par famille) pour l'affectation aux cuisines
    $db->exec("INSERT INTO categories (nom)
        SELECT DISTINCT p.categorie FROM produits p
        WHERE p.categorie IS NOT NULL AND p.categorie <> ''
          AND NOT EXISTS (SELECT 1 FROM categories c WHERE c.nom = p.categorie)");
    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}
fclose($fh);
$total = (int)$db->query("SELECT COUNT(*) FROM produits WHERE code_externe IS NOT NULL")->fetchColumn();
$cats = (int)$db->query("SELECT COUNT(*) FROM categories")->fetchColumn();
echo json_encode(['ok' => true, 'lignes_csv' => $lignes, 'inseres' => $inserted, 'deja_presents' => $lignes - $inserted,
    'total_produits' => $total, 'categories' => $cats], JSON_UNESCAPED_UNICODE);
