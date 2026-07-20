<?php
// Exécuté automatiquement par GitHub Actions après chaque déploiement.
// Idempotent : n'insère que ce qui n'existe pas encore (mêmes données que demo_data.sql).
// Protégé par un jeton partagé (secret GitHub BENYEDDER_MIGRATION_TOKEN), pas par une session.
require_once 'config.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Méthode non autorisée']));
}

$token = $_SERVER['HTTP_X_MIGRATION_TOKEN'] ?? '';
if (!defined('MIGRATION_TOKEN') || $token === '' || !hash_equals(MIGRATION_TOKEN, $token)) {
    http_response_code(403);
    die(json_encode(['error' => 'Jeton invalide']));
}

$db = getDB();
$log = [];

function insertIfMissing(PDO $db, string $table, string $whereCol, string $whereVal, string $insertSql, array $params, array &$log): void {
    $check = $db->prepare("SELECT 1 FROM `$table` WHERE `$whereCol` = ?");
    $check->execute([$whereVal]);
    if ($check->fetch()) {
        $log[] = "ignoré (déjà présent) : $table.$whereCol = $whereVal";
        return;
    }
    $db->prepare($insertSql)->execute($params);
    $log[] = "inséré : $table.$whereCol = $whereVal";
}

// ── Clients à terme ─────────────────────────────────
insertIfMissing($db, 'clients', 'nom', 'Café de la Gare',
    "INSERT INTO clients (nom,type_client,contact_nom,telephone,email,adresse) VALUES (?,'terme',?,?,?,?)",
    ['Café de la Gare', 'M. Trabelsi', '20 123 456', 'contact@cafedelagare.tn', 'Avenue Habib Bourguiba, Tunis'], $log);

insertIfMissing($db, 'clients', 'nom', 'Hôtel El Manar',
    "INSERT INTO clients (nom,type_client,contact_nom,telephone,email,adresse) VALUES (?,'terme',?,?,?,?)",
    ['Hôtel El Manar', 'Mme Bouazizi', '71 456 789', 'achats@hotelmanar.tn', 'Rue du Lac Windermere, Tunis'], $log);

// ── Franchise ────────────────────────────────────────
insertIfMissing($db, 'clients', 'nom', 'Franchise Ben Yedder Sfax',
    "INSERT INTO clients (nom,type_client,contact_nom,telephone,email,adresse) VALUES (?,'franchise',?,?,?,?)",
    ['Franchise Ben Yedder Sfax', 'M. Ayari', '74 123 456', 'sfax@benyedder.tn', 'Route de Tunis, Sfax'], $log);

$stmt = $db->prepare("SELECT id FROM clients WHERE nom = 'Franchise Ben Yedder Sfax'");
$stmt->execute();
$franchiseClientId = $stmt->fetchColumn();
if ($franchiseClientId) {
    $check = $db->prepare("SELECT 1 FROM franchises WHERE client_id = ?");
    $check->execute([$franchiseClientId]);
    if (!$check->fetch()) {
        $db->prepare("INSERT INTO franchises (client_id, mode_paiement, territoire) VALUES (?, 'libre_choix', 'Sfax')")->execute([$franchiseClientId]);
        $log[] = "inséré : franchises.client_id = $franchiseClientId";
    } else {
        $log[] = "ignoré (déjà présent) : franchises.client_id = $franchiseClientId";
    }
}

// ── Point de vente ───────────────────────────────────
insertIfMissing($db, 'points_vente', 'nom', 'Boutique El Menzah',
    "INSERT INTO points_vente (nom,adresse,responsable,telephone) VALUES (?,?,?,?)",
    ['Boutique El Menzah', 'Avenue Charles Nicolle, El Menzah, Tunis', 'M. Ghariani', '71 987 654'], $log);

// ── Produits supplémentaires ─────────────────────────
insertIfMissing($db, 'produits', 'nom', 'Msemen',
    "INSERT INTO produits (nom,categorie,prix_vente,unite) VALUES (?,?,?,?)",
    ['Msemen', 'Viennoiserie', 0.700, 'pièce'], $log);

insertIfMissing($db, 'produits', 'nom', 'Samsa',
    "INSERT INTO produits (nom,categorie,prix_vente,unite) VALUES (?,?,?,?)",
    ['Samsa', 'Pâtisserie traditionnelle', 1.300, 'pièce'], $log);

