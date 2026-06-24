<?php
/**
 * MGT CRM — Fichier de diagnostic temporaire
 * IMPORTANT : Supprimer ce fichier après utilisation !
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Diagnostic MGT CRM</h2>";

// ── 1. Version PHP ──
echo "<h3>1. PHP</h3>";
echo "Version PHP : <strong>" . phpversion() . "</strong><br>";
$ok = version_compare(phpversion(), '7.4', '>=');
echo $ok ? "✅ Version OK" : "❌ PHP trop ancien (besoin 7.4+)";

// ── 2. Extensions requises ──
echo "<h3>2. Extensions PHP</h3>";
foreach (['pdo', 'pdo_mysql', 'json', 'mbstring'] as $ext) {
    $loaded = extension_loaded($ext);
    echo ($loaded ? "✅" : "❌") . " $ext<br>";
}

// ── 3. Connexion MySQL ──
echo "<h3>3. Connexion MySQL</h3>";
// Remplacez ces valeurs par vos vrais identifiants
$db_name = 'u293743867_mgtcrm';
$db_user = 'u293743867_mgtuser';
$db_pass = '#b@!?qW8'; // ← mettre votre vrai mot de passe ici

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=$db_name;charset=utf8mb4",
        $db_user, $db_pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✅ Connexion MySQL OK<br>";

    // ── 4. Tables existantes ──
    echo "<h3>4. Tables dans la base</h3>";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    if (empty($tables)) {
        echo "⚠️ Aucune table trouvée — la base est vide<br>";
    } else {
        foreach ($tables as $t) {
            echo "✅ $t<br>";
        }
    }

    // ── 5. Tables MGT manquantes ──
    echo "<h3>5. Tables requises par le CRM</h3>";
    $required = ['mgt_clients','mgt_formats','mgt_types','mgt_users',
                 'mgt_prices','mgt_costs','mgt_entries',
                 'mgt_sessions','mgt_login_attempts'];
    foreach ($required as $t) {
        $exists = in_array($t, $tables);
        echo ($exists ? "✅" : "❌ MANQUANTE") . " — $t<br>";
    }

    // ── 6. Comptes utilisateurs ──
    echo "<h3>6. Comptes utilisateurs</h3>";
    if (in_array('mgt_users', $tables)) {
        $users = $pdo->query("SELECT login, role FROM mgt_users")->fetchAll();
        if (empty($users)) {
            echo "⚠️ Aucun utilisateur en base !<br>";
        } else {
            foreach ($users as $u) {
                echo "✅ {$u['login']} ({$u['role']})<br>";
            }
        }
    } else {
        echo "❌ Table mgt_users inexistante<br>";
    }

} catch (PDOException $e) {
    echo "❌ Erreur MySQL : <strong>" . htmlspecialchars($e->getMessage()) . "</strong><br>";
    echo "<br><em>Causes possibles :</em><br>";
    echo "• Mauvais nom de base de données<br>";
    echo "• Mauvais nom d'utilisateur MySQL<br>";
    echo "• Mauvais mot de passe MySQL<br>";
    echo "• La base n'existe pas encore sur Hostinger<br>";
}

// ── 7. Test api.php ──
echo "<h3>7. Test api.php</h3>";
if (file_exists('api.php')) {
    echo "✅ api.php présent<br>";
    $size = filesize('api.php');
    echo "Taille : $size octets<br>";
    if ($size < 1000) echo "⚠️ Fichier trop petit — probablement corrompu lors de l'upload<br>";
} else {
    echo "❌ api.php INTROUVABLE dans ce dossier<br>";
}

if (file_exists('index.html')) {
    echo "✅ index.html présent (" . filesize('index.html') . " octets)<br>";
} else {
    echo "❌ index.html INTROUVABLE<br>";
}

// ── 8. Droits PHP ──
echo "<h3>8. Fonctions PHP disponibles</h3>";
echo function_exists('random_bytes') ? "✅ random_bytes (PHP 7+)<br>" : "❌ random_bytes manquant<br>";
echo function_exists('bin2hex') ? "✅ bin2hex<br>" : "❌ bin2hex manquant<br>";

echo "<hr><p style='color:red'><strong>⚠️ SUPPRIMER ce fichier diagnostic.php après utilisation !</strong></p>";
