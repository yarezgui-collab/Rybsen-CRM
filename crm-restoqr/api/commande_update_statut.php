<?php
/**
 * api/commande_update_statut.php — POST (auth serveur ou propriétaire)
 * { commande_id, statut } => fait avancer le statut d'une commande.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_method('POST');
$user = require_auth(['serveur', 'proprietaire']);

$body = read_json_body();
$commandeId = (int)($body['commande_id'] ?? 0);
$nouveauStatut = $body['statut'] ?? '';

$transitionsValides = [
    'nouvelle' => 'en_cours',
    'en_cours' => 'prete',
    'prete'    => 'servie',
];
$statutsValides = ['en_cours', 'prete', 'servie', 'annulee'];

if ($commandeId <= 0 || !in_array($nouveauStatut, $statutsValides, true)) {
    json_error('Paramètres invalides.');
}

$pdo = get_db();

$sql = "SELECT c.id, c.statut, c.restaurant_id, t.zone_id
        FROM commandes c JOIN tables_restaurant t ON t.id = c.table_id
        WHERE c.id = ? AND c.restaurant_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$commandeId, $user['restaurant_id']]);
$commande = $stmt->fetch();

if (!$commande) {
    json_error('Commande introuvable.', 404);
}
if ($user['role'] === 'serveur' && $commande['zone_id'] != $user['zone_id']) {
    json_error('Cette commande n\'appartient pas à votre zone.', 403);
}

if ($nouveauStatut !== 'annulee' && ($transitionsValides[$commande['statut']] ?? null) !== $nouveauStatut) {
    json_error('Transition de statut invalide depuis "' . $commande['statut'] . '".');
}

$champDate = [
    'en_cours' => 'prise_en_charge_at',
    'prete'    => 'prete_at',
    'servie'   => 'servie_at',
][$nouveauStatut] ?? null;

if ($champDate) {
    $stmt = $pdo->prepare("UPDATE commandes SET statut = ?, {$champDate} = NOW() WHERE id = ?");
} else {
    $stmt = $pdo->prepare("UPDATE commandes SET statut = ? WHERE id = ?");
}
$stmt->execute([$nouveauStatut, $commandeId]);

log_audit($user['id'], 'commande_statut', "Commande #{$commandeId} -> {$nouveauStatut}");

json_response(['success' => true, 'commande_id' => $commandeId, 'statut' => $nouveauStatut]);