// ── Recettes des nouveaux produits ───────────────────
$recettes = [
    ['Msemen', 'Farine', 0.0600],
    ['Msemen', 'Beurre', 0.0150],
    ['Samsa', 'Amandes', 0.0250],
    ['Samsa', 'Miel', 0.0150],
    ['Samsa', 'Farine', 0.0150],
];
$insRecette = $db->prepare("INSERT INTO recettes (produit_id, matiere_id, quantite_necessaire) VALUES (?,?,?)
    ON DUPLICATE KEY UPDATE quantite_necessaire = VALUES(quantite_necessaire)");
foreach ($recettes as [$pnom, $mnom, $qte]) {
    $p = $db->prepare("SELECT id FROM produits WHERE nom = ?");
    $p->execute([$pnom]);
    $produitId = $p->fetchColumn();
    $m = $db->prepare("SELECT id FROM matieres_premieres WHERE nom = ?");
    $m->execute([$mnom]);
    $matiereId = $m->fetchColumn();
    if ($produitId && $matiereId) {
        $insRecette->execute([$produitId, $matiereId, $qte]);
        $log[] = "recette à jour : $pnom / $mnom";
    }
}

// ── Comptes de connexion pour tester chaque portail externe (mot de passe : Demo2026!) ──
$demoHash = '$2y$12$OCO2J8b3/LbKX53L9s6UKevkJ0lE.AVQdwENxZAbvOQnu4RL/NHLq';

$stmt = $db->prepare("SELECT id FROM clients WHERE nom = 'Franchise Ben Yedder Sfax'");
$stmt->execute();
if ($fid = $stmt->fetchColumn()) {
    insertIfMissing($db, 'users', 'email', 'demo.franchise@benyedder.tn',
        "INSERT INTO users (nom,email,password_hash,role,avatar,client_id) VALUES (?,?,?,'franchise','FR',?)",
        ['Franchise Sfax (démo)', 'demo.franchise@benyedder.tn', $demoHash, $fid], $log);
}

$stmt = $db->prepare("SELECT id FROM clients WHERE nom = 'Café de la Gare'");
$stmt->execute();
if ($cid = $stmt->fetchColumn()) {
    insertIfMissing($db, 'users', 'email', 'demo.client@benyedder.tn',
        "INSERT INTO users (nom,email,password_hash,role,avatar,client_id) VALUES (?,?,?,'client_terme','CG',?)",
        ['Café de la Gare (démo)', 'demo.client@benyedder.tn', $demoHash, $cid], $log);
}

$stmt = $db->prepare("SELECT id FROM points_vente WHERE nom = 'Boutique El Menzah'");
$stmt->execute();
if ($pvid = $stmt->fetchColumn()) {
    insertIfMissing($db, 'users', 'email', 'demo.pointvente@benyedder.tn',
        "INSERT INTO users (nom,email,password_hash,role,avatar,point_vente_id) VALUES (?,?,?,'point_vente','PV',?)",
        ['Boutique El Menzah (démo)', 'demo.pointvente@benyedder.tn', $demoHash, $pvid], $log);
}

// ── Comptes "production" de démonstration, rattachés à une cuisine (mot de passe : Demo2026!) ──
// Ne s'exécute que si la table cuisines_production existe (schéma v2 déjà appliqué).
try {
    $hasCuisines = $db->query("SHOW TABLES LIKE 'cuisines_production'")->fetchColumn();
    if ($hasCuisines) {
        foreach ([
            ['Viennoiserie', 'demo.viennoiserie@benyedder.tn', 'Chef Viennoiserie (démo)', 'CV'],
            ['Pâtisserie traditionnelle', 'demo.patisserie@benyedder.tn', 'Chef Pâtisserie (démo)', 'CP'],
        ] as [$cuisineNom, $email, $nom, $avatar]) {
            $s = $db->prepare("SELECT id FROM cuisines_production WHERE nom = ?");
            $s->execute([$cuisineNom]);
            if ($cuid = $s->fetchColumn()) {
                insertIfMissing($db, 'users', 'email', $email,
                    "INSERT INTO users (nom,email,password_hash,role,avatar,cuisine_id) VALUES (?,?,?,'production',?,?)",
                    [$nom, $email, $demoHash, $avatar, $cuid], $log);
            }
        }
    }
} catch (Exception $e) { $log[] = 'cuisines demo skipped: ' . $e->getMessage(); }

echo json_encode(['ok' => true, 'log' => $log], JSON_UNESCAPED_UNICODE);
