<?php
/**
 * db.php — Connexion PDO partagée par toute l'application.
 */

require_once __DIR__ . '/config.php';

function get_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    try {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: application/json');
        // On ne renvoie jamais le détail de l'erreur DB au client (sécurité)
        echo json_encode(['success' => false, 'error' => 'Connexion base de données indisponible.']);
        error_log('[QR-MENU] Erreur DB: ' . $e->getMessage());
        exit;
    }
}
