<?php
// MGT CRM — API v4 — atelier.rybsen.fr
// Configuration MySQL Hostinger
define('DB_HOST', 'localhost');
define('DB_NAME', 'u293743867_mgtcrm');
define('DB_USER', 'u293743867_mgtuser');
define('DB_PASS', 'Mgt2026!');

// Afficher les erreurs PHP (aide au debug)
error_reporting(E_ALL);
ini_set('display_errors', 0); // 0 = masqué en prod, erreurs dans log serveur
ini_set('log_errors', 1);

// Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Session-Token');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// Connexion BDD
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Connexion BDD impossible: ' . $e->getMessage()]);
    exit;
}

$action = trim($_GET['action'] ?? '');
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

// Token de session
$token = $_SERVER['HTTP_X_SESSION_TOKEN'] ?? '';

// ── Fonctions session ──
function getSession($pdo, $token) {
    if (!$token || strlen($token) < 10) return null;
    try {
        $s = $pdo->prepare("SELECT s.user_id, s.role, u.login FROM mgt_sessions s JOIN mgt_users u ON u.id = s.user_id WHERE s.token = ? AND s.expires_at > NOW() LIMIT 1");
        $s->execute([$token]);
        $sess = $s->fetch();
        if ($sess) {
            $pdo->prepare("UPDATE mgt_sessions SET expires_at = DATE_ADD(NOW(), INTERVAL 8 HOUR) WHERE token = ?")->execute([$token]);
        }
        return $sess ?: null;
    } catch (Exception $e) { return null; }
}

function needAuth($pdo, $token) {
    $sess = getSession($pdo, $token);
    if (!$sess) {
        http_response_code(401);
        echo json_encode(['error' => 'Non connecté', 'code' => 'AUTH']);
        exit;
    }
    return $sess;
}

function needAdmin($pdo, $token) {
    $sess = needAuth($pdo, $token);
    if ($sess['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Accès admin requis', 'code' => 'FORBIDDEN']);
        exit;
    }
    return $sess;
}

function clean($v, $len = 200) {
    return mb_substr(trim(strip_tags((string)$v)), 0, $len);
}

