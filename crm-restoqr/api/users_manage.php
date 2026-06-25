<?php
/**
 * api/users_manage.php — Gestion des utilisateurs (propriétaire uniquement)
 * GET  : liste utilisateurs + zones
 * POST : add_user, toggle_user, update_user, reset_password
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

$user = require_auth(['proprietaire']);
$pdo  = get_db();
$rid  = $user['restaurant_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmtU = $pdo->prepare(
        "SELECT u.id, u.nom, u.email, u.role, u.actif, u.derniere_connexion,
                u.whatsapp_number, u.zone_id, z.nom as zone_nom
         FROM utilisateurs u
         LEFT JOIN zones z ON z.id = u.zone_id
         WHERE u.restaurant_id = ?
         ORDER BY u.role ASC, u.nom ASC"
    );
    $stmtU->execute([$rid]);
    $users = $stmtU->fetchAll();

    $stmtZ = $pdo->prepare("SELECT id, nom FROM zones WHERE restaurant_id = ? ORDER BY nom ASC");
    $stmtZ->execute([$rid]);
    $zones = $stmtZ->fetchAll();

    json_response([
        'success' => true,
        'users'   => array_map(function ($u) {
            return [
                'id'                => (int)$u['id'],
                'nom'               => $u['nom'],
                'email'             => $u['email'],
                'role'              => $u['role'],
                'actif'             => (bool)$u['actif'],
                'derniere_connexion' => $u['derniere_connexion'],
                'whatsapp_number'   => $u['whatsapp_number'],
                'zone_id'           => $u['zone_id'] ? (int)$u['zone_id'] : null,
                'zone_nom'          => $u['zone_nom'],
            ];
        }, $users),
        'zones' => array_map(function ($z) {
            return ['id' => (int)$z['id'], 'nom' => $z['nom']];
        }, $zones),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body   = read_json_body();
    $action = $body['action'] ?? '';

    switch ($action) {

        case 'add_user': {
            $nom      = clean_text($body['nom'] ?? null, 100);
            $email    = trim($body['email'] ?? '');
            $password = $body['password'] ?? '';
            $role     = $body['role'] ?? '';
            $zone_id  = isset($body['zone_id']) && $body['zone_id'] !== null ? (int)$body['zone_id'] : null;
            $wa       = clean_text($body['whatsapp_number'] ?? null, 30);

            if (!$nom || !$email || strlen($password) < 8 || !in_array($role, ['serveur', 'proprietaire'], true)) {
                json_error('Données invalides. Mot de passe : 8 caractères minimum.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                json_error('Email invalide.');
            }

            // Vérifier unicité email
            $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) json_error('Cet email est déjà utilisé.');

            // Vérifier zone
            if ($zone_id) {
                $stmt = $pdo->prepare("SELECT id FROM zones WHERE id = ? AND restaurant_id = ?");
                $stmt->execute([$zone_id, $rid]);
                if (!$stmt->fetch()) json_error('Zone introuvable.', 404);
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare(
                "INSERT INTO utilisateurs (restaurant_id, nom, email, mot_de_passe_hash, role, zone_id, whatsapp_number, actif)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1)"
            )->execute([$rid, $nom, $email, $hash, $role, $zone_id, $wa]);

            json_response(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
        }

        case 'toggle_user': {
            $id = isset($body['user_id']) ? (int)$body['user_id'] : 0;
            if (!$id) json_error('user_id requis.');

            // Ne peut pas se désactiver soi-même
            if ($id === (int)$user['id']) json_error('Vous ne pouvez pas modifier votre propre compte.');

            $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE id = ? AND restaurant_id = ?");
            $stmt->execute([$id, $rid]);
            if (!$stmt->fetch()) json_error('Utilisateur introuvable.', 404);

            $pdo->prepare("UPDATE utilisateurs SET actif = NOT actif WHERE id = ?")->execute([$id]);
            json_response(['success' => true]);
        }

        case 'update_user': {
            $id      = isset($body['user_id']) ? (int)$body['user_id'] : 0;
            $nom     = clean_text($body['nom'] ?? null, 100);
            $zone_id = isset($body['zone_id']) && $body['zone_id'] !== null ? (int)$body['zone_id'] : null;
            $wa      = clean_text($body['whatsapp_number'] ?? null, 30);

            if (!$id || !$nom) json_error('user_id et nom sont requis.');

            $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE id = ? AND restaurant_id = ?");
            $stmt->execute([$id, $rid]);
            if (!$stmt->fetch()) json_error('Utilisateur introuvable.', 404);

            if ($zone_id) {
                $stmt = $pdo->prepare("SELECT id FROM zones WHERE id = ? AND restaurant_id = ?");
                $stmt->execute([$zone_id, $rid]);
                if (!$stmt->fetch()) json_error('Zone introuvable.', 404);
            }

            $pdo->prepare(
                "UPDATE utilisateurs SET nom = ?, zone_id = ?, whatsapp_number = ? WHERE id = ?"
            )->execute([$nom, $zone_id, $wa, $id]);

            json_response(['success' => true]);
        }

        case 'reset_password': {
            $id       = isset($body['user_id']) ? (int)$body['user_id'] : 0;
            $password = $body['password'] ?? '';

            if (!$id || strlen($password) < 8) {
                json_error('user_id requis et mot de passe de 8 caractères minimum.');
            }

            $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE id = ? AND restaurant_id = ?");
            $stmt->execute([$id, $rid]);
            if (!$stmt->fetch()) json_error('Utilisateur introuvable.', 404);

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE utilisateurs SET mot_de_passe_hash = ? WHERE id = ?")->execute([$hash, $id]);
            json_response(['success' => true]);
        }

        default:
            json_error('Action inconnue.', 400);
    }
}

json_error('Méthode non autorisée.', 405);
