<?php
/**
 * api/tables_manage.php — Gestion des tables et zones (propriétaire uniquement)
 * GET  : liste tables + zones
 * POST : add_table, delete_table, add_zone
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

$user = require_auth(['proprietaire']);
$pdo  = get_db();
$rid  = $user['restaurant_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmtT = $pdo->prepare(
        "SELECT t.id, t.numero, t.qr_token, t.statut, t.zone_id, z.nom as zone_nom
         FROM tables_restaurant t
         LEFT JOIN zones z ON z.id = t.zone_id
         WHERE t.restaurant_id = ?
         ORDER BY t.zone_id ASC, t.numero ASC"
    );
    $stmtT->execute([$rid]);
    $tables = $stmtT->fetchAll();

    $stmtZ = $pdo->prepare("SELECT id, nom FROM zones WHERE restaurant_id = ? ORDER BY nom ASC");
    $stmtZ->execute([$rid]);
    $zones = $stmtZ->fetchAll();

    json_response([
        'success' => true,
        'tables'  => array_map(function ($t) {
            return [
                'id'       => (int)$t['id'],
                'numero'   => (int)$t['numero'],
                'qr_token' => $t['qr_token'],
                'statut'   => $t['statut'],
                'zone_id'  => $t['zone_id'] ? (int)$t['zone_id'] : null,
                'zone_nom' => $t['zone_nom'],
            ];
        }, $tables),
        'zones' => array_map(function ($z) {
            return ['id' => (int)$z['id'], 'nom' => $z['nom']];
        }, $zones),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body   = read_json_body();
    $action = $body['action'] ?? '';

    switch ($action) {

        case 'add_table': {
            $numero  = isset($body['numero']) ? (int)$body['numero'] : 0;
            $zone_id = isset($body['zone_id']) && $body['zone_id'] !== null ? (int)$body['zone_id'] : null;
            if ($numero < 1) json_error('Numéro de table invalide.');

            // Vérifier que la zone appartient au restaurant si fournie
            if ($zone_id) {
                $stmt = $pdo->prepare("SELECT id FROM zones WHERE id = ? AND restaurant_id = ?");
                $stmt->execute([$zone_id, $rid]);
                if (!$stmt->fetch()) json_error('Zone introuvable.', 404);
            }

            // Vérifier unicité du numéro dans le restaurant
            $stmt = $pdo->prepare("SELECT id FROM tables_restaurant WHERE restaurant_id = ? AND numero = ?");
            $stmt->execute([$rid, $numero]);
            if ($stmt->fetch()) json_error('Ce numéro de table existe déjà.');

            $token = bin2hex(random_bytes(16));
            $pdo->prepare(
                "INSERT INTO tables_restaurant (restaurant_id, numero, qr_token, statut, zone_id)
                 VALUES (?, ?, ?, 'libre', ?)"
            )->execute([$rid, $numero, $token, $zone_id]);

            json_response(['success' => true, 'id' => (int)$pdo->lastInsertId(), 'qr_token' => $token]);
        }

        case 'delete_table': {
            $id = isset($body['table_id']) ? (int)$body['table_id'] : 0;
            if (!$id) json_error('table_id requis.');

            $stmt = $pdo->prepare("SELECT id FROM tables_restaurant WHERE id = ? AND restaurant_id = ?");
            $stmt->execute([$id, $rid]);
            if (!$stmt->fetch()) json_error('Table introuvable.', 404);

            $pdo->prepare("DELETE FROM tables_restaurant WHERE id = ?")->execute([$id]);
            json_response(['success' => true]);
        }

        case 'add_zone': {
            $nom = clean_text($body['nom'] ?? null, 100);
            if (!$nom) json_error('nom requis.');

            $pdo->prepare(
                "INSERT INTO zones (restaurant_id, nom) VALUES (?, ?)"
            )->execute([$rid, $nom]);

            json_response(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
        }

        default:
            json_error('Action inconnue.', 400);
    }
}

json_error('Méthode non autorisée.', 405);
