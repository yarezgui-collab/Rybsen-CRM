<?php
/**
 * api/commande_create.php — POST { qr_token, items: [{article_id, quantite, note}], note_globale }
 * => crée la commande, l'assigne au serveur de la zone, déclenche l'envoi WhatsApp.
 *
 * Accès public (client), protégé par qr_token + rate limiting.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/whatsapp.php';

require_method('POST');

$body = read_json_body();
$qrToken = trim($body['qr_token'] ?? '');
$items = $body['items'] ?? [];
$noteGlobale = clean_text($body['note_globale'] ?? null, 300);

if (!preg_match('/^[a-f0-9]{32}$/', $qrToken)) {
    json_error('Code table invalide.', 404);
}
if (!is_array($items) || count($items) === 0) {
    json_error('La commande ne contient aucun article.');
}
if (count($items) > 40) {
    json_error('Commande trop volumineuse.');
}

if (!rate_limit('order_' . $qrToken, 6, 300)) {
    json_error('Trop de commandes envoyées récemment depuis cette table. Patientez un instant.', 429);
}

$pdo = get_db();

$stmt = $pdo->prepare(
    "SELECT t.id as table_id, t.numero, t.zone_id, r.id as restaurant_id
     FROM tables_restaurant t
     JOIN restaurant r ON r.id = t.restaurant_id
     WHERE t.qr_token = ? LIMIT 1"
);
$stmt->execute([$qrToken]);
$table = $stmt->fetch();

if (!$table) {
    json_error('Table introuvable.', 404);
}

$serveurId = null;
$serveurWhatsapp = null;
if ($table['zone_id']) {
    $stmt = $pdo->prepare(
        "SELECT id, whatsapp_number FROM utilisateurs
         WHERE restaurant_id = ? AND zone_id = ? AND role = 'serveur' AND actif = 1
         ORDER BY derniere_connexion DESC LIMIT 1"
    );
    $stmt->execute([$table['restaurant_id'], $table['zone_id']]);
    $srv = $stmt->fetch();
    if ($srv) {
        $serveurId = $srv['id'];
        $serveurWhatsapp = $srv['whatsapp_number'];
    }
}

$validatedItems = [];
$total = 0.0;

$stmtArticle = $pdo->prepare(
    "SELECT id, nom, prix, disponible FROM articles WHERE id = ? AND restaurant_id = ?"
);

foreach ($items as $it) {
    $articleId = (int)($it['article_id'] ?? 0);
    $quantite = (int)($it['quantite'] ?? 0);
    $note = clean_text($it['note'] ?? null, 200);

    if ($articleId <= 0 || $quantite <= 0 || $quantite > 20) {
        json_error('Article ou quantité invalide.');
    }

    $stmtArticle->execute([$articleId, $table['restaurant_id']]);
    $article = $stmtArticle->fetch();

    if (!$article) {
        json_error('Un article de la commande est introuvable.');
    }
    if (!$article['disponible']) {
        json_error('"' . $article['nom'] . '" n\'est plus disponible. Merci de retirer cet article.');
    }

    $prix = (float)$article['prix'];
    $total += $prix * $quantite;

    $validatedItems[] = [
        'article_id' => $articleId,
        'nom'        => $article['nom'],
        'prix'       => $prix,
        'quantite'   => $quantite,
        'note'       => $note,
    ];
}

try {
    $pdo->beginTransaction();

    $code = generate_order_code($pdo, $table['restaurant_id']);

    $stmt = $pdo->prepare(
        "INSERT INTO commandes (restaurant_id, table_id, serveur_id, code, statut, total, note_client)
         VALUES (?, ?, ?, ?, 'nouvelle', ?, ?)"
    );
    $stmt->execute([
        $table['restaurant_id'],
        $table['table_id'],
        $serveurId,
        $code,
        $total,
        $noteGlobale,
    ]);
    $commandeId = (int)$pdo->lastInsertId();

    $stmtItem = $pdo->prepare(
        "INSERT INTO commande_items (commande_id, article_id, nom_article, prix_unitaire, quantite, note)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    foreach ($validatedItems as $vi) {
        $stmtItem->execute([
            $commandeId, $vi['article_id'], $vi['nom'], $vi['prix'], $vi['quantite'], $vi['note'],
        ]);
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('[QR-MENU] Erreur création commande: ' . $e->getMessage());
    json_error('Impossible d\'enregistrer la commande. Réessayez.', 500);
}

if ($serveurWhatsapp) {
    try {
        send_whatsapp_new_order($commandeId, $serveurWhatsapp, $code, $table['numero'], $validatedItems, $total, $noteGlobale);
    } catch (Throwable $e) {
        // Best-effort : la commande est déjà en base, on ne bloque jamais le client pour une erreur WhatsApp
        error_log('[QR-MENU] Exception WhatsApp pour commande #' . $code . ': ' . $e->getMessage());
    }
}

json_response([
    'success' => true,
    'commande' => [
        'id'    => $commandeId,
        'code'  => $code,
        'total' => $total,
        'items_count' => array_sum(array_column($validatedItems, 'quantite')),
    ],
]);
