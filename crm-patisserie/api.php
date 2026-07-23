<?php
// ============================================================
// API Pâtisserie CRM — point d'entrée unique
// Toutes les requêtes du site passent par ce fichier : api.php?action=...
// ============================================================

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
// CORS restreint au domaine du site lui-même (plutôt que '*' qui autoriserait
// n'importe quel site externe à appeler cette API depuis le navigateur d'un visiteur).
$allowedOrigin = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '');
header('Access-Control-Allow-Origin: ' . $allowedOrigin);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

$pdo    = get_pdo();
$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?? [];

function out($data) { echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function err($msg, $code = 400) { http_response_code($code); out(['error' => $msg]); }

// ---- Protection anti brute-force sur la connexion ----
// Limite à 10 tentatives de connexion par minute par adresse IP.
// Le fichier de compteur est stocké dans le même dossier que l'API (créé automatiquement).
function check_login_rate_limit() {
    $ip ='_' . preg_replace('/[^a-zA-Z0-9_]/', '', $_SERVER['REMOTE_ADDR'] ?? 'inconnu');
    $file = __DIR__ . '/.rl' . md5($ip) . '.json';
    $now = time();
    $data = ['count' => 0, 'since' => $now];
    if (file_exists($file)) {
        $raw = json_decode(file_get_contents($file), true);
        if (is_array($raw)) $data = $raw;
    }
    if ($now - $data['since'] > 60) { $data = ['count' => 0, 'since' => $now]; }
    $data['count']++;
    file_put_contents($file, json_encode($data));
    if ($data['count'] > 10) {
        err('Trop de tentatives de connexion. Réessayez dans une minute.', 429);
    }
}

/* ============================================================================
   MODULE TARIFS SPÉCIAUX — persistance fiable (source unique : la base)
   ----------------------------------------------------------------------------
   rebuild_prix_client() reconstruit la table dans un état GARANTI propre :
     • clé primaire composite (client_id, produit_id)
     • aucune ligne fantôme (client_id ou produit_id vide)
     • aucun doublon (on conserve la dernière valeur saisie par couple)
   Idempotente. Appelée par la réparation explicite ET en auto-guérison après
   un échec d'écriture — c'est ce qui rend l'enregistrement incassable :
   quel que soit l'état hérité de la table (pas de clé, mauvaise clé, doublons,
   lignes vides), une écriture qui échoue déclenche une reconstruction puis
   réessaie une fois, et réussit.
   ========================================================================== */
function rebuild_prix_client($pdo) {
    $pdo->exec("DROP TABLE IF EXISTS prix_client_seq");
    $pdo->exec("
        CREATE TABLE prix_client_seq (
          _seq       BIGINT        NOT NULL AUTO_INCREMENT PRIMARY KEY,
          client_id  VARCHAR(40)   NOT NULL,
          produit_id VARCHAR(40)   NOT NULL,
          prix       DECIMAL(10,3) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    // Copie l'existant en capturant l'ordre d'insertion (_seq croissant).
    $pdo->exec("
        INSERT INTO prix_client_seq (client_id, produit_id, prix)
        SELECT client_id, produit_id, prix FROM prix_client
    ");
    $pdo->exec("DROP TABLE IF EXISTS prix_client_clean");
    $pdo->exec("
        CREATE TABLE prix_client_clean (
          client_id  VARCHAR(40)   NOT NULL,
          produit_id VARCHAR(40)   NOT NULL,
          prix       DECIMAL(10,3) NOT NULL,
          PRIMARY KEY (client_id, produit_id),
          KEY idx_pcc_client  (client_id),
          KEY idx_pcc_produit (produit_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    // Ne garde que la ligne la plus récente (MAX _seq) par couple, en excluant
    // toute ligne fantôme (client ou produit vide).
    $pdo->exec("
        INSERT INTO prix_client_clean (client_id, produit_id, prix)
        SELECT s.client_id, s.produit_id, s.prix
        FROM prix_client_seq s
        JOIN (
            SELECT client_id, produit_id, MAX(_seq) AS mx
            FROM prix_client_seq
            WHERE client_id <> '' AND produit_id <> ''
            GROUP BY client_id, produit_id
        ) m ON m.client_id = s.client_id AND m.produit_id = s.produit_id AND m.mx = s._seq
    ");
    // Échange atomique + nettoyage. RENAME est atomique côté MySQL.
    $pdo->exec("RENAME TABLE prix_client TO prix_client_old, prix_client_clean TO prix_client");
    $pdo->exec("DROP TABLE prix_client_old");
    $pdo->exec("DROP TABLE IF EXISTS prix_client_seq");
}

try {
    switch ($action) {

        // ============================================================
        // VERSION — diagnostic : ouvrir api.php?action=version dans le
        // navigateur confirme quel code est réellement servi à cette URL.
        // ============================================================
        case 'version': {
            out(['ok' => true, 'version' => 'API-2026.07.23-selfheal', 'time' => date('c')]);
            break;
        }

        // ============================================================
        // CHARGEMENT GLOBAL — tout ce dont l'app a besoin en un appel
        // ============================================================
        case 'load_all': {
            $produits  = $pdo->query("SELECT id, nom, prix, categorie FROM produits ORDER BY nom")->fetchAll();
            foreach ($produits as &$p) { $p['prix'] = (float)$p['prix']; }

            $chauffeurs = $pdo->query("SELECT id, nom, utilisateur, mot_de_passe FROM chauffeurs ORDER BY nom")->fetchAll();

            $clients = $pdo->query("SELECT id, nom, chauffeur_id FROM clients ORDER BY nom")->fetchAll();

            $livraisons = $pdo->query("
                SELECT l.id, l.jour, l.client_id, c.nom AS client_nom, l.chauffeur_id,
                       l.produit_id, l.produit_nom, l.quantite, l.prix_unitaire
                FROM livraisons l
                JOIN clients c ON c.id = l.client_id
                ORDER BY l.jour DESC, l.id DESC
            ")->fetchAll();
            foreach ($livraisons as &$l) {
                $l['quantite']      = (int)$l['quantite'];
                $l['prix_unitaire'] = (float)$l['prix_unitaire'];
            }

            $encaissements = $pdo->query("
                SELECT e.id, e.jour, e.client_id, c.nom AS client_nom, e.chauffeur_id, e.montant
                FROM encaissements e
                JOIN clients c ON c.id = e.client_id
                ORDER BY e.jour DESC, e.id DESC
            ")->fetchAll();
            foreach ($encaissements as &$e) { $e['montant'] = (float)$e['montant']; }

            $retours = $pdo->query("
                SELECT id, livraison_id, jour, quantite, motif
                FROM retours
                ORDER BY jour DESC, id DESC
            ")->fetchAll();
            foreach ($retours as &$rt) { $rt['quantite'] = (int)$rt['quantite']; }

            $rapports = $pdo->query("
                SELECT jour, rempli, recupere, ecart, detail_json
                FROM rapports_jour ORDER BY jour DESC
            ")->fetchAll();
            foreach ($rapports as &$r) {
                $r['rempli']   = (float)$r['rempli'];
                $r['recupere'] = (float)$r['recupere'];
                $r['ecart']    = (float)$r['ecart'];
                $r['detail']   = json_decode($r['detail_json'], true);
                unset($r['detail_json']);
            }

            $prixClient = $pdo->query("SELECT client_id, produit_id, prix FROM prix_client")->fetchAll();
            foreach ($prixClient as &$pc) { $pc['prix'] = (float)$pc['prix']; }

            $paramsRows = $pdo->query("SELECT cle, valeur FROM parametres")->fetchAll();
            $params = [];
            foreach ($paramsRows as $row) { $params[$row['cle']] = $row['valeur']; }

            out([
                'produits'      => $produits,
                'chauffeurs'    => $chauffeurs,
                'clients'       => $clients,
                'livraisons'    => $livraisons,
                'encaissements' => $encaissements,
                'retours'       => $retours,
                'rapports'      => $rapports,
                'parametres'    => $params,
                'prix_client'   => $prixClient,
            ]);
            break;
        }

        // ============================================================
        // AUTHENTIFICATION
        // ============================================================
        case 'login': {
            check_login_rate_limit();
            $user = trim($input['user'] ?? '');
            $pass = (string)($input['pass'] ?? '');
            if ($user === '' ) err('Utilisateur requis');

            // Labo / Propriétaire (stockés dans parametres)
            $stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = ?");
            $stmt->execute(['auth_labo_user']);
            $loboUser = $stmt->fetchColumn();
            $stmt->execute(['auth_labo_pass']);
            $loboPass = $stmt->fetchColumn();
            if (strcasecmp($user, (string)$loboUser) === 0 && $pass === (string)$loboPass) {
                out(['ok' => true, 'role' => 'labo']);
            }

            $stmt->execute(['auth_proprio_user']);
            $propUser = $stmt->fetchColumn();
            $stmt->execute(['auth_proprio_pass']);
            $propPass = $stmt->fetchColumn();
            if (strcasecmp($user, (string)$propUser) === 0 && $pass === (string)$propPass) {
                out(['ok' => true, 'role' => 'proprietaire']);
            }

            // Chauffeurs
            $stmt = $pdo->prepare("SELECT id FROM chauffeurs WHERE utilisateur = ? AND mot_de_passe = ?");
            $stmt->execute([$user, $pass]);
            $row = $stmt->fetch();
            if ($row) out(['ok' => true, 'role' => $row['id']]);

            out(['ok' => false]);
            break;
        }

        // ============================================================
        // PRODUITS
        // ============================================================
        case 'save_product': {
            $id   = $input['id']   ?? bin2hex(random_bytes(6));
            $nom  = trim($input['nom'] ?? '');
            $prix = (float)($input['prix'] ?? 0);
            $cat  = trim($input['categorie'] ?? '') ?: 'Autres';
            if ($nom === '') err('Nom de produit requis');
            $stmt = $pdo->prepare("
                INSERT INTO produits (id, nom, prix, categorie) VALUES (?,?,?,?)
                ON DUPLICATE KEY UPDATE nom = VALUES(nom), prix = VALUES(prix), categorie = VALUES(categorie)
            ");
            $stmt->execute([$id, $nom, $prix, $cat]);
            out(['ok' => true, 'id' => $id]);
            break;
        }
        case 'delete_product': {
            $stmt = $pdo->prepare("DELETE FROM produits WHERE id = ?");
            $stmt->execute([$input['id'] ?? '']);
            out(['ok' => true]);
            break;
        }

        // ============================================================
        // CHAUFFEURS
        // ============================================================
        case 'save_driver': {
            $id   = $input['id'] ?? bin2hex(random_bytes(6));
            $nom  = trim($input['nom'] ?? '');
            $user = trim($input['utilisateur'] ?? '');
            $pass = (string)($input['mot_de_passe'] ?? '0000');
            if ($nom === '' || $user === '') err('Nom et utilisateur requis');
            $stmt = $pdo->prepare("
                INSERT INTO chauffeurs (id, nom, utilisateur, mot_de_passe) VALUES (?,?,?,?)
                ON DUPLICATE KEY UPDATE nom = VALUES(nom), utilisateur = VALUES(utilisateur), mot_de_passe = VALUES(mot_de_passe)
            ");
            $stmt->execute([$id, $nom, $user, $pass]);
            out(['ok' => true, 'id' => $id]);
            break;
        }
        case 'delete_driver': {
            $count = $pdo->query("SELECT COUNT(*) FROM chauffeurs")->fetchColumn();
            if ($count <= 1) err('Il faut au moins un chauffeur');
            $stmt = $pdo->prepare("DELETE FROM chauffeurs WHERE id = ?");
            $stmt->execute([$input['id'] ?? '']);
            out(['ok' => true]);
            break;
        }

        // ============================================================
        // CLIENTS
        // ============================================================
        case 'save_client': {
            $id  = $input['id'] ?? bin2hex(random_bytes(6));
            $nom = trim($input['nom'] ?? '');
            $drv = $input['chauffeur_id'] ?? '';
            if ($nom === '' || $drv === '') err('Nom et chauffeur requis');
            $stmt = $pdo->prepare("
                INSERT INTO clients (id, nom, chauffeur_id) VALUES (?,?,?)
                ON DUPLICATE KEY UPDATE nom = VALUES(nom), chauffeur_id = VALUES(chauffeur_id)
            ");
            $stmt->execute([$id, $nom, $drv]);
            out(['ok' => true, 'id' => $id]);
            break;
        }
        case 'delete_client': {
            $stmt = $pdo->prepare("DELETE FROM clients WHERE id = ?");
            $stmt->execute([$input['id'] ?? '']);
            out(['ok' => true]);
            break;
        }

        // ============================================================
        // LIVRAISONS (validation Laboratoire — plusieurs lignes d'un coup)
        // ============================================================
        case 'add_deliveries': {
            $rows = $input['rows'] ?? [];
            if (!is_array($rows) || count($rows) === 0) err('Aucune ligne à enregistrer');
            $stmt = $pdo->prepare("
                INSERT INTO livraisons (id, jour, client_id, chauffeur_id, produit_id, produit_nom, quantite, prix_unitaire)
                VALUES (?,?,?,?,?,?,?,?)
            ");
            $pdo->beginTransaction();
            $ids = [];
            foreach ($rows as $i => $r) {
                $jour        = $r['date']        ?? '';
                $clientId    = $r['clientId']    ?? '';
                $driver      = $r['driver']      ?? '';
                $productId   = $r['productId']   ?? '';
                $productName = trim($r['productName'] ?? '');
                $quantity    = (int)($r['quantity']  ?? 0);
                $unitPrice   = (float)($r['unitPrice'] ?? 0);
                if ($jour===''||$clientId===''||$driver===''||$productId===''||$productName===''||$quantity<=0) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    err("Ligne $i invalide : date, client, chauffeur, produit et quantité (>0) sont obligatoires");
                }
                $newId = bin2hex(random_bytes(6));
                $stmt->execute([$newId, $jour, $clientId, $driver, $productId, $productName, $quantity, $unitPrice]);
                $ids[] = $newId;
            }
            $pdo->commit();
            out(['ok' => true, 'count' => count($rows), 'ids' => $ids]);
            break;
        }
        case 'delete_delivery': {
            $stmt = $pdo->prepare("DELETE FROM livraisons WHERE id = ?");
            $stmt->execute([$input['id'] ?? '']);
            out(['ok' => true]);
            break;
        }
        // Modification d'une commande existante (utilisé par le Propriétaire)
        case 'update_delivery': {
            $id          = $input['id']          ?? '';
            $jour        = $input['date']        ?? '';
            $clientId    = $input['clientId']    ?? '';
            $driver      = $input['driver']      ?? '';
            $productId   = $input['productId']   ?? '';
            $productName = trim($input['productName'] ?? '');
            $quantity    = (int)($input['quantity']  ?? 0);
            $unitPrice   = (float)($input['unitPrice'] ?? 0);
            if ($id===''||$jour===''||$clientId===''||$driver===''||$productId===''||$productName===''||$quantity<=0) {
                err('Champs obligatoires manquants ou quantité invalide');
            }
            $stmt = $pdo->prepare("
                UPDATE livraisons
                SET jour = ?, client_id = ?, chauffeur_id = ?, produit_id = ?, produit_nom = ?, quantite = ?, prix_unitaire = ?
                WHERE id = ?
            ");
            $stmt->execute([$jour, $clientId, $driver, $productId, $productName, $quantity, $unitPrice, $id]);
            out(['ok' => true]);
            break;
        }

        // ============================================================
        // RETOURS (articles non conformes rendus par un client,
        // toujours liés à une livraison précise — réduit le montant dû)
        // ============================================================
        case 'add_return': {
            $livraisonId = $input['livraisonId'] ?? '';
            $qty = (int)($input['quantity'] ?? 0);
            $jour = $input['date'] ?? '';
            if ($livraisonId === '' || $qty <= 0 || $jour === '') err('Livraison, quantité et date requises');

            // Vérifie qu'on ne retourne pas plus que ce qui a été livré (moins les retours déjà enregistrés)
            $stmt = $pdo->prepare("SELECT quantite FROM livraisons WHERE id = ?");
            $stmt->execute([$livraisonId]);
            $livQty = $stmt->fetchColumn();
            if ($livQty === false) err('Livraison introuvable');

            $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantite),0) FROM retours WHERE livraison_id = ?");
            $stmt->execute([$livraisonId]);
            $alreadyReturned = (int)$stmt->fetchColumn();

            if ($alreadyReturned + $qty > (int)$livQty) {
                err('Quantité retournée supérieure à la quantité livrée restante (' . ((int)$livQty - $alreadyReturned) . ' disponible)');
            }

            $newId = bin2hex(random_bytes(6));
            $stmt = $pdo->prepare("INSERT INTO retours (id, livraison_id, jour, quantite, motif) VALUES (?,?,?,?,?)");
            $stmt->execute([$newId, $livraisonId, $jour, $qty, $input['motif'] ?? null]);
            out(['ok' => true, 'id' => $newId]);
            break;
        }
        case 'delete_return': {
            $stmt = $pdo->prepare("DELETE FROM retours WHERE id = ?");
            $stmt->execute([$input['id'] ?? '']);
            out(['ok' => true]);
            break;
        }

        // ============================================================
        // ENCAISSEMENTS (validation tournée chauffeur — remplace le jour/chauffeur)
        // ============================================================
        case 'set_payments_for_day': {
            $jour     = $input['date']   ?? '';
            $driverId = $input['driver'] ?? '';
            $rows     = $input['rows']   ?? [];
            if ($jour === '' || $driverId === '') err('Date et chauffeur requis');

            $pdo->beginTransaction();
            $del = $pdo->prepare("DELETE FROM encaissements WHERE jour = ? AND chauffeur_id = ?");
            $del->execute([$jour, $driverId]);

            $ins = $pdo->prepare("
                INSERT INTO encaissements (id, jour, client_id, chauffeur_id, montant) VALUES (?,?,?,?,?)
            ");
            foreach ($rows as $r) {
                $ins->execute([
                    bin2hex(random_bytes(6)), $jour, $r['clientId'], $driverId, (float)$r['amount'],
                ]);
            }
            $pdo->commit();
            out(['ok' => true]);
            break;
        }

        // ============================================================
        // REMISE À ZÉRO D'UNE JOURNÉE (après sauvegarde du tableau)
        // ============================================================
        case 'reset_day': {
            $jour = $input['date'] ?? '';
            if ($jour === '') err('Date requise');
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM livraisons WHERE jour = ?")->execute([$jour]);
            $pdo->prepare("DELETE FROM encaissements WHERE jour = ?")->execute([$jour]);
            $pdo->commit();
            out(['ok' => true]);
            break;
        }

        // ============================================================
        // RAPPORTS JOURNALIERS (sauvegarde + purge auto > 90 jours)
        // ============================================================
        case 'save_report': {
            $jour   = $input['date'] ?? '';
            if ($jour === '') err('Date requise');
            $stmt = $pdo->prepare("
                INSERT INTO rapports_jour (jour, rempli, recupere, ecart, detail_json)
                VALUES (?,?,?,?,?)
                ON DUPLICATE KEY UPDATE rempli=VALUES(rempli), recupere=VALUES(recupere),
                                        ecart=VALUES(ecart), detail_json=VALUES(detail_json)
            ");
            $stmt->execute([
                $jour, (float)$input['rempli'], (float)$input['recupere'], (float)$input['ecart'],
                json_encode($input['detail'] ?? [], JSON_UNESCAPED_UNICODE),
            ]);
            out(['ok' => true]);
            break;
        }
        case 'delete_report': {
            $stmt = $pdo->prepare("DELETE FROM rapports_jour WHERE jour = ?");
            $stmt->execute([$input['date'] ?? '']);
            out(['ok' => true]);
            break;
        }

        // ============================================================
        // TARIFS SPÉCIAUX PAR CLIENT
        // ============================================================

        // Lecture seule et légère : permet aux autres appareils (ex: poste Labo resté
        // ouvert) de rafraîchir les tarifs sans recharger toute l'application.
        case 'get_client_prices': {
            $rows = $pdo->query("SELECT client_id, produit_id, prix FROM prix_client")->fetchAll();
            foreach ($rows as &$r) { $r['prix'] = (float)$r['prix']; }
            out(['ok' => true, 'prix_client' => $rows]);
            break;
        }

        // Auto-réparation idempotente de la table prix_client.
        // Cause racine historique : sur une base créée par une ancienne version, la table
        // pouvait ne pas avoir de PRIMARY KEY (client_id, produit_id). Des doublons
        // s'accumulaient alors et l'ANCIEN prix (première ligne trouvée) l'emportait.
        // Cet endpoint déduplique et garantit la clé primaire. No-op si déjà propre.
        case 'repair_prix_client': {
            // Sérialise d'éventuelles réparations concurrentes (deux appareils au démarrage)
            $pdo->query("SELECT GET_LOCK('repair_prix_client', 5)");
            try {
                rebuild_prix_client($pdo);
                $pdo->query("SELECT RELEASE_LOCK('repair_prix_client')");
                out(['ok' => true, 'repaired' => true]);
            } catch (Exception $e) {
                $pdo->query("SELECT RELEASE_LOCK('repair_prix_client')");
                throw $e;
            }
            break;
        }

        // Diagnostic : renvoie la structure RÉELLE de la table + toutes ses lignes.
        // Permet de voir l'état exact en production (schéma, clé primaire, lignes
        // fantômes) sans accès direct à la base.
        case 'debug_prix_client': {
            $schema = '(introuvable)';
            try { $r = $pdo->query("SHOW CREATE TABLE prix_client")->fetch(PDO::FETCH_NUM); $schema = $r[1] ?? '(vide)'; } catch (Exception $e) { $schema = 'ERREUR: '.$e->getMessage(); }
            $rows    = $pdo->query("SELECT client_id, produit_id, prix FROM prix_client ORDER BY client_id, produit_id")->fetchAll();
            $garbage = (int)$pdo->query("SELECT COUNT(*) FROM prix_client WHERE client_id = '' OR produit_id = ''")->fetchColumn();
            out(['ok' => true, 'schema' => $schema, 'nb_lignes' => count($rows), 'lignes_fantomes' => $garbage, 'rows' => $rows]);
            break;
        }

        case 'save_client_price': {
            $clientId  = $input['clientId']  ?? '';
            $produitId = $input['produitId'] ?? '';
            $prix      = (float)($input['prix'] ?? 0);
            if ($clientId === '' || $produitId === '') err('Client et produit requis');

            // Écriture avec auto-guérison : si l'écriture échoue pour une raison
            // d'intégrité (clé manquante/mauvaise, doublon, ligne fantôme), on
            // reconstruit la table proprement puis on réessaie une fois.
            $writeOne = function() use ($pdo, $clientId, $produitId, $prix) {
                $pdo->exec("DELETE FROM prix_client WHERE client_id = '' OR produit_id = ''");
                $pdo->prepare("DELETE FROM prix_client WHERE client_id = ? AND produit_id = ?")
                    ->execute([$clientId, $produitId]);
                if ($prix > 0) {
                    $pdo->prepare("INSERT INTO prix_client (client_id, produit_id, prix) VALUES (?,?,?)")
                        ->execute([$clientId, $produitId, $prix]);
                }
            };
            try {
                $writeOne();
            } catch (PDOException $e) {
                rebuild_prix_client($pdo);
                $writeOne();
            }
            out(['ok' => true]);
            break;
        }

        // Enregistrement groupé : plusieurs tarifs pour un même client.
        case 'save_client_prices': {
            $clientId = $input['clientId'] ?? '';
            $items    = $input['items']    ?? [];
            if ($clientId === '') err('Client requis');
            if (!is_array($items)) err('Liste de tarifs invalide');

            // La transaction est encapsulée pour pouvoir être REJOUÉE après une
            // reconstruction de la table en cas d'échec d'intégrité.
            $doBatch = function() use ($pdo, $clientId, $items) {
                $pdo->beginTransaction();
                try {
                    // Purge défensive des lignes fantômes (client/produit vide).
                    $pdo->exec("DELETE FROM prix_client WHERE client_id = '' OR produit_id = ''");
                    $del = $pdo->prepare("DELETE FROM prix_client WHERE client_id = ? AND produit_id = ?");
                    $ins = $pdo->prepare("INSERT INTO prix_client (client_id, produit_id, prix) VALUES (?,?,?)");
                    foreach ($items as $it) {
                        $produitId = $it['produitId'] ?? '';
                        if ($produitId === '') continue;         // jamais de clé vide
                        $prix = (float)($it['prix'] ?? 0);
                        $del->execute([$clientId, $produitId]);
                        if ($prix > 0) $ins->execute([$clientId, $produitId, $prix]);
                    }
                    $pdo->commit();
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    throw $e;
                }
            };

            try {
                $doBatch();
            } catch (PDOException $e) {
                // Auto-guérison : l'échec vient d'un état de table hérité (clé
                // manquante/mauvaise, doublon, ligne fantôme). On reconstruit une
                // table propre (hors transaction — les DDL committent implicitement)
                // puis on rejoue l'enregistrement. S'il échoue ENCORE, l'erreur
                // réelle est propagée au client (message détaillé).
                rebuild_prix_client($pdo);
                $doBatch();
            }
            out(['ok' => true]);
            break;
        }

        // ============================================================
        // PARAMÈTRES (nom établissement, monnaie, identifiants)
        // ============================================================
        case 'save_settings': {
            $map = $input['parametres'] ?? [];
            $stmt = $pdo->prepare("
                INSERT INTO parametres (cle, valeur) VALUES (?,?)
                ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)
            ");
            $pdo->beginTransaction();
            foreach ($map as $cle => $valeur) {
                $stmt->execute([$cle, (string)$valeur]);
            }
            $pdo->commit();
            out(['ok' => true]);
            break;
        }

        // ============================================================
        // RESTAURATION D'UNE SAUVEGARDE COMPLÈTE (fichier JSON)
        // ============================================================
        case 'restore_backup': {
            $data = $input;
            $pdo->beginTransaction();
            $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
            try {
                foreach (['retours','encaissements','livraisons','clients','chauffeurs','produits','rapports_jour','prix_client'] as $t) {
                    $pdo->exec("DELETE FROM $t");
                }

                $stmtP = $pdo->prepare("INSERT INTO produits (id,nom,prix,categorie) VALUES (?,?,?,?)");
                foreach ($data['products'] ?? [] as $p) $stmtP->execute([$p['id'],$p['name'],(float)$p['price'],$p['category'] ?? 'Autres']);

                $stmtD = $pdo->prepare("INSERT INTO chauffeurs (id,nom,utilisateur,mot_de_passe) VALUES (?,?,?,?)");
                foreach ($data['settings']['drivers'] ?? [] as $d) $stmtD->execute([$d['id'],$d['name'],$d['user'] ?? $d['id'],$d['pass'] ?? '0000']);

                $stmtC = $pdo->prepare("INSERT INTO clients (id,nom,chauffeur_id) VALUES (?,?,?)");
                foreach ($data['clients'] ?? [] as $c) $stmtC->execute([$c['id'],$c['name'],$c['driver']]);

                $stmtL = $pdo->prepare("INSERT INTO livraisons (id,jour,client_id,chauffeur_id,produit_id,produit_nom,quantite,prix_unitaire) VALUES (?,?,?,?,?,?,?,?)");
                foreach ($data['deliveries'] ?? [] as $l) $stmtL->execute([$l['id'],$l['date'],$l['clientId'],$l['driver'],$l['productId'],$l['productName'],(int)$l['quantity'],(float)$l['unitPrice']]);

                $stmtRet = $pdo->prepare("INSERT INTO retours (id,livraison_id,jour,quantite,motif) VALUES (?,?,?,?,?)");
                foreach ($data['returns'] ?? [] as $rt) $stmtRet->execute([$rt['id'],$rt['livraisonId'],$rt['date'],(int)$rt['quantity'],$rt['reason'] ?? null]);

                $stmtE = $pdo->prepare("INSERT INTO encaissements (id,jour,client_id,chauffeur_id,montant) VALUES (?,?,?,?,?)");
                foreach ($data['payments'] ?? [] as $p) $stmtE->execute([$p['id'],$p['date'],$p['clientId'],$p['driver'],(float)$p['amount']]);

                $stmtR = $pdo->prepare("INSERT INTO rapports_jour (jour,rempli,recupere,ecart,detail_json) VALUES (?,?,?,?,?)");
                foreach ($data['reports'] ?? [] as $r) $stmtR->execute([$r['date'],(float)$r['rempli'],(float)$r['rec'],(float)$r['ecart'],json_encode($r['rows'] ?? [], JSON_UNESCAPED_UNICODE)]);

                $stmtPC = $pdo->prepare("INSERT INTO prix_client (client_id, produit_id, prix) VALUES (?,?,?)");
                foreach ($data['clientPrices'] ?? [] as $pc) $stmtPC->execute([$pc['clientId'], $pc['productId'], (float)$pc['prix']]);

                $settings = $data['settings'] ?? [];
                $stmtS = $pdo->prepare("INSERT INTO parametres (cle,valeur) VALUES (?,?) ON DUPLICATE KEY UPDATE valeur=VALUES(valeur)");
                $stmtS->execute(['business', $settings['business'] ?? 'Ma Pâtisserie']);
                $stmtS->execute(['currency', $settings['currency'] ?? 'DT']);
                $stmtS->execute(['auth_labo_user', $settings['auth']['labo']['user'] ?? 'labo']);
                $stmtS->execute(['auth_labo_pass', $settings['auth']['labo']['pass'] ?? '0000']);
                $stmtS->execute(['auth_proprio_user', $settings['auth']['proprietaire']['user'] ?? 'admin']);
                $stmtS->execute(['auth_proprio_pass', $settings['auth']['proprietaire']['pass'] ?? '9999']);

                $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            out(['ok' => true]);
            break;
        }

        default:
            err('Action inconnue : ' . $action, 404);
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Erreur base de données [' . $action . '] : ' . $e->getMessage());
    // Le message réel est renvoyé au client (pas seulement dans le log serveur) :
    // sans ça, toute panne future reste invisible et impossible à diagnostiquer
    // à distance — exactement ce qui a empêché de trancher le cas précédent.
    err('Erreur base de données [' . $action . '] : ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Erreur serveur [' . $action . '] : ' . $e->getMessage());
    err('Erreur serveur [' . $action . '] : ' . $e->getMessage(), 500);
}
