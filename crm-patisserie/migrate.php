<?php
// Script de migration — à exécuter UNE FOIS puis supprimer
// Accéder via navigateur : https://votre-domaine/pby/migrate.php
require_once __DIR__ . '/config.php';
$pdo = get_pdo();
$pdo->exec("
    CREATE TABLE IF NOT EXISTS prix_client (
      client_id  VARCHAR(40)   NOT NULL,
      produit_id VARCHAR(40)   NOT NULL,
      prix       DECIMAL(10,3) NOT NULL,
      PRIMARY KEY (client_id, produit_id),
      KEY idx_pc_client  (client_id),
      KEY idx_pc_produit (produit_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
echo '<p style="font-family:sans-serif;color:green;font-size:18px">✅ Table prix_client créée (ou déjà existante). Supprimez ce fichier maintenant.</p>';
