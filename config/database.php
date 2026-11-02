<?php
/**
 * Database Configuration & PDO Connector
 * Reads settings from .env file or environment variables
 */

// Simple .env parser
function load_env($filePath) {
    if (!file_exists($filePath)) {
        return [];
    }
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $env = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Remove wrapping quotes if any
            $value = trim($value, '"\'');
            $env[$key] = $value;
            $_ENV[$key] = $value;
        }
    }
    return $env;
}

// Load .env from project root
$envPath = dirname(__DIR__) . '/.env';
$config = load_env($envPath);

// Default settings if .env is missing
$db_host = $config['DB_HOST'] ?? '127.0.0.1';
$db_port = $config['DB_PORT'] ?? '3306';
$db_name = $config['DB_DATABASE'] ?? 'school_portal';
$db_user = $config['DB_USERNAME'] ?? 'school_user';
$db_pass = $config['DB_PASSWORD'] ?? 'SchoolPass123!';

$db_connected = false;
$db_error_message = '';
$pdo = null;

try {
    $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 2, // 2-second timeout to prevent long page hangs if VM is off
    ];
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    $db_connected = true;
} catch (PDOException $e) {
    $db_connected = false;
    $db_error_message = $e->getMessage();
}

/**
 * Returns active PDO instance or null
 */
function get_db() {
    global $pdo;
    return $pdo;
}

/**
 * Check if database is currently reachable
 */
function is_db_connected() {
    global $db_connected;
    return $db_connected;
}

/**
 * Get error details if connection failed
 */
function get_db_error() {
    global $db_error_message, $db_host, $db_port;
    return [
        'host' => $db_host,
        'port' => $db_port,
        'message' => $db_error_message
    ];
}
