<?php
// Copier ce fichier en config.php et renseigner vos valeurs Hostinger
define('DB_HOST', 'localhost');
define('DB_NAME', 'votre_base_de_données');
define('DB_USER', 'votre_utilisateur_db');
define('DB_PASS', 'votre_mot_de_passe');
define('DB_CHARSET', 'utf8mb4');

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['error' => 'Connexion échouée: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

function requireLogin() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['user_id'])) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            http_response_code(401);
            die(json_encode(['error' => 'Non authentifié']));
        }
        header('Location: /login.php');
        exit;
    }
}

function currentUser() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return $_SESSION['user'] ?? null;
}
?>
