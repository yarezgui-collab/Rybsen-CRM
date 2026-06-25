<?php
/**
 * api/stats.php — GET (auth propriétaire uniquement)
 * ?periode=jour|semaine|mois => statistiques agrégées + alertes anomalies.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_method('GET');
$user = require_auth(['proprietaire']);

$pdo = get_db();
$restaurantId = $user['restaurant_id'];
$periode = $_GET['periode'] ?? 'jour';

[$dateDebut, $dateDebutPrecedente, $dateFinPrecedente] = resolve_periode($periode);

$stmt = $pdo->prepare(
    "SELECT COUNT(*) as nb_commandes, COALESCE(SUM(total),0) as ca
     FROM commandes
     WHERE restaurant_id = ? AND statut != 'annulee' AND created_at >= ?"
);
$stmt->execute([$restaurantId, $dateDebut]);
$courant = $stmt->fetch();

$stmt = $pdo->prepare(
    "SELECT COUNT(*) as nb_commandes, COALESCE(SUM(total),0) as ca
     FROM commandes
     WHERE restaurant_id = ? AND statut != 'annulee' AND created_at >= ? AND created_at < ?"
);
$stmt->execute([$restaurantId, $dateDebutPrecedente, $dateFinPrecedente]);
$precedent = $stmt->fetch();

$caCourant = (float)$courant['ca'];
$caPrecedent = (float)$precedent['ca'];
$variationCa = $caPrecedent > 0 ? round((($caCourant - $caPrecedent) / $caPrecedent) * 100, 1) : null;

$panierMoyen = $courant['nb_commandes'] > 0 ? $caCourant / $courant['nb_commandes'] : 0;

$stmt = $pdo->prepare(
    "SELECT ci.nom_article, SUM(ci.quantite) as qte, SUM(ci.quantite * ci.prix_unitaire) as ca_article
     FROM commande_items ci
     JOIN commandes c ON c.id = ci.commande_id
     WHERE c.restaurant_id = ? AND c.statut != 'annulee' AND c.created_at >= ?
     GROUP BY ci.nom_article
     ORDER BY qte DESC LIMIT 5"
);
$stmt->execute([$restaurantId, $dateDebut]);
$topArticles = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT HOUR(created_at) as heure, COUNT(*) as nb, SUM(total) as ca
     FROM commandes
     WHERE restaurant_id = ? AND statut != 'annulee' AND created_at >= ?
     GROUP BY HOUR(created_at)
     ORDER BY heure ASC"
);
$stmt->execute([$restaurantId, $dateDebut]);
$parHeure = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT u.nom, COUNT(c.id) as nb_commandes, COALESCE(SUM(c.total),0) as ca,
            AVG(TIMESTAMPDIFF(MINUTE, c.created_at, c.servie_at)) as temps_moyen_min
     FROM commandes c
     JOIN utilisateurs u ON u.id = c.serveur_id
     WHERE c.restaurant_id = ? AND c.statut != 'annulee' AND c.created_at >= ?
     GROUP BY u.id, u.nom
     ORDER BY ca DESC"
);
$stmt->execute([$restaurantId, $dateDebut]);
$parServeur = $stmt->fetchAll();

$alertes = [];

$stmt = $pdo->prepare(
    "SELECT c.code, t.numero, TIMESTAMPDIFF(MINUTE, c.created_at, NOW()) as minutes_attente
     FROM commandes c JOIN tables_restaurant t ON t.id = c.table_id
     WHERE c.restaurant_id = ? AND c.statut IN ('nouvelle','en_cours')
       AND c.created_at < DATE_SUB(NOW(), INTERVAL 20 MINUTE)"
);
$stmt->execute([$restaurantId]);
foreach ($stmt->fetchAll() as $row) {
    $alertes[] = [
        'type' => 'retard',
        'gravite' => $row['minutes_attente'] > 35 ? 'haute' : 'moyenne',
        'message' => "Commande #{$row['code']} (Table {$row['numero']}) en attente depuis {$row['minutes_attente']} min.",
    ];
}

$stmt = $pdo->prepare(
    "SELECT COUNT(*) as nb FROM whatsapp_logs wl
     JOIN commandes c ON c.id = wl.commande_id
     WHERE c.restaurant_id = ? AND wl.statut = 'echec' AND wl.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
);
$stmt->execute([$restaurantId]);
$echecsWhatsapp = (int)$stmt->fetchColumn();
if ($echecsWhatsapp > 0) {
    $alertes[] = [
        'type' => 'whatsapp',
        'gravite' => 'haute',
        'message' => "{$echecsWhatsapp} notification(s) WhatsApp ont échoué dans la dernière heure. Vérifiez la configuration du provider.",
    ];
}

if ($variationCa !== null && $variationCa <= -30) {
    $alertes[] = [
        'type' => 'ca',
        'gravite' => 'moyenne',
        'message' => "Le chiffre d'affaires a baissé de " . abs($variationCa) . "% par rapport à la période précédente.",
    ];
}

$stmt = $pdo->prepare(
    "SELECT DATE(created_at) as jour, SUM(total) as ca
     FROM commandes
     WHERE restaurant_id = ? AND statut != 'annulee' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
     GROUP BY DATE(created_at) ORDER BY jour ASC"
);
$stmt->execute([$restaurantId]);
$historique7j = $stmt->fetchAll();
$moyenne7j = count($historique7j) > 0
    ? array_sum(array_column($historique7j, 'ca')) / count($historique7j)
    : 0;

json_response([
    'success' => true,
    'periode' => $periode,
    'kpis' => [
        'ca' => round($caCourant, 3),
        'ca_variation_pct' => $variationCa,
        'nb_commandes' => (int)$courant['nb_commandes'],
        'panier_moyen' => round($panierMoyen, 3),
    ],
    'top_articles' => array_map(fn($a) => [
        'nom' => $a['nom_article'],
        'quantite' => (int)$a['qte'],
        'ca' => round((float)$a['ca_article'], 3),
    ], $topArticles),
    'par_heure' => array_map(fn($h) => [
        'heure' => (int)$h['heure'],
        'nb_commandes' => (int)$h['nb'],
        'ca' => round((float)$h['ca'], 3),
    ], $parHeure),
    'par_serveur' => array_map(fn($s) => [
        'nom' => $s['nom'],
        'nb_commandes' => (int)$s['nb_commandes'],
        'ca' => round((float)$s['ca'], 3),
        'temps_moyen_min' => $s['temps_moyen_min'] !== null ? round((float)$s['temps_moyen_min'], 1) : null,
    ], $parServeur),
    'previsions' => [
        'ca_moyen_7j' => round($moyenne7j, 3),
        'projection_demain' => round($moyenne7j, 3),
    ],
    'alertes' => $alertes,
]);

function resolve_periode(string $periode): array {
    switch ($periode) {
        case 'semaine':
            return [
                date('Y-m-d 00:00:00', strtotime('monday this week')),
                date('Y-m-d 00:00:00', strtotime('monday last week')),
                date('Y-m-d 00:00:00', strtotime('monday this week')),
            ];
        case 'mois':
            return [
                date('Y-m-01 00:00:00'),
                date('Y-m-01 00:00:00', strtotime('first day of last month')),
                date('Y-m-01 00:00:00'),
            ];
        default:
            return [
                date('Y-m-d 00:00:00'),
                date('Y-m-d 00:00:00', strtotime('yesterday')),
                date('Y-m-d 00:00:00'),
            ];
    }
}
