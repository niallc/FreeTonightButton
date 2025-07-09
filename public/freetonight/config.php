<?php
// Enable full error reporting for debugging
error_reporting(E_ALL);

// --- Environment Detection ---
// Define your production server's hostname.
define('PRODUCTION_HOSTNAME', 'niallcardin.com');

$is_production = ($_SERVER['HTTP_HOST'] === PRODUCTION_HOSTNAME);

// --- Smart Logging System ---
// Log levels: ERROR, WARN, INFO, DEBUG
// Production: Only ERROR and WARN
// Development: All levels
// Can be overridden with environment variable
$log_level = getenv('FREETONIGHT_LOG_LEVEL');
if (!$log_level) {
    $log_level = $is_production ? 'WARN' : 'DEBUG';
}

// Define log level constants (only if not already defined)
if (!defined('LOG_ERROR')) define('LOG_ERROR', 0);
if (!defined('LOG_WARN')) define('LOG_WARN', 1);
if (!defined('LOG_INFO')) define('LOG_INFO', 2);
if (!defined('LOG_DEBUG')) define('LOG_DEBUG', 3);

$log_levels = [
    'ERROR' => LOG_ERROR,
    'WARN' => LOG_WARN,
    'INFO' => LOG_INFO,
    'DEBUG' => LOG_DEBUG
];

define('CURRENT_LOG_LEVEL', $log_levels[$log_level] ?? LOG_WARN);

// Smart logging function
function smart_log($level, $message, $context = []) {
    if ($level <= CURRENT_LOG_LEVEL) {
        $prefix = match($level) {
            LOG_ERROR => 'ERROR',
            LOG_WARN => 'WARN',
            LOG_INFO => 'INFO',
            LOG_DEBUG => 'DEBUG'
        };
        
        $context_str = !empty($context) ? ' ' . json_encode($context) : '';
        error_log("[$prefix] $message$context_str");
    }
}

// Start logging immediately
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/php_errors.log');

smart_log(LOG_INFO, "=== CONFIG.PHP STARTING ===", [
    'environment' => $is_production ? 'production' : 'development',
    'log_level' => $log_level,
    'host' => $_SERVER['HTTP_HOST'] ?? 'not set'
]);

// --- Path Definitions ---
if ($is_production) {
    // Production Server Paths
    define('PRIVATE_PATH', '/home/private/freetonight');
    define('DB_PATH', PRIVATE_PATH . '/friends.db');
    define('LOG_PATH', PRIVATE_PATH . '/php_errors.log');
} else {
    // Local Development Paths
    // Uses __DIR__ to get the absolute path of the current directory (public/freetonight)
    // then navigates up and over to the private directory.
    define('PRIVATE_PATH', realpath(__DIR__ . '/../../private/freetonight'));
    $test_db = getenv('FREETONIGHT_TEST_DB');
    if ($test_db) {
        define('DB_PATH', PRIVATE_PATH . '/friends_test.db');
    } else {
        define('DB_PATH', PRIVATE_PATH . '/friends.db');
    }
    define('LOG_PATH', PRIVATE_PATH . '/php_errors.log');
}

smart_log(LOG_DEBUG, "Path configuration", [
    'PRIVATE_PATH' => PRIVATE_PATH,
    'DB_PATH' => DB_PATH,
    'LOG_PATH' => LOG_PATH
]);

// Check if private directory exists
smart_log(LOG_DEBUG, "Directory status", [
    'exists' => is_dir(PRIVATE_PATH) ? 'YES' : 'NO',
    'writable' => is_writable(PRIVATE_PATH) ? 'YES' : 'NO'
]);

// --- Ensure Private Directory Exists ---
// The script will attempt to create the private directory if it doesn't exist.
if (!is_dir(PRIVATE_PATH)) {
    smart_log(LOG_INFO, "Creating private directory", ['path' => PRIVATE_PATH]);
    
    // The @ suppresses warnings, which we handle manually.
    // The `true` allows recursive directory creation.
    if (!@mkdir(PRIVATE_PATH, 0755, true)) {
        $error = error_get_last();
        smart_log(LOG_ERROR, "Failed to create private directory", ['error' => $error['message']]);
        // We can't proceed if this fails.
        http_response_code(500);
        echo json_encode(['error' => 'Server configuration error: cannot create private directory.']);
        exit;
    }
    
    smart_log(LOG_INFO, "Private directory created successfully");
}

// Now try to set up the proper error log
if (is_writable(PRIVATE_PATH)) {
    ini_set('error_log', LOG_PATH);
    smart_log(LOG_DEBUG, "Error log configured", ['path' => LOG_PATH]);
    
    // Test writing to the log file
    $test_log = "Test log entry at " . date('Y-m-d H:i:s') . "\n";
    if (file_put_contents(LOG_PATH, $test_log, FILE_APPEND | LOCK_EX) !== false) {
        smart_log(LOG_DEBUG, "Successfully wrote to log file");
    } else {
        smart_log(LOG_WARN, "Failed to write to log file");
    }
} else {
    smart_log(LOG_WARN, "Cannot write to private directory, keeping default error log");
}

// For debugging, also show errors in browser temporarily
if (CURRENT_LOG_LEVEL >= LOG_DEBUG) {
    ini_set('display_errors', 1);
    smart_log(LOG_DEBUG, "Display errors enabled");
}

smart_log(LOG_INFO, "=== CONFIG.PHP COMPLETE ==="); 