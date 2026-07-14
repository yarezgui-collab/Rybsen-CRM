<?php
/**
 * RYBSEN CRM — Couche sécurité partagée
 * Inclus par: login.php, includes/header.php, api/api.php, dataroom/_dr.php
 */

/** Headers de sécurité HTTP communs */
function sendSecurityHeaders(bool $noStore = false): void {
    if (headers_sent()) return;
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    if ($noStore) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
}

/** Démarrage de session durci (cookies HttpOnly + SameSite, nom dédié) */
function secureSessionStart(string $name = 'RYBSENSESS'): void {
    if (session_status() !== PHP_SESSION_NONE) return;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name($name);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** IP réelle du client (derrière proxy Hostinger éventuel) */
function clientIp(): string {
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = trim(explode(',', $_SERVER[$k])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
}

/**
 * Anti-bruteforce basé DB.
 * Retourne le nombre de secondes d'attente restantes (0 = autorisé).
 * Politique: 5 tentatives libres, puis verrou 15 min glissant.
 */
function throttleCheck(PDO $db, string $contexte, string $identifiant): int {
    try {
        $stmt = $db->prepare("SELECT tentatives, derniere_tentative FROM auth_throttle WHERE contexte=? AND identifiant=?");
        $stmt->execute([$contexte, $identifiant]);
        $row = $stmt->fetch();
        if (!$row) return 0;
        $elapsed = time() - strtotime($row['derniere_tentative']);
        if ($elapsed > 900) { // fenêtre expirée → reset
            $db->prepare("DELETE FROM auth_throttle WHERE contexte=? AND identifiant=?")->execute([$contexte, $identifiant]);
            return 0;
        }
        if (intval($row['tentatives']) >= 5) return 900 - $elapsed;
        return 0;
    } catch (Exception $e) { return 0; } // table absente → ne pas bloquer
}

function throttleFail(PDO $db, string $contexte, string $identifiant): void {
    try {
        $db->prepare("INSERT INTO auth_throttle (contexte, identifiant) VALUES (?,?)
                      ON DUPLICATE KEY UPDATE tentatives = tentatives + 1, derniere_tentative = CURRENT_TIMESTAMP")
           ->execute([$contexte, $identifiant]);
    } catch (Exception $e) {}
}

function throttleReset(PDO $db, string $contexte, string $identifiant): void {
    try {
        $db->prepare("DELETE FROM auth_throttle WHERE contexte=? AND identifiant=?")->execute([$contexte, $identifiant]);
    } catch (Exception $e) {}
}

/** Répertoire de stockage des fichiers Data Room — HORS racine web si possible */
function drFilesDir(): string {
    $docroot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__), '/');
    $outside = dirname($docroot) . '/dataroom_files';
    if (is_dir($outside) || @mkdir($outside, 0750, true)) return $outside;
    // Fallback: dossier interne protégé par .htaccess (deny all)
    $inside = dirname(__DIR__) . '/dataroom/files';
    if (!is_dir($inside)) {
        @mkdir($inside, 0750, true);
        @file_put_contents($inside . '/.htaccess', "Require all denied\n");
    }
    return $inside;
}

/** Géolocalisation IP (ipwho.is, gratuit HTTPS). Échec silencieux → ['','']. */
function geoLookup(string $ip): array {
    if ($ip === '0.0.0.0' || $ip === '127.0.0.1' || str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.')) {
        return ['', ''];
    }
    try {
        $ctx = stream_context_create(['http' => ['timeout' => 2]]);
        $raw = @file_get_contents('https://ipwho.is/' . urlencode($ip) . '?fields=success,country,city', false, $ctx);
        if ($raw) {
            $j = json_decode($raw, true);
            if (!empty($j['success'])) return [$j['country'] ?? '', $j['city'] ?? ''];
        }
    } catch (Exception $e) {}
    return ['', ''];
}
