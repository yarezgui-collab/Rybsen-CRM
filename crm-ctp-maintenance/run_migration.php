<?php
// run_migration.php — applique le schéma (install.sql) de façon idempotente, via HTTP,
// protégé par un jeton. Peut être appelé par GitHub Actions après le déploiement SFTP,
// ou manuellement (curl -X POST -H "X-Migration-Token: <token>" https://.../run_migration.php).
//
// install.sql est écrit pour être rejoué sans risque : CREATE TABLE IF NOT EXISTS pour les
// tables, procédure conditionnelle (information_schema) pour les colonnes, CREATE OR REPLACE
// pour les vues, seed admin conditionnel. Aucune donnée existante n'est écrasée.
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST requis']);
    exit;
}
$provided = $_SERVER['HTTP_X_MIGRATION_TOKEN'] ?? '';
if (!defined('MIGRATION_TOKEN') || MIGRATION_TOKEN === '' || !hash_equals(MIGRATION_TOKEN, $provided)) {
    http_response_code(403);
    echo json_encode(['error' => 'Jeton de migration invalide']);
    exit;
}

$sqlFile = __DIR__ . '/install.sql';
if (!is_readable($sqlFile)) {
    http_response_code(500);
    echo json_encode(['error' => 'install.sql introuvable']);
    exit;
}

// Découpe le script en requêtes en respectant les changements de DELIMITER (procédures stockées).
function splitSqlStatements(string $sql): array {
    $statements = [];
    $delimiter = ';';
    $buffer = '';
    foreach (preg_split('/\r\n|\n|\r/', $sql) as $line) {
        $trim = trim($line);
        if (preg_match('/^DELIMITER\s+(\S+)/i', $trim, $m)) { $delimiter = $m[1]; continue; }
        if ($trim === '' && $buffer === '') continue;
        if (strpos($trim, '--') === 0) continue; // ligne de commentaire pur
        $buffer .= $line . "\n";
        if ($trim !== '' && substr($trim, -strlen($delimiter)) === $delimiter) {
            $stmt = trim(substr(rtrim($buffer), 0, -strlen($delimiter)));
            if ($stmt !== '') $statements[] = $stmt;
            $buffer = '';
        }
    }
    if (trim($buffer) !== '') $statements[] = trim($buffer);
    return $statements;
}

$db = getDB();
$statements = splitSqlStatements(file_get_contents($sqlFile));
$executed = 0;
$errors = [];
foreach ($statements as $stmt) {
    try {
        $db->exec($stmt);
        $executed++;
    } catch (PDOException $e) {
        $errors[] = ['sql' => substr(preg_replace('/\s+/', ' ', $stmt), 0, 90), 'err' => $e->getMessage()];
    }
}

echo json_encode([
    'ok' => empty($errors),
    'statements' => count($statements),
    'executed' => $executed,
    'errors' => $errors,
], JSON_UNESCAPED_UNICODE);
