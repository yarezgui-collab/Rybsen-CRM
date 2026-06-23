<?php
// ============================================================
// Startup.TN — Configuration Hostinger
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'u293743867_startup');
define('DB_USER', 'u293743867_startup');
define('DB_PASS', '#b@!?qW8');
define('APP_NAME', 'Startup Tunisia');
define('APP_URL',  'https://startup.rybsen.fr');

// ============================================================
error_reporting(0);
ini_set('display_errors', 0);

// Session sécurisée avec timeout 2h
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.gc_maxlifetime', 7200);
session_start();
session_regenerate_id(false);

// Timeout session 2 heures
if (isset($_SESSION['fm_last_activity']) && (time() - $_SESSION['fm_last_activity'] > 7200)) {
    session_unset();
    session_destroy();
    session_start();
}
$_SESSION['fm_last_activity'] = time();

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST
                 . ';dbname=' . DB_NAME
                 . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, array(
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ));
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(array('error' => 'Connexion base de donnees impossible.')));
        }
    }
    return $pdo;
}

function isLoggedIn() {
    return isset($_SESSION['fm_user_id']) && isset($_SESSION['fm_role']);
}

function isAdmin() {
    return isLoggedIn() && $_SESSION['fm_role'] === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php?msg=session_expired');
        exit;
    }
}

function requireAdmin() {
    if (!isAdmin()) {
        header('Location: dashboard.php?msg=access_denied');
        exit;
    }
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function auditLog($action, $target = '', $targetId = 0, $details = '') {
    try {
        $db = getDB();
        $stmt = $db->prepare(
            'INSERT INTO fm_audit (user_id, action, target, target_id, details, ip)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute(array(
            isset($_SESSION['fm_user_id']) ? $_SESSION['fm_user_id'] : null,
            $action,
            $target  ?: null,
            $targetId ?: null,
            $details  ?: null,
            isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null,
        ));
    } catch (Exception $e) {
        // silencieux
    }
}

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function calcDeadlineType($date) {
    if (!$date) return 'open';
    $days = (strtotime($date) - time()) / 86400;
    if ($days < 0)   return 'open';
    if ($days <= 7)  return 'urgent';
    if ($days <= 30) return 'soon';
    return 'ok';
}

// ── CSRF Protection ──────────────────────────────────
function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf() {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die(json_encode(array('error' => 'Token CSRF invalide. Rechargez la page.')));
    }
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

// ── Brute Force Protection ───────────────────────────
function checkLoginAttempts($email) {
    if (!isset($_SESSION['login_attempts'])) $_SESSION['login_attempts'] = array();
    if (!isset($_SESSION['login_attempts'][$email])) {
        $_SESSION['login_attempts'][$email] = array('count' => 0, 'last' => 0);
    }
    $att = &$_SESSION['login_attempts'][$email];
    // Reset après 15 minutes
    if (time() - $att['last'] > 900) { $att['count'] = 0; }
    return $att['count'];
}

function recordLoginFailure($email) {
    if (!isset($_SESSION['login_attempts'])) $_SESSION['login_attempts'] = array();
    if (!isset($_SESSION['login_attempts'][$email])) {
        $_SESSION['login_attempts'][$email] = array('count' => 0, 'last' => 0);
    }
    $_SESSION['login_attempts'][$email]['count']++;
    $_SESSION['login_attempts'][$email]['last'] = time();
    auditLog('login_failed', 'email', 0, $email . ' (attempt ' . $_SESSION['login_attempts'][$email]['count'] . ')');
}

function resetLoginAttempts($email) {
    if (isset($_SESSION['login_attempts'][$email])) {
        unset($_SESSION['login_attempts'][$email]);
    }
}
