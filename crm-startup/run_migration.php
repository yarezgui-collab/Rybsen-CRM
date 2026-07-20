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

// Auto-destruction
@unlink(__FILE__);
$results[] = "OK: script deleted";

echo json_encode(['success' => true, 'results' => $results], JSON_PRETTY_PRINT);
