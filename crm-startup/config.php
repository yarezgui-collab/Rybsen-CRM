<?php
// ============================================================
// Startup.TN — Configuration Hostinger
// ============================================================

define('DB_HOST',     'localhost');
define('DB_NAME',     'u293743867_startup');
define('DB_USER',     'u293743867_startup');
define('DB_PASS',     '#b@!?qW8');
define('APP_NAME',    'Startup Tunisia');
define('APP_URL',     'https://startup.rybsen.fr');
define('ADMIN_EMAIL', 'admin@startup.rybsen.fr');

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
        die('Requête invalide. <a href="javascript:history.back()">Retour</a>');
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

// ── Email ────────────────────────────────────────────
function sendEmail($to, $subject, $htmlBody) {
    $host = parse_url(APP_URL, PHP_URL_HOST) ?: 'startup.rybsen.fr';
    $from = 'noreply@' . $host;
    $headers = implode("\r\n", [
        'From: Startup.TN <' . $from . '>',
        'Reply-To: ' . $from,
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'X-Mailer: Startup.TN',
    ]);
    $enc = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    return @mail($to, $enc, $htmlBody, $headers);
}

function _emailShell($title, $content) {
    return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
        . '<body style="margin:0;padding:0;background:#0d1117;font-family:-apple-system,\'Segoe UI\',sans-serif">'
        . '<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:40px 16px">'
        . '<table width="100%" style="max-width:480px;background:#1c2333;border:1px solid #2a3349;border-radius:12px;overflow:hidden">'
        . '<tr><td style="padding:8px 0;text-align:center;background:linear-gradient(90deg,#38bdf8,#818cf8)">'
        . '<span style="display:inline-block;padding:6px 18px;font-family:monospace;font-size:15px;font-weight:700;color:#0d1117">Startup.TN</span>'
        . '</td></tr>'
        . '<tr><td style="padding:28px 32px">'
        . '<h2 style="margin:0 0 18px;font-size:18px;font-weight:700;color:#fff">' . $title . '</h2>'
        . $content
        . '</td></tr>'
        . '<tr><td style="padding:16px 32px;border-top:1px solid #2a3349;text-align:center">'
        . '<p style="margin:0;font-size:11px;color:#4a5568">Startup.TN · Plateforme de veille financement</p>'
        . '</td></tr></table>'
        . '</td></tr></table></body></html>';
}

function sendVerificationEmail($email, $code) {
    $content = '<p style="margin:0 0 20px;font-size:14px;color:#c8d3e0;line-height:1.6">Entrez ce code pour vérifier votre adresse email :</p>'
        . '<div style="background:#0d1117;border-radius:10px;padding:22px;text-align:center;margin-bottom:20px">'
        . '<div style="font-family:monospace;font-size:42px;font-weight:700;color:#38bdf8;letter-spacing:10px">' . h($code) . '</div>'
        . '<div style="font-size:12px;color:#4a5568;margin-top:8px">Valable 30 minutes</div>'
        . '</div>'
        . '<p style="margin:0;font-size:12px;color:#4a5568;line-height:1.6">Si vous n\'avez pas créé de compte sur Startup.TN, ignorez cet email.</p>';
    return sendEmail($email, 'Votre code de vérification — Startup.TN', _emailShell('Vérifiez votre email', $content));
}

function sendResetEmail($email, $token) {
    $url = rtrim(APP_URL, '/') . '/forgot.php?token=' . urlencode($token);
    $content = '<p style="margin:0 0 20px;font-size:14px;color:#c8d3e0;line-height:1.6">Vous avez demandé une réinitialisation de votre mot de passe. Ce lien est valable <strong style="color:#38bdf8">1 heure</strong>.</p>'
        . '<div style="text-align:center;margin-bottom:20px">'
        . '<a href="' . $url . '" style="display:inline-block;padding:14px 28px;background:#38bdf8;color:#0d1117;border-radius:8px;font-weight:700;font-size:15px;text-decoration:none">Réinitialiser mon mot de passe →</a>'
        . '</div>'
        . '<p style="margin:0;font-size:12px;color:#4a5568;line-height:1.6">Si vous n\'avez pas demandé cette réinitialisation, ignorez cet email. Votre mot de passe ne sera pas modifié.</p>';
    return sendEmail($email, 'Réinitialisation de mot de passe — Startup.TN', _emailShell('Réinitialisation de mot de passe', $content));
}

function sendAdminNewUserNotif($userEmail, $startupName) {
    if (!ADMIN_EMAIL) return;
    $url = rtrim(APP_URL, '/') . '/admin.php?tab=users';
    $content = '<p style="margin:0 0 16px;font-size:14px;color:#c8d3e0">Un nouveau compte a été créé et vérifié :</p>'
        . '<div style="background:#0d1117;border-radius:8px;padding:14px;margin-bottom:20px">'
        . '<div style="font-size:14px;color:#38bdf8;font-weight:600">' . h($startupName) . '</div>'
        . '<div style="font-size:12px;color:#8b98b0;margin-top:4px">' . h($userEmail) . '</div>'
        . '</div>'
        . '<div style="text-align:center">'
        . '<a href="' . $url . '" style="display:inline-block;padding:12px 24px;background:#38bdf8;color:#0d1117;border-radius:8px;font-weight:700;text-decoration:none">Activer le compte →</a>'
        . '</div>';
    sendEmail(ADMIN_EMAIL, '[Startup.TN] Nouveau compte à activer — ' . $startupName, _emailShell('Nouveau compte startup', $content));
}

function sendSubmissionResultEmail($userEmail, $startupName, $programName, $approved) {
    $status = $approved ? '✓ Approuvée' : '✗ Refusée';
    $color  = $approved ? '#34d399' : '#f87171';
    $detail = $approved
        ? 'Le programme est maintenant visible sur la plateforme. Merci pour votre contribution !'
        : 'Votre soumission n\'a pas été retenue cette fois. Vous pouvez soumettre d\'autres programmes.';
    $url = rtrim(APP_URL, '/') . '/my_submissions.php';
    $content = '<p style="margin:0 0 14px;font-size:14px;color:#c8d3e0">Bonjour <strong style="color:#fff">' . h($startupName) . '</strong>,</p>'
        . '<p style="margin:0 0 16px;font-size:14px;color:#c8d3e0">Votre soumission <strong style="color:#fff">' . h($programName) . '</strong> a été :</p>'
        . '<div style="background:#0d1117;border-radius:8px;padding:16px;text-align:center;margin-bottom:20px">'
        . '<div style="font-size:24px;font-weight:700;color:' . $color . '">' . $status . '</div>'
        . '</div>'
        . '<p style="margin:0 0 20px;font-size:13px;color:#8b98b0;line-height:1.6">' . $detail . '</p>'
        . '<div style="text-align:center">'
        . '<a href="' . $url . '" style="color:#38bdf8;font-size:13px;text-decoration:none">Voir mes soumissions →</a>'
        . '</div>';
    $subj = $approved ? 'Soumission approuvée — ' . $programName : 'Soumission refusée — ' . $programName;
    sendEmail($userEmail, '[Startup.TN] ' . $subj, _emailShell('Résultat de votre soumission', $content));
}
