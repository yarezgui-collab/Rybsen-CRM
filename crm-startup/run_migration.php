<?php
// One-shot migration runner — auto-deletes after execution
ob_start();
define('MIGRATION_TOKEN', 'MIGRATION_TOKEN_PLACEHOLDER');

require_once __DIR__ . '/config.php';
ob_end_clean(); // discard any output from config.php

header('Content-Type: application/json');

if (!isset($_GET['token']) || !hash_equals(MIGRATION_TOKEN, (string)$_GET['token'])) {
    http_response_code(403);
    die(json_encode(['error' => 'Forbidden']));
}
$db = getDB();

$results = [];

$columns = [
    'email_verified'      => "ALTER TABLE `fm_users` ADD COLUMN `email_verified` TINYINT(1) NOT NULL DEFAULT 0",
    'email_verif_code'    => "ALTER TABLE `fm_users` ADD COLUMN `email_verif_code` VARCHAR(255) DEFAULT NULL",
    'email_verif_expires' => "ALTER TABLE `fm_users` ADD COLUMN `email_verif_expires` DATETIME DEFAULT NULL",
    'reset_token'         => "ALTER TABLE `fm_users` ADD COLUMN `reset_token` VARCHAR(255) DEFAULT NULL",
    'reset_token_expires' => "ALTER TABLE `fm_users` ADD COLUMN `reset_token_expires` DATETIME DEFAULT NULL",
];

foreach ($columns as $col => $sql) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fm_users' AND COLUMN_NAME = ?");
    $stmt->execute([$col]);
    if ((int)$stmt->fetchColumn() === 0) {
        try {
            $db->exec($sql);
            $results[] = "OK: added column $col";
        } catch (PDOException $e) {
            $results[] = "ERR: $col — " . $e->getMessage();
        }
    } else {
        $results[] = "SKIP: column $col already exists";
    }
}

// Marquer les utilisateurs actifs existants comme vérifiés
try {
    $n = $db->exec("UPDATE `fm_users` SET `email_verified` = 1 WHERE `is_active` = 1 AND `email_verified` = 0");
    $results[] = "OK: $n existing active user(s) marked as verified";
} catch (PDOException $e) {
    $results[] = "ERR: update existing users — " . $e->getMessage();
}

// Index — vérification avant ajout
$indexes = [
    'idx_reset_token'    => "ALTER TABLE `fm_users` ADD INDEX `idx_reset_token` (`reset_token`)",
    'idx_email_verified' => "ALTER TABLE `fm_users` ADD INDEX `idx_email_verified` (`email`, `email_verified`)",
];

foreach ($indexes as $name => $sql) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fm_users' AND INDEX_NAME = ?");
    $stmt->execute([$name]);
    if ((int)$stmt->fetchColumn() === 0) {
        try {
            $db->exec($sql);
            $results[] = "OK: added index $name";
        } catch (PDOException $e) {
            $results[] = "ERR: $name — " . $e->getMessage();
        }
    } else {
        $results[] = "SKIP: index $name already exists";
    }
}

// ── v5 : présence en ligne, favoris, indicateur de saisie ────────
$columns_v5 = [
    'last_activity' => "ALTER TABLE `fm_users` ADD COLUMN `last_activity` DATETIME DEFAULT NULL",
];
foreach ($columns_v5 as $col => $sql) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fm_users' AND COLUMN_NAME = ?");
    $stmt->execute([$col]);
    if ((int)$stmt->fetchColumn() === 0) {
        try {
            $db->exec($sql);
            $results[] = "OK: added column $col";
        } catch (PDOException $e) {
            $results[] = "ERR: $col — " . $e->getMessage();
        }
    } else {
        $results[] = "SKIP: column $col already exists";
    }
}

$indexes_v5 = [
    'idx_last_activity' => "ALTER TABLE `fm_users` ADD INDEX `idx_last_activity` (`last_activity`)",
];
foreach ($indexes_v5 as $name => $sql) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fm_users' AND INDEX_NAME = ?");
    $stmt->execute([$name]);
    if ((int)$stmt->fetchColumn() === 0) {
        try {
            $db->exec($sql);
            $results[] = "OK: added index $name";
        } catch (PDOException $e) {
            $results[] = "ERR: $name — " . $e->getMessage();
        }
    } else {
        $results[] = "SKIP: index $name already exists";
    }
}

$tables_v5 = [
    'fm_favorites' => "CREATE TABLE `fm_favorites` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `program_id` INT NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uniq_user_program` (`user_id`, `program_id`),
        KEY `idx_program` (`program_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'fm_typing' => "CREATE TABLE `fm_typing` (
        `user_id` INT NOT NULL PRIMARY KEY,
        `receiver_id` INT NOT NULL,
        `updated_at` DATETIME NOT NULL,
        KEY `idx_receiver` (`receiver_id`, `updated_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];
foreach ($tables_v5 as $table => $sql) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute([$table]);
    if ((int)$stmt->fetchColumn() === 0) {
        try {
            $db->exec($sql);
            $results[] = "OK: created table $table";
        } catch (PDOException $e) {
            $results[] = "ERR: $table — " . $e->getMessage();
        }
    } else {
        $results[] = "SKIP: table $table already exists";
    }
}

