<?php
/**
 * api/menu_manage.php — Gestion du menu (propriétaire uniquement)
 * GET  : liste toutes les catégories + articles
 * POST : actions CRUD (toggle_article, add_article, update_article, delete_article, add_category, delete_category)
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

$user = require_auth(['proprietaire']);
$pdo  = get_db();
$rid  = $user['restaurant_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare(
        "SELECT id, nom, ordre FROM categories WHERE restaurant_id = ? ORDER BY ordre ASC, id ASC"
    );
    $stmt->execute([$rid]);
    $cats = $stmt->fetchAll();

    $stmtA = $pdo->prepare(
        "SELECT id, nom, description, prix, photo_url, disponible
         FROM articles WHERE categorie_id = ? ORDER BY id ASC"
    );

    $categories = [];
    foreach ($cats as $cat) {
        $stmtA->execute([$cat['id']]);
        $articles = $stmtA->fetchAll();
        $categories[] = [
            'id'       => (int)$cat['id'],
            'nom'      => $cat['nom'],
            'ordre'    => (int)$cat['ordre'],
            'articles' => array_map(function ($a) {
                return [
                    'id'          => (int)$a['id'],
                    'nom'         => $a['nom'],
                    'description' => $a['description'],
                    'prix'        => (float)$a['prix'],
                    'photo_url'   => $a['photo_url'],
                    'disponible'  => (bool)$a['disponible'],
                ];
            }, $articles),
        ];
    }

    json_response(['success' => true, 'categories' => $categories]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body   = read_json_body();
    $action = $body['action'] ?? '';

    switch ($action) {

        case 'toggle_article': {
            $id = isset($body['article_id']) ? (int)$body['article_id'] : 0;
            if (!$id) json_error('article_id requis.');
            // Vérifier appartenance
            $stmt = $pdo->prepare(
                "SELECT a.id FROM articles a
                 JOIN categories c ON c.id = a.categorie_id
                 WHERE a.id = ? AND c.restaurant_id = ?"
            );
            $stmt->execute([$id, $rid]);
            if (!$stmt->fetch()) json_error('Article introuvable.', 404);

            $pdo->prepare("UPDATE articles SET disponible = NOT disponible WHERE id = ?")->execute([$id]);
            json_response(['success' => true]);
        }

        case 'add_article': {
            $catId = isset($body['categorie_id']) ? (int)$body['categorie_id'] : 0;
            $nom   = clean_text($body['nom'] ?? null, 150);
            $prix  = isset($body['prix']) ? (float)$body['prix'] : null;
            if (!$catId || !$nom || $prix === null || $prix < 0) {
                json_error('categorie_id, nom et prix sont requis.');
            }
            // Vérifier que la catégorie appartient au restaurant
            $stmt = $pdo->prepare("SELECT id FROM categories WHERE id = ? AND restaurant_id = ?");
            $stmt->execute([$catId, $rid]);
            if (!$stmt->fetch()) json_error('Catégorie introuvable.', 404);

            $desc     = clean_text($body['description'] ?? null, 500);
            $photoUrl = clean_text($body['photo_url'] ?? null, 500);

            $pdo->prepare(
                "INSERT INTO articles (categorie_id, nom, description, prix, photo_url, disponible)
                 VALUES (?, ?, ?, ?, ?, 1)"
            )->execute([$catId, $nom, $desc, $prix, $photoUrl]);

            json_response(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
        }

        case 'update_article': {
            $id   = isset($body['article_id']) ? (int)$body['article_id'] : 0;
            $nom  = clean_text($body['nom'] ?? null, 150);
            $prix = isset($body['prix']) ? (float)$body['prix'] : null;
            if (!$id || !$nom || $prix === null || $prix < 0) {
                json_error('article_id, nom et prix sont requis.');
            }
            // Vérifier appartenance
            $stmt = $pdo->prepare(
                "SELECT a.id FROM articles a
                 JOIN categories c ON c.id = a.categorie_id
                 WHERE a.id = ? AND c.restaurant_id = ?"
            );
            $stmt->execute([$id, $rid]);
            if (!$stmt->fetch()) json_error('Article introuvable.', 404);

            $desc     = clean_text($body['description'] ?? null, 500);
            $photoUrl = clean_text($body['photo_url'] ?? null, 500);

            $pdo->prepare(
                "UPDATE articles SET nom = ?, prix = ?, description = ?, photo_url = ? WHERE id = ?"
            )->execute([$nom, $prix, $desc, $photoUrl, $id]);

            json_response(['success' => true]);
        }

        case 'delete_article': {
            $id = isset($body['article_id']) ? (int)$body['article_id'] : 0;
            if (!$id) json_error('article_id requis.');
            $stmt = $pdo->prepare(
                "SELECT a.id FROM articles a
                 JOIN categories c ON c.id = a.categorie_id
                 WHERE a.id = ? AND c.restaurant_id = ?"
            );
            $stmt->execute([$id, $rid]);
            if (!$stmt->fetch()) json_error('Article introuvable.', 404);

            $pdo->prepare("DELETE FROM articles WHERE id = ?")->execute([$id]);
            json_response(['success' => true]);
        }

        case 'add_category': {
            $nom = clean_text($body['nom'] ?? null, 100);
            if (!$nom) json_error('nom requis.');

            $stmt = $pdo->prepare("SELECT COALESCE(MAX(ordre),0)+1 FROM categories WHERE restaurant_id = ?");
            $stmt->execute([$rid]);
            $ordre = (int)$stmt->fetchColumn();

            $pdo->prepare(
                "INSERT INTO categories (restaurant_id, nom, ordre) VALUES (?, ?, ?)"
            )->execute([$rid, $nom, $ordre]);

            json_response(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
        }

        case 'delete_category': {
            $id = isset($body['categorie_id']) ? (int)$body['categorie_id'] : 0;
            if (!$id) json_error('categorie_id requis.');

            $stmt = $pdo->prepare("SELECT id FROM categories WHERE id = ? AND restaurant_id = ?");
            $stmt->execute([$id, $rid]);
            if (!$stmt->fetch()) json_error('Catégorie introuvable.', 404);

            // Vérifier que la catégorie est vide
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM articles WHERE categorie_id = ?");
            $stmt->execute([$id]);
            if ((int)$stmt->fetchColumn() > 0) {
                json_error('Cette catégorie contient des articles. Supprimez-les d\'abord.');
            }

            $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
            json_response(['success' => true]);
        }

        default:
            json_error('Action inconnue.', 400);
    }
}

json_error('Méthode non autorisée.', 405);
