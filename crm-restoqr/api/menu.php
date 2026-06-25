<?php
/**
 * api/menu.php — GET ?t={qr_token} => menu complet + infos table
 *
 * Accès public (pas d'authentification) : la sécurité repose sur le fait
 * que qr_token est un identifiant aléatoire de 32 caractères, non énumérable.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

require_method('GET');

$qrToken = trim($_GET['t'] ?? '');
if (!preg_match('/^[a-f0-9]{32}$/', $qrToken)) {
    json_error('Code table invalide.', 404);
}

$pdo = get_db();

$stmt = $pdo->prepare(
    "SELECT t.id as table_id, t.numero, t.zone_id, r.id as restaurant_id, r.nom as restaurant_nom, r.devise
     FROM tables_restaurant t
     JOIN restaurant r ON r.id = t.restaurant_id
     WHERE t.qr_token = ? LIMIT 1"
);
$stmt->execute([$qrToken]);
$table = $stmt->fetch();

if (!$table) {
    json_error('Table introuvable. Vérifiez le QR code scanné.', 404);
}

$stmt = $pdo->prepare(
    "SELECT id, nom, ordre FROM categories WHERE restaurant_id = ? ORDER BY ordre ASC, nom ASC"
);
$stmt->execute([$table['restaurant_id']]);
$categories = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT id, categorie_id, nom, description, prix, photo_url, disponible
     FROM articles WHERE restaurant_id = ? ORDER BY ordre ASC, nom ASC"
);
$stmt->execute([$table['restaurant_id']]);
$articles = $stmt->fetchAll();

$menu = [];
foreach ($categories as $cat) {
    $items = array_values(array_filter($articles, fn($a) => $a['categorie_id'] == $cat['id']));
    if (empty($items)) continue;
    $menu[] = [
        'id'    => $cat['id'],
        'nom'   => $cat['nom'],
        'items' => array_map(function ($a) {
            return [
                'id'          => (int)$a['id'],
                'nom'         => $a['nom'],
                'description' => $a['description'],
                'prix'        => (float)$a['prix'],
                'photo'       => $a['photo_url'],
                'disponible'  => (bool)$a['disponible'],
            ];
        }, $items),
    ];
}

json_response([
    'success' => true,
    'restaurant' => [
        'nom'    => $table['restaurant_nom'],
        'devise' => $table['devise'],
    ],
    'table' => [
        'numero' => (int)$table['numero'],
        'token'  => $qrToken,
    ],
    'menu' => $menu,
]);
