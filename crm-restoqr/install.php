<!DOCTYPE html>
<?php
/**
 * install.php — Script d'installation unique pour Hostinger.
 *
 * USAGE :
 *   1. Uploader le ZIP sur le sous-domaine via hPanel File Manager
 *   2. Extraire à la racine du sous-domaine
 *   3. Visiter https://bar.rybsen.fr/install.php
 *   4. Remplir le formulaire et cliquer "Installer"
 *   5. Supprimer install.php via File Manager dès la fin (ou cliquer le bouton)
 *
 * SÉCURITÉ : ce fichier expose des informations sensibles pendant l'install.
 * Ne jamais le laisser accessible après installation.
 *
 * COMPATIBILITÉ : PHP 7.4+, MySQL 5.7+ / MariaDB 10.3+
 * Testé sur Hostinger Business Shared Hosting.
 */

// ─── Dès qu'on reçoit le formulaire, on traite AVANT tout affichage HTML ────
$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $result = handle_install();
}

function handle_install(): array {
    $db_host  = trim($_POST['db_host']  ?? 'localhost');
    $db_name  = trim($_POST['db_name']  ?? '');
    $db_user  = trim($_POST['db_user']  ?? '');
    $db_pass  = $_POST['db_pass']  ?? '';
    $resto_nom = trim($_POST['resto_nom'] ?? 'Mon Restaurant');
    $proprio_nom   = trim($_POST['proprio_nom']   ?? '');
    $proprio_email = trim($_POST['proprio_email'] ?? '');
    $proprio_pass  = $_POST['proprio_pass']  ?? '';
    $serveur_nom   = trim($_POST['serveur_nom']   ?? '');
    $serveur_email = trim($_POST['serveur_email'] ?? '');
    $serveur_pass  = $_POST['serveur_pass']  ?? '';
    $serveur_whatsapp = trim($_POST['serveur_whatsapp'] ?? '');
    $twilio_sid   = trim($_POST['twilio_sid']   ?? '');
    $twilio_token = trim($_POST['twilio_token'] ?? '');
    $twilio_from  = trim($_POST['twilio_from']  ?? '');
    $nb_tables    = max(1, min(50, (int)($_POST['nb_tables'] ?? 10)));
    $self_delete  = isset($_POST['self_delete']);

    $steps = [];
    $errors = [];

    // ── 1. Connexion MySQL ────────────────────────────────────────────────
    try {
        $pdo = new PDO(
            "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
            $db_user, $db_pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
        );
        $steps[] = ['ok', "Connexion à la base <strong>{$db_name}</strong> réussie."];
    } catch (PDOException $e) {
        return ['success' => false, 'errors' => ["Connexion MySQL impossible : " . htmlspecialchars($e->getMessage())], 'steps' => []];
    }

    // ── 2. Création des tables (UNE PAR UNE — règle Hostinger) ──────────
    $tables_sql = get_tables_sql();
    foreach ($tables_sql as $label => $sql) {
        try {
            $pdo->exec($sql);
            $steps[] = ['ok', "Table <code>{$label}</code> créée ou déjà présente."];
        } catch (PDOException $e) {
            $errors[] = "Erreur sur table {$label} : " . htmlspecialchars($e->getMessage());
        }
    }
    if ($errors) {
        return ['success' => false, 'errors' => $errors, 'steps' => $steps];
    }

    // ── 3. Restaurant ─────────────────────────────────────────────────────
    $pdo->prepare("INSERT IGNORE INTO restaurant (id, nom, devise, fuseau) VALUES (1, ?, 'DT', 'Africa/Tunis')")
        ->execute([$resto_nom]);
    $steps[] = ['ok', "Restaurant <strong>" . htmlspecialchars($resto_nom) . "</strong> configuré."];

    // ── 4. Zones ──────────────────────────────────────────────────────────
    foreach (['Salle', 'Terrasse'] as $zone) {
        $pdo->prepare("INSERT IGNORE INTO zones (restaurant_id, nom) VALUES (1, ?)")->execute([$zone]);
    }
    $zone_id = (int) $pdo->query("SELECT id FROM zones WHERE restaurant_id=1 LIMIT 1")->fetchColumn();
    $steps[] = ['ok', "Zones créées (Salle, Terrasse)."];

    // ── 5. Tables physiques avec QR tokens aléatoires ────────────────────
    $qr_tokens = [];
    $stmt_table = $pdo->prepare(
        "INSERT IGNORE INTO tables_restaurant (restaurant_id, zone_id, numero, qr_token)
         VALUES (1, ?, ?, ?)"
    );
    for ($i = 1; $i <= $nb_tables; $i++) {
        $token = bin2hex(random_bytes(16)); // 32 hex chars
        $z = ($i <= ceil($nb_tables / 2)) ? $zone_id : ($zone_id + 1);
        $stmt_table->execute([$z, $i, $token]);
        $qr_tokens[$i] = $token;
    }
    // Relit les vrais tokens (en cas d'IGNORE sur des tables existantes)
    $rows = $pdo->query("SELECT numero, qr_token FROM tables_restaurant WHERE restaurant_id=1 ORDER BY numero")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) { $qr_tokens[$r['numero']] = $r['qr_token']; }
    $steps[] = ['ok', "{$nb_tables} table(s) physique(s) créée(s) avec QR tokens."];

    // ── 6. Catégories et articles d'exemple ──────────────────────────────
    $pdo->exec("INSERT IGNORE INTO categories (id, restaurant_id, nom, ordre) VALUES
        (1, 1, 'Entrées', 1), (2, 1, 'Plats', 2), (3, 1, 'Boissons', 3)");
    $pdo->exec("INSERT IGNORE INTO articles (restaurant_id, categorie_id, nom, description, prix, disponible, ordre) VALUES
        (1, 1, 'Article d''exemple 1', 'Description à modifier depuis le dashboard.', 8.000, 1, 1),
        (1, 2, 'Plat du jour', 'Modifiable depuis l''interface propriétaire.', 15.000, 1, 1),
        (1, 3, 'Boisson fraîche', 'Eau, jus, soda au choix.', 3.500, 1, 1)");
    $steps[] = ['ok', "Menu d'exemple créé (3 articles — à personnaliser via le dashboard propriétaire)."];

    // ── 7. Utilisateurs (propriétaire + serveur) ─────────────────────────
    $hash_proprio  = password_hash($proprio_pass,  PASSWORD_DEFAULT);
    $hash_serveur  = password_hash($serveur_pass,  PASSWORD_DEFAULT);

    $pdo->prepare(
        "INSERT INTO utilisateurs (restaurant_id, nom, email, mot_de_passe_hash, role, zone_id, whatsapp_number)
         VALUES (1, ?, ?, ?, 'proprietaire', NULL, NULL)
         ON DUPLICATE KEY UPDATE nom=VALUES(nom), mot_de_passe_hash=VALUES(mot_de_passe_hash)"
    )->execute([$proprio_nom, $proprio_email, $hash_proprio]);

    $pdo->prepare(
        "INSERT INTO utilisateurs (restaurant_id, nom, email, mot_de_passe_hash, role, zone_id, whatsapp_number)
         VALUES (1, ?, ?, ?, 'serveur', ?, ?)
         ON DUPLICATE KEY UPDATE nom=VALUES(nom), mot_de_passe_hash=VALUES(mot_de_passe_hash)"
    )->execute([$serveur_nom, $serveur_email, $hash_serveur, $zone_id, $serveur_whatsapp ?: null]);

    $steps[] = ['ok', "Comptes créés : propriétaire <code>" . htmlspecialchars($proprio_email) . "</code> + serveur <code>" . htmlspecialchars($serveur_email) . "</code>."];

    // ── 8. Génération de config.php ───────────────────────────────────────
    $qr_secret = bin2hex(random_bytes(32));

    // Génération propre sans heredoc (évite l'interprétation PHP des variables dans le template)
    $config_lines = [
        '<?php',
        '/**',
        ' * config.php — Généré automatiquement par install.php le ' . date('Y-m-d H:i:s'),
        ' * NE PAS versionner ce fichier (contient des credentials).',
        ' */',
        "define('DB_HOST', " . var_export($db_host, true) . ");",
        "define('DB_NAME', " . var_export($db_name, true) . ");",
        "define('DB_USER', " . var_export($db_user, true) . ");",
        "define('DB_PASS', " . var_export($db_pass, true) . ");",
        '',
        "define('APP_TIMEZONE', 'Africa/Tunis');",
        "define('APP_DEVISE',   'DT');",
        "define('SESSION_LIFETIME', 43200); // 12h",
    ];

    if ($twilio_sid && $twilio_token && $twilio_from) {
        $config_lines[] = "define('TWILIO_ACCOUNT_SID', " . var_export($twilio_sid, true) . ");";
        $config_lines[] = "define('TWILIO_AUTH_TOKEN',  " . var_export($twilio_token, true) . ");";
        $config_lines[] = "define('TWILIO_WHATSAPP_FROM', " . var_export($twilio_from, true) . ");";
    }

    $config_lines[] = "define('QR_SECRET', " . var_export($qr_secret, true) . ");";
    $config_lines[] = '';
    $config_lines[] = 'date_default_timezone_set(APP_TIMEZONE);';

    $config_content = implode("\n", $config_lines) . "\n";

    $config_path = __DIR__ . '/includes/config.php';
    if (file_put_contents($config_path, $config_content) === false) {
        $errors[] = "Impossible d'écrire includes/config.php. Vérifiez les permissions du dossier includes/.";
        return ['success' => false, 'errors' => $errors, 'steps' => $steps];
    }
    $steps[] = ['ok', "Fichier <code>includes/config.php</code> généré avec les credentials et la clé QR secrète."];

    // ── 9. .htaccess à la racine ──────────────────────────────────────────
    $htaccess = <<<'HTACCESS'
# QR-Menu — Sécurité Hostinger
Options -Indexes
ServerSignature Off

# Interdit l'accès direct aux fichiers sensibles
<FilesMatch "^(install\.php|\.env)$">
    Order deny,allow
    Deny from all
</FilesMatch>

# Protège includes/ et sql/
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^includes/ - [F,L]
    RewriteRule ^sql/ - [F,L]
</IfModule>

# En-têtes de sécurité
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>
HTACCESS;

    file_put_contents(__DIR__ . '/.htaccess', $htaccess);
    $steps[] = ['ok', "Fichier <code>.htaccess</code> de sécurité créé (accès aux répertoires sensibles bloqué)."];

    // ── 10. Auto-suppression si demandé ───────────────────────────────────
    if ($self_delete) {
        register_shutdown_function(function() {
            @unlink(__FILE__);
        });
        $steps[] = ['ok', "<strong>install.php se supprime automatiquement</strong> à la fin de cette page."];
    }

    return [
        'success'    => true,
        'steps'      => $steps,
        'qr_tokens'  => $qr_tokens,
        'proprio_email' => $proprio_email,
        'serveur_email' => $serveur_email,
        'twilio_configured' => (bool)$twilio_sid,
        'nb_tables' => $nb_tables,
    ];
}

/**
 * Retourne les CREATE TABLE dans l'ordre de dépendances, UN par entrée.
 * Règle Hostinger : exec() ne supporte pas les multi-statements → boucle PHP.
 */
function get_tables_sql(): array {
    return [
        'restaurant' => "CREATE TABLE IF NOT EXISTS restaurant (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(120) NOT NULL,
            devise VARCHAR(8) NOT NULL DEFAULT 'DT',
            fuseau VARCHAR(64) NOT NULL DEFAULT 'Africa/Tunis',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'zones' => "CREATE TABLE IF NOT EXISTS zones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            restaurant_id INT NOT NULL,
            nom VARCHAR(80) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (restaurant_id) REFERENCES restaurant(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'tables_restaurant' => "CREATE TABLE IF NOT EXISTS tables_restaurant (
            id INT AUTO_INCREMENT PRIMARY KEY,
            restaurant_id INT NOT NULL,
            zone_id INT NULL,
            numero INT NOT NULL,
            qr_token CHAR(32) NOT NULL UNIQUE,
            statut ENUM('libre','occupee','reservee') NOT NULL DEFAULT 'libre',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (restaurant_id) REFERENCES restaurant(id) ON DELETE CASCADE,
            FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE SET NULL,
            UNIQUE KEY uniq_table_resto (restaurant_id, numero)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'utilisateurs' => "CREATE TABLE IF NOT EXISTS utilisateurs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            restaurant_id INT NOT NULL,
            nom VARCHAR(100) NOT NULL,
            email VARCHAR(160) NOT NULL UNIQUE,
            mot_de_passe_hash VARCHAR(255) NOT NULL,
            role ENUM('proprietaire','serveur') NOT NULL,
            zone_id INT NULL,
            whatsapp_number VARCHAR(20) NULL,
            actif TINYINT(1) NOT NULL DEFAULT 1,
            derniere_connexion DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (restaurant_id) REFERENCES restaurant(id) ON DELETE CASCADE,
            FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'sessions' => "CREATE TABLE IF NOT EXISTS sessions (
            id CHAR(64) PRIMARY KEY,
            utilisateur_id INT NOT NULL,
            user_agent VARCHAR(255) NULL,
            ip VARCHAR(45) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'categories' => "CREATE TABLE IF NOT EXISTS categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            restaurant_id INT NOT NULL,
            nom VARCHAR(80) NOT NULL,
            ordre INT NOT NULL DEFAULT 0,
            FOREIGN KEY (restaurant_id) REFERENCES restaurant(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'articles' => "CREATE TABLE IF NOT EXISTS articles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            restaurant_id INT NOT NULL,
            categorie_id INT NOT NULL,
            nom VARCHAR(120) NOT NULL,
            description TEXT NULL,
            prix DECIMAL(8,3) NOT NULL,
            photo_url VARCHAR(255) NULL,
            disponible TINYINT(1) NOT NULL DEFAULT 1,
            ordre INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (restaurant_id) REFERENCES restaurant(id) ON DELETE CASCADE,
            FOREIGN KEY (categorie_id) REFERENCES categories(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'commandes' => "CREATE TABLE IF NOT EXISTS commandes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            restaurant_id INT NOT NULL,
            table_id INT NOT NULL,
            serveur_id INT NULL,
            code VARCHAR(10) NOT NULL,
            statut ENUM('nouvelle','en_cours','prete','servie','annulee') NOT NULL DEFAULT 'nouvelle',
            total DECIMAL(10,3) NOT NULL DEFAULT 0,
            note_client TEXT NULL,
            whatsapp_envoye TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            prise_en_charge_at DATETIME NULL,
            prete_at DATETIME NULL,
            servie_at DATETIME NULL,
            FOREIGN KEY (restaurant_id) REFERENCES restaurant(id) ON DELETE CASCADE,
            FOREIGN KEY (table_id) REFERENCES tables_restaurant(id) ON DELETE CASCADE,
            FOREIGN KEY (serveur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL,
            INDEX idx_statut (restaurant_id, statut),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'commande_items' => "CREATE TABLE IF NOT EXISTS commande_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            commande_id INT NOT NULL,
            article_id INT NULL,
            nom_article VARCHAR(120) NOT NULL,
            prix_unitaire DECIMAL(8,3) NOT NULL,
            quantite INT NOT NULL DEFAULT 1,
            note TEXT NULL,
            FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE,
            FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'whatsapp_logs' => "CREATE TABLE IF NOT EXISTS whatsapp_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            commande_id INT NOT NULL,
            destinataire VARCHAR(20) NOT NULL,
            statut ENUM('envoye','echec') NOT NULL,
            message_id VARCHAR(100) NULL,
            erreur TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'audit_log' => "CREATE TABLE IF NOT EXISTS audit_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            utilisateur_id INT NULL,
            action VARCHAR(100) NOT NULL,
            details TEXT NULL,
            ip VARCHAR(45) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];
}
?>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Installation — QR-Menu</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600&family=Inter:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:#EDE3D1;color:#2A211B;min-height:100vh;padding:30px 16px 60px}
.card{background:#FBF6EC;border-radius:20px;max-width:680px;margin:0 auto;padding:36px 32px;border:1px solid rgba(42,33,27,.1)}
h1{font-family:'Fraunces',serif;font-size:26px;font-weight:600;margin-bottom:4px}
.subtitle{font-size:13px;color:#8C6A52;margin-bottom:30px}
.section{margin-bottom:28px}
.section-title{font-size:11px;text-transform:uppercase;letter-spacing:.1em;font-weight:700;color:#8C6A52;
  border-bottom:1px solid rgba(42,33,27,.1);padding-bottom:8px;margin-bottom:16px}
.row{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px}
.row.one{grid-template-columns:1fr}
label{font-size:12px;font-weight:700;color:#8C6A52;display:block;margin-bottom:6px}
input,select{width:100%;padding:11px 13px;border-radius:10px;border:1px solid rgba(42,33,27,.15);
  background:#fff;font-family:'Inter',sans-serif;font-size:14px;color:#2A211B}
input:focus,select:focus{outline:none;border-color:#C1502E}
.hint{font-size:11.5px;color:#8C6A52;margin-top:5px;line-height:1.4}
.check-row{display:flex;align-items:center;gap:10px;padding:12px 14px;background:rgba(193,80,46,.07);
  border-radius:10px;border:1px solid rgba(193,80,46,.15);margin-top:6px}
.check-row input[type=checkbox]{width:16px;height:16px;flex:0 0 auto;accent-color:#C1502E}
.check-row label{margin:0;font-size:13px;font-weight:600;color:#C1502E}
.btn{width:100%;background:#2A211B;color:#F2E8D8;border:none;padding:15px;border-radius:13px;
  font-size:15px;font-weight:700;cursor:pointer;margin-top:8px}
.btn:active{transform:scale(.99)}

/* Résultat */
.result{margin-top:28px}
.step{display:flex;align-items:flex-start;gap:10px;padding:9px 0;border-bottom:1px solid rgba(42,33,27,.08);font-size:13.5px}
.step:last-child{border-bottom:none}
.step .ic{flex:0 0 20px;font-size:14px}
.step.ok .ic::before{content:'✓';color:#5B6B3F;font-weight:700}
.step.err .ic::before{content:'✗';color:#C1502E;font-weight:700}
.err-box{background:rgba(193,80,46,.1);border:1px solid rgba(193,80,46,.2);border-radius:12px;padding:14px 16px;margin-bottom:20px}
.err-box h3{color:#C1502E;font-size:14px;font-weight:700;margin-bottom:8px}
.err-box ul{padding-left:16px;font-size:13px;color:#2A211B}
.err-box ul li{margin-bottom:4px}
.success-box{background:rgba(91,107,63,.1);border:1px solid rgba(91,107,63,.2);border-radius:12px;padding:18px 20px;margin-bottom:24px}
.success-box h2{font-family:'Fraunces',serif;font-size:20px;color:#5B6B3F;margin-bottom:8px}
.success-box p{font-size:13.5px;line-height:1.6;color:#2A211B;margin-bottom:6px}

/* QR tokens table */
.qr-table{width:100%;border-collapse:collapse;margin:14px 0;font-size:13px}
.qr-table th{background:#2A211B;color:#F2E8D8;padding:8px 12px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.05em}
.qr-table td{padding:8px 12px;border-bottom:1px solid rgba(42,33,27,.08);vertical-align:middle}
.qr-table tr:last-child td{border-bottom:none}
.token{font-family:'DM Mono',monospace;font-size:11px;color:#8C6A52;word-break:break-all}
.url-chip{font-family:'DM Mono',monospace;font-size:11px;background:#2A211B;color:#F2E8D8;padding:3px 8px;border-radius:6px;word-break:break-all}
.copy-btn{background:#2A211B;color:#F2E8D8;border:none;padding:4px 10px;border-radius:6px;font-size:11px;cursor:pointer;white-space:nowrap}
.next-steps{background:#2A211B;border-radius:14px;padding:22px;margin-top:20px;color:#F2E8D8}
.next-steps h3{font-family:'Fraunces',serif;font-size:17px;margin-bottom:14px;color:#F2E8D8}
.next-steps ol{padding-left:18px;font-size:13.5px;line-height:1.8;color:rgba(242,232,216,.8)}
.next-steps ol li{margin-bottom:4px}
.next-steps a{color:#C1502E}
.delete-btn{width:100%;background:#C1502E;color:#fff;border:none;padding:13px;border-radius:11px;
  font-size:13.5px;font-weight:700;cursor:pointer;margin-top:16px}
</style>
</head>
<body>
<div class="card">
  <h1>🍽️ QR-Menu — Installation</h1>
  <div class="subtitle">Configurez votre application en remplissant ce formulaire une seule fois.</div>

<?php if ($result === null): // ─── FORMULAIRE ────────────────────────────── ?>
  <form method="POST" id="installForm">
    <input type="hidden" name="action" value="install">

    <div class="section">
      <div class="section-title">Base de données (hPanel Hostinger)</div>
      <div class="row">
        <div>
          <label>Hôte MySQL</label>
          <input name="db_host" value="localhost" required>
          <div class="hint">Presque toujours <code>localhost</code> sur Hostinger.</div>
        </div>
        <div>
          <label>Nom de la base</label>
          <input name="db_name" placeholder="u123456789_qrmenu" required>
        </div>
      </div>
      <div class="row">
        <div>
          <label>Utilisateur MySQL</label>
          <input name="db_user" placeholder="u123456789_qrmenu" required>
        </div>
        <div>
          <label>Mot de passe MySQL</label>
          <input type="password" name="db_pass" required>
        </div>
      </div>
    </div>

    <div class="section">
      <div class="section-title">Votre restaurant</div>
      <div class="row one">
        <div>
          <label>Nom du restaurant</label>
          <input name="resto_nom" placeholder="Le Médina" required>
        </div>
      </div>
      <div class="row one">
        <div>
          <label>Nombre de tables</label>
          <input type="number" name="nb_tables" value="10" min="1" max="50" required>
          <div class="hint">Vous pourrez en ajouter ultérieurement via phpMyAdmin.</div>
        </div>
      </div>
    </div>

    <div class="section">
      <div class="section-title">Compte Propriétaire</div>
      <div class="row one">
        <div>
          <label>Nom complet</label>
          <input name="proprio_nom" placeholder="Yassine Rezgui" required>
        </div>
      </div>
      <div class="row">
        <div>
          <label>Email de connexion</label>
          <input type="email" name="proprio_email" placeholder="proprio@lemedina.tn" required>
        </div>
        <div>
          <label>Mot de passe</label>
          <input type="password" name="proprio_pass" minlength="8" required>
          <div class="hint">8 caractères minimum.</div>
        </div>
      </div>
    </div>

    <div class="section">
      <div class="section-title">Premier Serveur</div>
      <div class="row one">
        <div>
          <label>Nom du serveur</label>
          <input name="serveur_nom" placeholder="Ahmed Ben Ali" required>
        </div>
      </div>
      <div class="row">
        <div>
          <label>Email de connexion</label>
          <input type="email" name="serveur_email" placeholder="ahmed@lemedina.tn" required>
        </div>
        <div>
          <label>Mot de passe</label>
          <input type="password" name="serveur_pass" minlength="8" required>
        </div>
      </div>
      <div class="row one">
        <div>
          <label>Numéro WhatsApp (avec indicatif)</label>
          <input name="serveur_whatsapp" placeholder="+21620123456">
          <div class="hint">Recevra les notifications de nouvelles commandes via Twilio.</div>
        </div>
      </div>
    </div>

    <div class="section">
      <div class="section-title">Twilio WhatsApp (notifications temps réel)</div>
      <div class="row">
        <div>
          <label>Account SID</label>
          <input name="twilio_sid" placeholder="ACxxxxxxxxxxxxxxxx">
        </div>
        <div>
          <label>Auth Token</label>
          <input type="password" name="twilio_token" placeholder="xxxxxxxxxxxxxxxx">
        </div>
      </div>
      <div class="row one">
        <div>
          <label>Numéro expéditeur WhatsApp</label>
          <input name="twilio_from" placeholder="whatsapp:+14155238886">
          <div class="hint">Numéro Twilio sandbox ou approuvé, format <code>whatsapp:+1xxxxxxxxxx</code>.</div>
        </div>
      </div>
    </div>

    <div class="section">
      <div class="section-title">Sécurité post-installation</div>
      <div class="check-row">
        <input type="checkbox" name="self_delete" id="self_delete" checked>
        <label for="self_delete">Supprimer install.php automatiquement après installation (recommandé)</label>
      </div>
    </div>

    <button type="submit" class="btn">🚀 Lancer l'installation</button>
  </form>

<?php elseif (!$result['success']): // ─── ERREUR ──────────────────────────── ?>
  <div class="err-box">
    <h3>❌ Installation incomplète</h3>
    <ul><?php foreach ($result['errors'] as $e): ?><li><?= $e ?></li><?php endforeach ?></ul>
  </div>
  <?php if (!empty($result['steps'])): ?>
    <div class="result">
      <?php foreach ($result['steps'] as $s): ?>
        <div class="step <?= $s[0] ?>"><span class="ic"></span><span><?= $s[1] ?></span></div>
      <?php endforeach ?>
    </div>
  <?php endif ?>
  <a href="install.php"><button class="btn" style="margin-top:20px;background:#8C6A52">← Réessayer</button></a>

<?php else: // ─── SUCCÈS ──────────────────────────────────────────────────── ?>
  <div class="success-box">
    <h2>✅ Installation réussie !</h2>
    <p>Toutes les tables ont été créées, les comptes configurés et <code>config.php</code> généré.</p>
    <?php if ($result['twilio_configured']): ?>
      <p>🟢 Twilio WhatsApp configuré — les notifications sont actives.</p>
    <?php else: ?>
      <p>🟡 Twilio non configuré — les commandes fonctionnent mais sans notification WhatsApp.</p>
    <?php endif ?>
  </div>

  <div class="result">
    <?php foreach ($result['steps'] as $s): ?>
      <div class="step <?= $s[0] ?>"><span class="ic"></span><span><?= $s[1] ?></span></div>
    <?php endforeach ?>
  </div>

  <div style="margin-top:28px">
    <div class="section-title">URLs QR Code par table</div>
    <p style="font-size:12.5px;color:#8C6A52;margin-bottom:12px">
      Générez le QR code de chaque URL ci-dessous sur
      <a href="https://qr.io" target="_blank">qr.io</a> ou
      <a href="https://goqr.me" target="_blank">goqr.me</a> et imprimez-les sur des supports de table.
    </p>
    <table class="qr-table">
      <tr><th>Table</th><th>URL à encoder dans le QR</th><th></th></tr>
      <?php
      $host = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
              . '://' . ($_SERVER['HTTP_HOST'] ?? 'votre-domaine.com');
      foreach ($result['qr_tokens'] as $num => $token):
        $url = $host . '/public/client/index.html?t=' . $token;
      ?>
      <tr>
        <td><strong>Table <?= $num ?></strong></td>
        <td><span class="url-chip" id="url-<?= $num ?>"><?= htmlspecialchars($url) ?></span></td>
        <td><button class="copy-btn" onclick="copyUrl('url-<?= $num ?>')">Copier</button></td>
      </tr>
      <?php endforeach ?>
    </table>
  </div>

  <div class="next-steps">
    <h3>📋 Prochaines étapes</h3>
    <ol>
      <li>Connectez-vous en tant que <strong>propriétaire</strong> sur
        <a href="public/login.html" target="_blank">/public/login.html</a>
        avec <code><?= htmlspecialchars($result['proprio_email']) ?></code>
      </li>
      <li>Personnalisez le menu (catégories, articles, photos, prix) depuis le dashboard.</li>
      <li>Ajoutez vos serveurs supplémentaires et assignez leurs zones via phpMyAdmin.</li>
      <li>Générez et imprimez les QR codes de chaque table (URLs listées ci-dessus).</li>
      <li>Connectez chaque serveur sur <a href="public/login.html" target="_blank">/public/login.html</a>
        avec <code><?= htmlspecialchars($result['serveur_email']) ?></code>
      </li>
      <?php if (!$result['twilio_configured']): ?>
      <li>⚠️ Pour activer WhatsApp : ajoutez vos clés Twilio dans <code>includes/config.php</code>
        via hPanel File Manager.</li>
      <?php endif ?>
    </ol>
  </div>

  <button class="delete-btn" onclick="deleteSelf()">🗑️ Supprimer install.php maintenant (sécurité)</button>

<?php endif ?>
</div>

<script>
function copyUrl(id) {
  const text = document.getElementById(id).textContent;
  navigator.clipboard.writeText(text).then(() => {
    event.target.textContent = 'Copié !';
    setTimeout(() => event.target.textContent = 'Copier', 2000);
  });
}
function deleteSelf() {
  if(!confirm('Supprimer install.php ? Cette action est irréversible.')) return;
  fetch('install.php?delete=1').then(() => {
    document.querySelector('.delete-btn').textContent = '✓ Supprimé — fermez cette page.';
    document.querySelector('.delete-btn').disabled = true;
  });
}
</script>
</body>
</html>
