<?php
// ============================================================
// Configuration de connexion à la base de données
// Modifiez ces 4 valeurs avec celles de votre hPanel si besoin.
// ============================================================

// D'après hPanel : si votre base et votre PHP sont sur le même
// hébergement Hostinger (cas normal), laissez "localhost".
// Sinon, remplacez par l'adresse srv####.hostinger.com indiquée
// dans hPanel → Bases de données → MySQL.
define('DB_HOST', 'localhost');

define('DB_NAME', 'u293743867_pby');
define('DB_USER', 'u293743867_Pby');
define('DB_PASS', 'Pby56787?!');

define('DB_CHARSET', 'utf8mb4');

// ------------------------------------------------------------
// Ne pas modifier en dessous de cette ligne
// ------------------------------------------------------------
function get_pdo() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        // Le détail technique n'est jamais renvoyé au navigateur (évite de divulguer
        // des informations sur la base de données). Il est uniquement écrit dans les
        // journaux du serveur, consultables depuis hPanel si besoin de déboguer.
        error_log('Connexion DB échouée : ' . $e->getMessage());
        echo json_encode(['error' => 'Connexion base de données impossible. Vérifiez config.php.']);
        exit;
    }
}