// ── Router ──
try {
    switch ($action) {

        // ─ SETUP ─
        case 'check':
            $tables = $pdo->query("SHOW TABLES LIKE 'mgt_%'")->fetchAll(PDO::FETCH_COLUMN);
            echo json_encode(['ok' => true, 'tables' => count($tables), 'list' => $tables]);
            break;

        case 'init':
            createTables($pdo);
            echo json_encode(['ok' => true]);
            break;

        // ─ AUTH ─
        case 'login':
            $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $login    = strtolower(clean($body['login'] ?? ''));
            $pass     = $body['pass'] ?? '';

            // Anti brute-force simple
            try {
                $pdo->prepare("DELETE FROM mgt_login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)")->execute();
                $attempts = (int)$pdo->prepare("SELECT COUNT(*) FROM mgt_login_attempts WHERE ip = ?")->execute([$ip]) ? $pdo->query("SELECT COUNT(*) FROM mgt_login_attempts WHERE ip = '$ip'")->fetchColumn() : 0;
            } catch (Exception $e) { $attempts = 0; }

            if ($attempts >= 10) {
                http_response_code(429);
                echo json_encode(['error' => 'Trop de tentatives. Attendez 5 minutes.']);
                break;
            }

            if (!$login || !$pass) {
                http_response_code(400);
                echo json_encode(['error' => 'Identifiant et mot de passe requis.']);
                break;
            }

            $s = $pdo->prepare("SELECT id, login, pass, role FROM mgt_users WHERE login = ? LIMIT 1");
            $s->execute([$login]);
            $user = $s->fetch();

            if (!$user || $user['pass'] !== $pass) {
                try { $pdo->prepare("INSERT INTO mgt_login_attempts (ip, attempted_at) VALUES (?, NOW())")->execute([$ip]); } catch (Exception $e) {}
                usleep(rand(100000, 300000));
                http_response_code(401);
                echo json_encode(['error' => 'Identifiant ou mot de passe incorrect.']);
                break;
            }

            try { $pdo->prepare("DELETE FROM mgt_login_attempts WHERE ip = ?")->execute([$ip]); } catch (Exception $e) {}
            // Nettoyer vieilles sessions
            try { $pdo->prepare("DELETE FROM mgt_sessions WHERE expires_at < NOW()")->execute(); } catch (Exception $e) {}

            $tok = bin2hex(random_bytes(32));
            $pdo->prepare("INSERT INTO mgt_sessions (token, user_id, role, ip, expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 8 HOUR))")
                ->execute([$tok, $user['id'], $user['role'], $ip]);

            echo json_encode([
                'ok'    => true,
                'token' => $tok,
                'user'  => ['id' => $user['id'], 'login' => $user['login'], 'role' => $user['role']],
            ]);
            break;

        case 'logout':
            if ($token) {
                try { $pdo->prepare("DELETE FROM mgt_sessions WHERE token = ?")->execute([$token]); } catch (Exception $e) {}
            }
            echo json_encode(['ok' => true]);
            break;

        case 'session_check':
            $sess = getSession($pdo, $token);
            if (!$sess) { http_response_code(401); echo json_encode(['ok' => false]); }
            else echo json_encode(['ok' => true, 'role' => $sess['role'], 'login' => $sess['login'], 'user_id' => $sess['user_id']]);
            break;

        // ─ LOAD ─
        case 'load':
            $sess    = needAuth($pdo, $token);
            $isAdmin = ($sess['role'] === 'admin');
            $data = [
                'clients' => $pdo->query("SELECT * FROM mgt_clients ORDER BY name")->fetchAll(),
                'formats' => $pdo->query("SELECT * FROM mgt_formats ORDER BY dim")->fetchAll(),
                'types'   => $pdo->query("SELECT * FROM mgt_types ORDER BY name")->fetchAll(),
                'entries' => $pdo->query("SELECT * FROM mgt_entries ORDER BY ts DESC LIMIT 2000")->fetchAll(),
                'prices'  => $isAdmin ? $pdo->query("SELECT * FROM mgt_prices")->fetchAll() : [],
                'cost'    => $isAdmin ? $pdo->query("SELECT * FROM mgt_costs")->fetchAll() : [],
                'users'   => $isAdmin
                    ? $pdo->query("SELECT id, login, pass, role FROM mgt_users")->fetchAll()
                    : $pdo->query("SELECT id, login, role FROM mgt_users")->fetchAll(),
            ];
            echo json_encode($data);
            break;

        // ─ ENTRIES ─
        case 'entry_add':
            $sess = needAuth($pdo, $token);
            $id   = substr(preg_replace('/[^a-z0-9]/', '', strtolower($body['id'] ?? '')), 0, 20);
            if (!$id) $id = bin2hex(random_bytes(6));
            $s = $pdo->prepare("INSERT INTO mgt_entries (id,ts,client_id,format_id,type_id,client_name,format_dim,type_name,qty,waste,operator) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            $s->execute([
                $id,
                clean($body['ts'] ?? date('Y-m-d H:i:s'), 20),
                clean($body['client_id'] ?? ''),
                clean($body['format_id'] ?? ''),
                clean($body['type_id'] ?? ''),
                clean($body['client_name'] ?? ''),
                clean($body['format_dim'] ?? '', 100),
                clean($body['type_name'] ?? ''),
                max(0, (int)($body['qty'] ?? 0)),
                max(0, (int)($body['waste'] ?? 0)),
                clean($sess['login'], 100),
            ]);
            echo json_encode(['ok' => true, 'id' => $id]);
            break;

        case 'entry_update':
            $sess    = needAuth($pdo, $token);
            $entryId = clean($body['id'] ?? '');
            if ($sess['role'] !== 'admin') {
                $chk = $pdo->prepare("SELECT operator, DATE(ts) as day FROM mgt_entries WHERE id = ? LIMIT 1");
                $chk->execute([$entryId]);
                $row = $chk->fetch();
                if (!$row || $row['operator'] !== $sess['login'] || $row['day'] !== date('Y-m-d')) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Modification non autorisée.']);
                    break;
                }
            }
            $s = $pdo->prepare("UPDATE mgt_entries SET client_id=?,format_id=?,type_id=?,client_name=?,format_dim=?,type_name=?,qty=?,waste=? WHERE id=?");
            $s->execute([
                clean($body['client_id'] ?? ''), clean($body['format_id'] ?? ''), clean($body['type_id'] ?? ''),
                clean($body['client_name'] ?? ''), clean($body['format_dim'] ?? '', 100), clean($body['type_name'] ?? ''),
                max(0, (int)($body['qty'] ?? 0)), max(0, (int)($body['waste'] ?? 0)), $entryId,
            ]);
            echo json_encode(['ok' => true]);
            break;

        case 'entry_delete':
            needAdmin($pdo, $token);
            $pdo->prepare("DELETE FROM mgt_entries WHERE id = ?")->execute([clean($body['id'] ?? '')]);
            echo json_encode(['ok' => true]);
            break;

        case 'entries_clear':
            needAdmin($pdo, $token);
            $pdo->exec("DELETE FROM mgt_entries");
            echo json_encode(['ok' => true]);
            break;

        // ─ CLIENTS ─
        case 'client_add':
            needAuth($pdo, $token);
            $name = strtoupper(clean($body['name'] ?? ''));
            if (!$name) { http_response_code(400); echo json_encode(['error' => 'Nom requis.']); break; }
            $id = clean($body['id'] ?? bin2hex(random_bytes(6)));
            $pdo->prepare("INSERT IGNORE INTO mgt_clients (id,name) VALUES (?,?)")->execute([$id, $name]);
            echo json_encode(['ok' => true]);
            break;

        case 'client_delete':
            needAdmin($pdo, $token);
            $pdo->prepare("DELETE FROM mgt_clients WHERE id=?")->execute([clean($body['id'] ?? '')]);
            echo json_encode(['ok' => true]);
            break;

        // ─ FORMATS ─
        case 'format_add':
            needAuth($pdo, $token);
            $dim = strtoupper(clean($body['dim'] ?? '', 100));
            if (!$dim) { http_response_code(400); echo json_encode(['error' => 'Dimension requise.']); break; }
            $id = clean($body['id'] ?? bin2hex(random_bytes(6)));
            $pdo->prepare("INSERT IGNORE INTO mgt_formats (id,dim) VALUES (?,?)")->execute([$id, $dim]);
            echo json_encode(['ok' => true]);
            break;

        case 'format_delete':
            needAdmin($pdo, $token);
            $pdo->prepare("DELETE FROM mgt_formats WHERE id=?")->execute([clean($body['id'] ?? '')]);
            echo json_encode(['ok' => true]);
            break;

        // ─ TYPES ─
        case 'type_add':
            needAuth($pdo, $token);
            $name = strtoupper(clean($body['name'] ?? ''));
            if (!$name) { http_response_code(400); echo json_encode(['error' => 'Nom requis.']); break; }
            $id = clean($body['id'] ?? bin2hex(random_bytes(6)));
            $pdo->prepare("INSERT IGNORE INTO mgt_types (id,name) VALUES (?,?)")->execute([$id, $name]);
            echo json_encode(['ok' => true]);
            break;

        case 'type_delete':
            needAdmin($pdo, $token);
            $pdo->prepare("DELETE FROM mgt_types WHERE id=?")->execute([clean($body['id'] ?? '')]);
            echo json_encode(['ok' => true]);
            break;

        // ─ USERS ─
        case 'user_add':
            needAdmin($pdo, $token);
            $login = strtolower(clean($body['login'] ?? '', 100));
            $pass  = $body['pass'] ?? '';
            if (!$login || !$pass) { http_response_code(400); echo json_encode(['error' => 'Données manquantes.']); break; }
            $id = clean($body['id'] ?? bin2hex(random_bytes(6)));
            $chk = $pdo->prepare("SELECT id FROM mgt_users WHERE login=?");
            $chk->execute([$login]);
            if ($chk->fetch()) { http_response_code(409); echo json_encode(['error' => 'Identifiant déjà utilisé.']); break; }
            $pdo->prepare("INSERT INTO mgt_users (id,login,pass,role) VALUES (?,?,?,'operator')")->execute([$id, $login, $pass]);
            echo json_encode(['ok' => true]);
            break;

        case 'user_delete':
            needAdmin($pdo, $token);
            $pdo->prepare("DELETE FROM mgt_users WHERE id=? AND role!='admin'")->execute([clean($body['id'] ?? '')]);
            echo json_encode(['ok' => true]);
            break;

        case 'user_update_pass':
            $sess = needAuth($pdo, $token);
            $tid  = clean($body['id'] ?? '');
            if ($sess['role'] !== 'admin' && $sess['user_id'] !== $tid) {
                http_response_code(403); echo json_encode(['error' => 'Accès refusé.']); break;
            }
            $pdo->prepare("UPDATE mgt_users SET pass=? WHERE id=?")->execute([$body['pass'] ?? '', $tid]);
            echo json_encode(['ok' => true]);
            break;

        // ─ PRIX & COÛTS ─
        case 'price_save':
            needAdmin($pdo, $token);
            $pdo->prepare("INSERT INTO mgt_prices (client_id,format_id,price) VALUES (?,?,?) ON DUPLICATE KEY UPDATE price=VALUES(price)")
                ->execute([clean($body['client_id'] ?? ''), clean($body['format_id'] ?? ''), max(0, (float)($body['price'] ?? 0))]);
            echo json_encode(['ok' => true]);
            break;

        case 'cost_save':
            needAdmin($pdo, $token);
            $pdo->prepare("INSERT INTO mgt_costs (format_id,cost) VALUES (?,?) ON DUPLICATE KEY UPDATE cost=VALUES(cost)")
                ->execute([clean($body['format_id'] ?? ''), max(0, (float)($body['cost'] ?? 0))]);
            echo json_encode(['ok' => true]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Action inconnue.']);
    }

} catch (Exception $e) {
    error_log('[MGT CRM] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur: ' . $e->getMessage()]);
}

