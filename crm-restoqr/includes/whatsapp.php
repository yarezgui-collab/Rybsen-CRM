<?php
/**
 * whatsapp.php — Envoi de notifications WhatsApp via API officielle.
 *
 * Implémentation par défaut : Twilio WhatsApp Business API.
 * Pour changer de provider (360dialog, Wati, Meta direct), il suffit de
 * réécrire le corps de send_whatsapp_message() — le reste de l'app ne
 * dépend que de cette fonction.
 *
 * IMPORTANT : configure ces constantes dans config.php avant la mise en prod.
 * En attendant une vraie config, les envois échouent silencieusement et sont
 * loggés — la commande reste fonctionnelle côté dashboard dans tous les cas.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// À définir dans config.php :
// define('TWILIO_ACCOUNT_SID', 'ACxxxxxxxx');
// define('TWILIO_AUTH_TOKEN', 'xxxxxxxx');
// define('TWILIO_WHATSAPP_FROM', 'whatsapp:+14155238886'); // numéro Twilio sandbox ou approuvé

function send_whatsapp_message(string $toNumber, string $message): array {
    if (!function_exists('curl_init')) {
        return ['success' => false, 'error' => 'Extension PHP cURL absente sur ce serveur. Activez-la dans hPanel → PHP Configuration.'];
    }
    if (!defined('TWILIO_ACCOUNT_SID') || !defined('TWILIO_AUTH_TOKEN') || !defined('TWILIO_WHATSAPP_FROM')) {
        return ['success' => false, 'error' => 'Configuration WhatsApp (Twilio) absente dans config.php.'];
    }

    $toNumber = normalize_phone($toNumber);
    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . TWILIO_ACCOUNT_SID . '/Messages.json';

    $from = TWILIO_WHATSAPP_FROM;
    if (!str_starts_with($from, 'whatsapp:')) {
        $from = 'whatsapp:' . $from;
    }
    $payload = [
        'From' => $from,
        'To'   => 'whatsapp:' . $toNumber,
        'Body' => $message,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($payload),
        CURLOPT_USERPWD        => TWILIO_ACCOUNT_SID . ':' . TWILIO_AUTH_TOKEN,
        CURLOPT_TIMEOUT        => 8, // ne jamais bloquer la requête commande trop longtemps
        CURLOPT_CONNECTTIMEOUT => 4,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['success' => false, 'error' => 'Erreur réseau: ' . $curlError];
    }

    $decoded = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'message_id' => $decoded['sid'] ?? null];
    }

    return ['success' => false, 'error' => $decoded['message'] ?? ('HTTP ' . $httpCode)];
}

function normalize_phone(string $number): string {
    $number = preg_replace('/[^0-9+]/', '', $number);
    if (!str_starts_with($number, '+')) {
        // Par défaut on suppose la Tunisie si aucun indicatif n'est fourni
        $number = '+216' . ltrim($number, '0');
    }
    return $number;
}

/**
 * Construit le message de notification de nouvelle commande et l'envoie,
 * en journalisant systématiquement le résultat (succès ou échec) en base.
 */
function send_whatsapp_new_order(
    int $commandeId,
    string $serveurWhatsapp,
    string $code,
    int $tableNumero,
    array $items,
    float $total,
    ?string $noteGlobale
): void {
    $lignes = array_map(
        fn($it) => "• {$it['quantite']}× {$it['nom']}" . ($it['note'] ? " ({$it['note']})" : ''),
        $items
    );
    $message = "🔔 Nouvelle commande #{$code}\n"
        . "Table {$tableNumero}\n\n"
        . implode("\n", $lignes) . "\n\n"
        . "Total: " . number_format($total, 3, ',', ' ') . " " . APP_DEVISE;

    if ($noteGlobale) {
        $message .= "\n📝 Note: {$noteGlobale}";
    }

    $result = send_whatsapp_message($serveurWhatsapp, $message);

    $pdo = get_db();
    $stmt = $pdo->prepare(
        "INSERT INTO whatsapp_logs (commande_id, destinataire, statut, message_id, erreur)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $commandeId,
        $serveurWhatsapp,
        $result['success'] ? 'envoye' : 'echec',
        $result['message_id'] ?? null,
        $result['error'] ?? null,
    ]);

    if ($result['success']) {
        $pdo->prepare("UPDATE commandes SET whatsapp_envoye = 1 WHERE id = ?")->execute([$commandeId]);
    } else {
        error_log("[QR-MENU] Échec envoi WhatsApp pour commande #{$code}: " . ($result['error'] ?? 'inconnu'));
    }
}