// ── v6 : import de programmes (source externe, 24/07/2026) ──────
// "The Dot Sprint Camp" volontairement exclu : deadline déjà dépassée
// à la génération de cette migration (aurait été auto-archivé immédiatement).
$programs_v6 = [
    [
        'name' => 'Le Grand Bain 2026 — Délégation Tunisienne',
        'organisation' => 'La French Tech Aix-Marseille × The Dot',
        'type' => 'competition',
        'badge' => 'Mission Marseille (5 places)',
        'amount' => 'Billets avion A/R + accès événement + RDV business (hébergement non inclus)',
        'stage_target' => 'Startups innovantes à fort impact',
        'geo' => 'Tunisie → France (Marseille)',
        'tn_eligible' => 'Oui — 5 startups tunisiennes sélectionnées',
        'tunisia_focus' => 1,
        'deadline' => '24 Juillet 2026',
        'deadline_date' => '2026-07-24',
        'sectors' => 'IA / AI,Greentech,Cleantech,Impact Social',
        'description' => 'Sélection de 5 startups tunisiennes innovantes à fort impact (IA, GreenTech, Inclusive Tech, Transition énergétique) pour participer au Grand Bain 2026, événement phare de la French Tech Aix-Marseille (8e édition, 14 septembre au Palais du Pharo, 1 800+ participants). Programme : rencontres B2B avec investisseurs et partenaires internationaux, immersion dans les écosystèmes de Marseille et Aix-en-Provence. Prise en charge des billets d\'avion aller-retour, de l\'accès à l\'événement et du programme d\'immersion. Thème 2026 : « ERROR 404 - Refresh to success ».',
        'link' => 'https://legrandbain.lafrenchtech-aixmarseille.fr',
        'emoji' => '✈️',
    ],
    [
        'name' => 'Creative Tunisia 2.0 — Diaspora Artisanat',
        'organisation' => 'ONUDI × ONAT × OTE (financé par l\'UE)',
        'type' => 'grant',
        'badge' => 'Appui diaspora artisanat',
        'amount' => 'Jusqu\'à 15 000 EUR par entreprise',
        'stage_target' => 'Entrepreneurs diaspora tunisienne',
        'geo' => 'Diaspora tunisienne (pays de résidence)',
        'tn_eligible' => 'Oui — entrepreneurs tunisiens résidant à l\'étranger',
        'tunisia_focus' => 1,
        'deadline' => '25 Août 2026',
        'deadline_date' => '2026-08-25',
        'sectors' => 'E-commerce,Impact Social,Tourisme Tech',
        'description' => 'Programme de l\'ONUDI destiné aux entrepreneurs de la diaspora tunisienne commercialisant des produits artisanaux dans leur pays de résidence. Appui technique et financier pouvant atteindre 15 000 EUR par entreprise : accompagnement au développement d\'activité, valorisation du savoir-faire artisanal tunisien à l\'international, accès à un réseau d\'experts locaux et de fournisseurs. Mis en œuvre avec l\'Office National de l\'Artisanat (ONAT) et l\'Office des Tunisiens à l\'Étranger (OTE), dans le cadre du programme européen EDMEJ financé par l\'Union européenne avec contribution de l\'Agence italienne pour la coopération au développement.',
        'link' => 'https://creativetunisia.tn',
        'emoji' => '🎨',
    ],
    [
        'name' => 'MEDICA 2026 — Délégation Tunisienne',
        'organisation' => 'AHK Tunisie (Chambre Tuniso-Allemande)',
        'type' => 'competition',
        'badge' => 'Salon international HealthTech',
        'amount' => 'Participation délégation tunisienne au salon',
        'stage_target' => 'Startups HealthTech / MedTech',
        'geo' => 'Tunisie → Allemagne (Düsseldorf)',
        'tn_eligible' => 'Oui — startups tunisiennes HealthTech uniquement',
        'tunisia_focus' => 1,
        'deadline' => '1 Septembre 2026 (salon 16-20 Novembre)',
        'deadline_date' => '2026-09-01',
        'sectors' => 'Healthtech,Deep Tech,IA / AI,Life Sciences',
        'description' => 'Appel à candidatures de la Chambre Tuniso-Allemande de l\'Industrie et du Commerce (AHK Tunisie) pour rejoindre la délégation tunisienne au salon international MEDICA 2026, du 16 au 20 novembre 2026 à Düsseldorf. MEDICA est le plus grand salon mondial dédié à la santé et aux technologies médicales. Destiné aux startups tunisiennes innovantes en HealthTech, MedTech ou Digital Health cherchant des partenariats industriels, distributeurs et investisseurs sur le marché européen.',
        'link' => 'https://www.tunesien.ahk.de',
        'emoji' => '🏥',
    ],
    [
        'name' => 'Gamescom 2026 — Cologne',
        'organisation' => 'Koelnmesse (Salon international Gaming)',
        'type' => 'competition',
        'badge' => 'Salon mondial Gaming',
        'amount' => 'Participation salon + networking industrie',
        'stage_target' => 'Studios gaming et XR (toutes phases)',
        'geo' => 'Allemagne (Cologne)',
        'tn_eligible' => 'Oui — ouvert internationalement',
        'tunisia_focus' => 0,
        'deadline' => '19-23 Août 2026 (événement)',
        'deadline_date' => '2026-08-19',
        'sectors' => 'Tech,IA / AI,Deep Tech,E-commerce',
        'description' => 'Gamescom est le plus grand salon mondial dédié aux jeux vidéo et aux technologies interactives, réunissant startups, développeurs, investisseurs et acteurs majeurs de l\'industrie du Gaming, Entertainment Tech, XR et Digital Tech. Opportunité pour les studios tunisiens de rencontrer éditeurs, publishers et investisseurs spécialisés gaming, et de présenter leurs productions sur la scène internationale.',
        'link' => 'https://www.gamescom.global',
        'emoji' => '🎮',
    ],
    [
        'name' => 'IFC SME Growth Accelerator Grant 2026',
        'organisation' => 'International Finance Corporation (Groupe Banque Mondiale)',
        'type' => 'grant',
        'badge' => 'Grant PME jusqu\'à 1,5M$',
        'amount' => '50 000 – 1 500 000 USD',
        'stage_target' => 'PME à forte croissance (10-250 employés, CA 250K-10M USD)',
        'geo' => 'Afrique, Asie, Amérique latine, Caraïbes',
        'tn_eligible' => 'Oui — PME tunisiennes éligibles',
        'tunisia_focus' => 1,
        'deadline' => '15 Novembre 2026',
        'deadline_date' => '2026-11-15',
        'sectors' => 'Tout secteur',
        'description' => 'Initiative de la Société Financière Internationale (IFC), membre du Groupe Banque Mondiale, destinée aux PME à forte croissance. Financement de 50 000 à 1,5 million USD pour entreprises à but lucratif comptant 10 à 250 employés et réalisant un chiffre d\'affaires annuel entre 250 000 et 10 millions USD. Au-delà du financement : mentorat, assistance technique, accès au réseau IFC et opportunités de financement de suivi. L\'un des plus gros tickets accessibles aux PME tunisiennes matures.',
        'link' => 'https://www.ifc.org',
        'emoji' => '🏦',
    ],
    [
        'name' => 'Supercell Developer Grants Program — Africa',
        'organisation' => 'Supercell',
        'type' => 'grant',
        'badge' => 'Grant gaming sans dilution',
        'amount' => '20 000 – 200 000 USD (equity-free)',
        'stage_target' => 'Studios de jeux légalement enregistrés',
        'geo' => 'Afrique',
        'tn_eligible' => 'Oui — studios africains dont Tunisie',
        'tunisia_focus' => 1,
        'deadline' => '9 Août 2026 (financement en Décembre)',
        'deadline_date' => '2026-08-09',
        'sectors' => 'Tech,IA / AI,E-commerce',
        'description' => 'Premier programme de grants de Supercell (éditeur de Clash of Clans, Brawl Stars) dédié à l\'Afrique. Grants sans dilution de 20 000 à 200 000 USD pour studios de jeux légalement enregistrés sur le continent. Le studio conserve 100% de la propriété de son jeu et de son entreprise. Première cohorte visant 3 à 5 lauréats. Financement démarrant en décembre 2026. Opportunité rare de financement non dilutif significatif pour le secteur gaming africain.',
        'link' => 'https://supercell.com',
        'emoji' => '🎮',
    ],
];

