<?php
/**
 * helpers.php — Fonctions utilitaires partagées par les endpoints API.
 */

function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function json_error(string $message, int $status = 400): void {
    json_response(['success' => false, 'error' => $message], $status);
}

function require_method(string $method): void {
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        json_error('Méthode non autorisée.', 405);
    }
}

function read_json_body(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        json_error('Corps de requête JSON invalide.', 400);
    }
    return $data;
}

/**
 * Génère une référence courte de commande, ex: A412.
 * Lettre = jour du mois en base26 grossière, chiffres = compteur du jour.
 */
function generate_order_code(PDO $pdo, int $restaurantId): string {
    $prefix = chr(65 + (intval(date('j')) % 26)); // A-Z selon le jour
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM commandes WHERE restaurant_id = ? AND DATE(created_at) = CURDATE()"
    );
    $stmt->execute([$restaurantId]);
    $countToday = (int)$stmt->fetchColumn() + 1;
    return $prefix . str_pad((string)$countToday, 3, '0', STR_PAD_LEFT);
}

/**
 * Nettoyage simple anti-XSS pour les champs texte libres (notes client, etc).
 */
function clean_text(?string $value, int $maxLen = 500): ?string {
    if ($value === null) return null;
    $value = trim(strip_tags($value));
    return substr($value, 0, $maxLen);
}

/**
 * Rate limiting très simple basé sur fichier, suffisant pour un MVP sur shared hosting.
 * Limite: $max requêtes par $windowSeconds pour une clé donnée (ex: IP + table).
 */
function rate_limit(string $key, int $max = 5, int $windowSeconds = 30): bool {
    $dir = sys_get_temp_dir() . '/qrmenu_rl';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    $file = $dir . '/' . md5($key) . '.json';

    $now = time();
    $entries = [];
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $entries = json_decode($content, true) ?: [];
    }
    // Garde seulement les entrées dans la fenêtre de temps
    $entries = array_filter($entries, fn($ts) => $ts > $now - $windowSeconds);

    if (count($entries) >= $max) {
        return false; // limite atteinte
    }
    $entries[] = $now;
    file_put_contents($file, json_encode($entries));
    return true;
}

function client_ip(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
