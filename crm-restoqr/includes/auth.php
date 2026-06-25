<?php
/**
 * auth.php — Authentification par token de session (serveur + propriétaire).
 *
 * Le client (consommateur) n'a AUCUNE authentification : son accès est
 * protégé uniquement par la connaissance du qr_token de la table, qui
 * n'est ni devinable ni énumérable (32 caractères aléatoires).
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

const SESSION_COOKIE_NAME = 'qrmenu_session';

function login(string $email, string $password): array {
    $pdo = get_db();
    $stmt = $pdo->prepare(
        "SELECT u.id, u.nom, u.email, u.mot_de_passe_hash, u.role, u.zone_id, u.restaurant_id, u.actif,
                z.nom as zone_nom
         FROM utilisateurs u
         LEFT JOIN zones z ON z.id = u.zone_id
         WHERE u.email = ? LIMIT 1"
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !$user['actif']) {
        // Délai constant pour limiter le timing attack / l'énumération de comptes
        usleep(300000);
        return ['success' => false, 'error' => 'Identifiants incorrects.'];
    }

    if (!password_verify($password, $user['mot_de_passe_hash'])) {
        usleep(300000);
        return ['success' => false, 'error' => 'Identifiants incorrects.'];
    }

    // Génère un token de session aléatoire (64 caractères hex)
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);

    $stmt = $pdo->prepare(
        "INSERT INTO sessions (id, utilisateur_id, user_agent, ip, expires_at)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $token,
        $user['id'],
        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250),
        client_ip(),
        $expiresAt,
    ]);

    $pdo->prepare("UPDATE utilisateurs SET derniere_connexion = NOW() WHERE id = ?")
        ->execute([$user['id']]);

    log_audit($user['id'], 'login', 'Connexion réussie');

    return [
        'success' => true,
        'token'   => $token,
        'expires_at' => $expiresAt,
        'user' => [
            'id'   => $user['id'],
            'nom'  => $user['nom'],
            'role' => $user['role'],
            'zone_id' => $user['zone_id'],
            'zone_nom' => $user['zone_nom'],
            'restaurant_id' => $user['restaurant_id'],
        ],
    ];
}

/**
 * Vérifie le token envoyé (header Authorization: Bearer xxx, ou cookie).
 * Retourne les infos utilisateur si valide, sinon null.
 */
function current_user(): ?array {
    $token = extract_token();
    if (!$token) return null;

    $pdo = get_db();
    $stmt = $pdo->prepare(
        "SELECT s.id as session_id, s.expires_at, u.id, u.nom, u.email, u.role, u.zone_id, u.restaurant_id,
                z.nom as zone_nom
         FROM sessions s
         JOIN utilisateurs u ON u.id = s.utilisateur_id
         LEFT JOIN zones z ON z.id = u.zone_id
         WHERE s.id = ? AND u.actif = 1 LIMIT 1"
    );
    $stmt->execute([$token]);
    $row = $stmt->fetch();

    if (!$row) return null;
    if (strtotime($row['expires_at']) < time()) {
        // Session expirée : on la supprime
        $pdo->prepare("DELETE FROM sessions WHERE id = ?")->execute([$token]);
        return null;
    }
    return $row;
}

function extract_token(): ?string {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (preg_match('/Bearer\s+(.+)/i', $authHeader, $m)) {
        return trim($m[1]);
    }
    if (isset($_COOKIE[SESSION_COOKIE_NAME])) {
        return $_COOKIE[SESSION_COOKIE_NAME];
    }
    return null;
}

/**
 * À appeler en tête des endpoints qui exigent une authentification.
 * $allowedRoles ex: ['serveur'], ['proprietaire'], ['serveur','proprietaire']
 */
function require_auth(array $allowedRoles = ['serveur', 'proprietaire']): array {
    $user = current_user();
    if (!$user) {
        json_error('Session invalide ou expirée. Veuillez vous reconnecter.', 401);
    }
    if (!in_array($user['role'], $allowedRoles, true)) {
        json_error('Accès refusé pour ce rôle.', 403);
    }
    return $user;
}

function logout(string $token): void {
    $pdo = get_db();
    $pdo->prepare("DELETE FROM sessions WHERE id = ?")->execute([$token]);
}

function log_audit(?int $utilisateurId, string $action, string $details = ''): void {
    $pdo = get_db();
    $stmt = $pdo->prepare(
        "INSERT INTO audit_log (utilisateur_id, action, details, ip) VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$utilisateurId, $action, $details, client_ip()]);
}