foreach ($programs_v6 as $p) {
    $chk = $db->prepare('SELECT COUNT(*) FROM fm_programs WHERE name = ?');
    $chk->execute([$p['name']]);
    if ((int)$chk->fetchColumn() === 0) {
        try {
            $db->prepare('INSERT INTO fm_programs
                (name,organisation,type,badge,amount,stage_target,geo,tn_eligible,
                 tunisia_focus,deadline,deadline_date,deadline_type,sectors,description,
                 link,emoji,status,validated_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,"active",NULL)')
               ->execute([
                   $p['name'], $p['organisation'], $p['type'], $p['badge'], $p['amount'],
                   $p['stage_target'], $p['geo'], $p['tn_eligible'], $p['tunisia_focus'],
                   $p['deadline'], $p['deadline_date'], calcDeadlineType($p['deadline_date']),
                   $p['sectors'], $p['description'], $p['link'], $p['emoji'],
               ]);
            $results[] = 'OK: programme importé — ' . $p['name'];
        } catch (PDOException $e) {
            $results[] = 'ERR: ' . $p['name'] . ' — ' . $e->getMessage();
        }
    } else {
        $results[] = 'SKIP: programme déjà présent — ' . $p['name'];
    }
}

// Auto-destruction
@unlink(__FILE__);
$results[] = "OK: script deleted";

echo json_encode(['success' => true, 'results' => $results], JSON_PRETTY_PRINT);
