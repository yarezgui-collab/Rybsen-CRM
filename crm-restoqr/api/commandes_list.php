<?php
/**
 * api/commandes_list.php — GET (auth serveur ou propriétaire)
 * => liste des commandes actives (+ filtrage par statut optionnel)
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_method('GET');
$user = require_auth(['serveur', 'proprietaire']);

$pdo = get_db();

$statutFilter = $_GET['statut'] ?? null;
$sinceId = isset($_GET['since_id']) ? (int)$_GET['since_id'] : 0;

$where = ["c.restaurant_id = ?"];
$params = [$user['restaurant_id']];

if ($user['role'] === 'serveur') {
    $where[] = "t.zone_id = ?";
    $params[] = $user['zone_id'];
}

if ($statutFilter && in_array($statutFilter, ['nouvelle','en_cours','prete','servie','annulee'], true)) {
    $where[] = "c.statut = ?";
    $params[] = $statutFilter;
} else {
    $where[] = "(c.statut != 'servie' OR c.servie_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE))";
    $where[] = "DATE(c.created_at) = CURDATE()";
}

if ($sinceId > 0) {
    $where[] = "c.id > ?";
    $params[] = $sinceId;
}

$sql = "SELECT c.id, c.code, c.statut, c.total, c.note_client, c.created_at,
               c.prise_en_charge_at, c.prete_at, c.servie_at,
               t.numero as table_numero
        FROM commandes c
        JOIN tables_restaurant t ON t.id = c.table_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY
          CASE c.statut WHEN 'nouvelle' THEN 0 WHEN 'en_cours' THEN 1 WHEN 'prete' THEN 2 ELSE 3 END ASC,
          c.created_at ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$commandes = $stmt->fetchAll();

if (empty($commandes)) {
    json_response(['success' => true, 'commandes' => [], 'server_time' => date('c')]);
}

$ids = array_column($commandes, 'id');
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmtItems = $pdo->prepare(
    "SELECT commande_id, nom_article, quantite, note
     FROM commande_items WHERE commande_id IN ($placeholders)"
);
$stmtItems->execute($ids);
$allItems = $stmtItems->fetchAll();

$itemsByCommande = [];
foreach ($allItems as $it) {
    $itemsByCommande[$it['commande_id']][] = [
        'nom'      => $it['nom_article'],
        'quantite' => (int)$it['quantite'],
        'note'     => $it['note'],
    ];
}

$result = array_map(function ($c) use ($itemsByCommande) {
    return [
        'id'         => (int)$c['id'],
        'code'       => $c['code'],
        'statut'     => $c['statut'],
        'total'      => (float)$c['total'],
        'note'       => $c['note_client'],
        'table'      => (int)$c['table_numero'],
        'created_at' => $c['created_at'],
        'items'      => $itemsByCommande[$c['id']] ?? [],
    ];
}, $commandes);

json_response(['success' => true, 'commandes' => $result, 'server_time' => date('c')]);
