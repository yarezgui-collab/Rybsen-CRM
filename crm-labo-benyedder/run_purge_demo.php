<?php
// run_purge_demo.php — nettoyage de livraison : supprime toutes les données de démonstration
// et l'historique transactionnel de test, en CONSERVANT :
//   - le compte administrateur, les paramètres, les cuisines et les catégories ;
//   - les clients réels importés (ceux qui ont un code_externe) et leurs accès.
// À déclencher UNE FOIS (workflow manuel « Purge demo »), protégé par jeton.
// N'est jamais appelé par le déploiement automatique.
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'POST requis']); exit; }
$provided = $_SERVER['HTTP_X_MIGRATION_TOKEN'] ?? '';
if (!defined('MIGRATION_TOKEN') || MIGRATION_TOKEN === '' || !hash_equals(MIGRATION_TOKEN, $provided)) {
    http_response_code(403); echo json_encode(['error' => 'Jeton invalide']); exit;
}

$db = getDB();
$log = [];
try {
    $db->exec("SET FOREIGN_KEY_CHECKS=0");

    // 1) Historique transactionnel de test (toutes les tables de mouvements)
    foreach ([
        'paiements','declarations_paiement','lignes_livraison','livraisons','lots',
        'ordre_fabrication_commandes','lignes_ordre_fabrication','ordres_fabrication',
        'factures','lignes_commande','commandes',
        'mouvements_stock_produits','mouvements_stock_matieres',
        'stocks_points_vente','stocks_clients','inventaire_lignes','inventaires','pertes',
        // 2) Catalogue d'exemple (produits, matières, recettes, autorisations)
        'catalogue_autorise','recettes','produits','matieres_premieres',
    ] as $t) {
        try { $log['vidé_' . $t] = $db->exec("DELETE FROM $t"); } catch (Exception $e) { $log['skip_' . $t] = $e->getMessage(); }
    }

    // 3) Comptes de connexion de démonstration
    $emails = ['demo.franchise@benyedder.tn','demo.client@benyedder.tn','demo.pointvente@benyedder.tn',
               'demo.viennoiserie@benyedder.tn','demo.patisserie@benyedder.tn'];
    $in = implode(',', array_fill(0, count($emails), '?'));
    $st = $db->prepare("DELETE FROM users WHERE email IN ($in)");
    $st->execute($emails);
    $log['comptes_demo_supprimes'] = $st->rowCount();

    // 4) Entités fictives (franchise, points de vente, clients de démo sans code_externe)
    $noms = ['Café de la Gare','Hôtel El Manar','Franchise Ben Yedder Sfax'];
    $inN = implode(',', array_fill(0, count($noms), '?'));
    $db->prepare("DELETE FROM franchises WHERE client_id IN (SELECT id FROM clients WHERE nom IN ($inN))")->execute($noms);
    $stC = $db->prepare("DELETE FROM clients WHERE nom IN ($inN) AND code_externe IS NULL");
    $stC->execute($noms);
    $log['clients_demo_supprimes'] = $stC->rowCount();
    $stP = $db->prepare("DELETE FROM points_vente WHERE nom = 'Boutique El Menzah'");
    $stP->execute();
    $log['points_vente_demo_supprimes'] = $stP->rowCount();

    // 5) Remise à zéro des compteurs (tables vidées) pour une numérotation propre
    foreach (['factures','commandes','produits','matieres_premieres','ordres_fabrication','livraisons'] as $t) {
        try { $db->exec("ALTER TABLE $t AUTO_INCREMENT = 1"); } catch (Exception $e) {}
    }

    $db->exec("SET FOREIGN_KEY_CHECKS=1");

    // Contrôle : ce qui reste
    $log['reste_clients_reels'] = (int)$db->query("SELECT COUNT(*) FROM clients WHERE code_externe IS NOT NULL")->fetchColumn();
    $log['reste_produits'] = (int)$db->query("SELECT COUNT(*) FROM produits")->fetchColumn();
    $log['reste_cuisines'] = (int)$db->query("SELECT COUNT(*) FROM cuisines_production")->fetchColumn();
    $log['reste_admin'] = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();

    echo json_encode(['ok' => true, 'log' => $log], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    $db->exec("SET FOREIGN_KEY_CHECKS=1");
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage(), 'log' => $log], JSON_UNESCAPED_UNICODE);
}