// ── Création des tables ──
function createTables($pdo) {
    // Tables une par une pour éviter erreur multi-statement
    $tables = [
        "CREATE TABLE IF NOT EXISTS mgt_clients (
            id VARCHAR(20) PRIMARY KEY,
            name VARCHAR(200) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS mgt_formats (
            id VARCHAR(20) PRIMARY KEY,
            dim VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS mgt_types (
            id VARCHAR(20) PRIMARY KEY,
            name VARCHAR(200) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS mgt_users (
            id VARCHAR(20) PRIMARY KEY,
            login VARCHAR(100) UNIQUE NOT NULL,
            pass VARCHAR(500) NOT NULL,
            role ENUM('admin','operator') DEFAULT 'operator',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS mgt_prices (
            client_id VARCHAR(20) NOT NULL,
            format_id VARCHAR(20) NOT NULL,
            price DECIMAL(10,3) DEFAULT 0,
            PRIMARY KEY (client_id, format_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS mgt_costs (
            format_id VARCHAR(20) PRIMARY KEY,
            cost DECIMAL(10,3) DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS mgt_entries (
            id VARCHAR(20) PRIMARY KEY,
            ts DATETIME NOT NULL,
            client_id VARCHAR(20), format_id VARCHAR(20), type_id VARCHAR(20),
            client_name VARCHAR(200), format_dim VARCHAR(100), type_name VARCHAR(200),
            qty INT DEFAULT 0, waste INT DEFAULT 0, operator VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ts (ts),
            INDEX idx_client (client_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS mgt_sessions (
            token VARCHAR(64) PRIMARY KEY,
            user_id VARCHAR(20) NOT NULL,
            role ENUM('admin','operator') NOT NULL,
            ip VARCHAR(45),
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS mgt_login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(45) NOT NULL,
            attempted_at DATETIME NOT NULL,
            INDEX idx_ip (ip, attempted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];

    foreach ($tables as $sql) {
        $pdo->exec($sql);
    }

    // Seed uniquement si la table users est vide
    $count = (int)$pdo->query("SELECT COUNT(*) FROM mgt_users")->fetchColumn();
    if ($count > 0) return;

    $uid = function() { return bin2hex(random_bytes(6)); };

    $clients = ['PROPRINT','FOCUS','IMP PREMIÈRE','COLORAMAPLUS','LA PRESSE','PROPAK'];
    $formats = ['510X400','605X745','790X1030','790X1030 (615X724)','1220X1460'];
    $types   = ['KODAK ULTRA','SERVICE GRAVURE FUJI ZX','SERVICE GRAVURE ZX','TP-GX'];

    foreach ($clients as $n)
        $pdo->prepare("INSERT IGNORE INTO mgt_clients (id,name) VALUES (?,?)")->execute([$uid(), $n]);
    foreach ($formats as $d)
        $pdo->prepare("INSERT IGNORE INTO mgt_formats (id,dim) VALUES (?,?)")->execute([$uid(), $d]);
    foreach ($types as $n)
        $pdo->prepare("INSERT IGNORE INTO mgt_types (id,name) VALUES (?,?)")->execute([$uid(), $n]);

    $pdo->prepare("INSERT IGNORE INTO mgt_users (id,login,pass,role) VALUES (?,?,?,'admin')")
        ->execute([$uid(), 'admin', base64_encode('mgt2026')]);
    $pdo->prepare("INSERT IGNORE INTO mgt_users (id,login,pass,role) VALUES (?,?,?,'operator')")
        ->execute([$uid(), 'operateur1', base64_encode('op123')]);
}
