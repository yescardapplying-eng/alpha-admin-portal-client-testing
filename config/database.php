<?php
/**
 * ============================================================================
 * ADMIN PORTAL — DATABASE CONNECTION MODULE
 * ============================================================================
 */

function loadAdminEnv($file = null) {
    if ($file === null) {
        $file = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
    }
    if (!file_exists($file)) {
        return;
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }

        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if (preg_match('/^"(.+)"$/', $value, $matches) || preg_match("/^'(.+)'$/", $value, $matches)) {
                $value = $matches[1];
            }

            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv("{$name}={$value}");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

loadAdminEnv();

function getAdminDB() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    loadAdminEnv();

    $host = getenv('DB_HOST') ?: 'gateway01.us-east-1.prod.aws.tidbcloud.com';
    $port = getenv('DB_PORT') ?: 4000;
    $dbname = getenv('DB_NAME') ?: 'test';
    $user = getenv('DB_USER') ?: '3dArsb3jCD8JmQU.root';
    $pass = getenv('DB_PASS') ?: 'AlphaTrader@2026#Pass';
    $sslCa = getenv('DB_SSL_CA') ?: 'cacert.pem';

    $caPath = $sslCa;
    if (!file_exists($caPath)) {
        $candidate = dirname(__DIR__) . DIRECTORY_SEPARATOR . $sslCa;
        if (file_exists($candidate)) {
            $caPath = $candidate;
        }
    }

    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    if (file_exists($caPath)) {
        $options[PDO::MYSQL_ATTR_SSL_CA] = $caPath;
    }

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        return $pdo;
    } catch (PDOException $e) {
        error_log("[Admin DB Connection Error] " . $e->getMessage());
        throw new Exception("Admin DB Connection Failed: " . $e->getMessage());
    }
}

/**
 * ============================================================================
 * TABLE NAME RESOLUTION HELPERS FOR ADMIN PORTAL (DYNAMIC / CONFIGURABLE)
 * ============================================================================
 */
function getAdminTableName($type = 'leads') {
    loadAdminEnv();
    switch ($type) {
        case 'interactions':
        case 'assessment_interactions':
            $t = getenv('DB_TABLE_INTERACTIONS') ?: 'test_assessment_interactions';
            break;
        case 'admin_users':
        case 'users':
            $t = getenv('DB_TABLE_ADMIN_USERS') ?: 'test_admin_users';
            break;
        case 'leads':
        default:
            $t = getenv('DB_TABLE_LEADS') ?: 'test_leads';
            break;
    }
    return preg_replace('/[^a-zA-Z0-9_]/', '', $t);
}

if (!defined('ADMIN_TABLE_LEADS')) {
    define('ADMIN_TABLE_LEADS', getAdminTableName('leads'));
}
if (!defined('ADMIN_TABLE_INTERACTIONS')) {
    define('ADMIN_TABLE_INTERACTIONS', getAdminTableName('interactions'));
}
if (!defined('ADMIN_TABLE_USERS')) {
    define('ADMIN_TABLE_USERS', getAdminTableName('admin_users'));
}

