<?php
// Enable full error reporting for debugging
error_reporting(E_ALL);

// Debug mode - set to true for development/troubleshooting
define('DEBUG_MODE', true);

// Start logging immediately
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/php_errors.log');
error_log("=== CONFIG.PHP STARTING ===");

// --- Environment Detection ---
// Define your production server's hostname.
define('PRODUCTION_HOSTNAME', 'niallcardin.com');

$is_production = ($_SERVER['HTTP_HOST'] === PRODUCTION_HOSTNAME);

error_log("Debug: HTTP_HOST = " . ($_SERVER['HTTP_HOST'] ?? 'not set'));
error_log("Debug: is_production = " . ($is_production ? 'true' : 'false'));

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

error_log("Debug: PRIVATE_PATH = " . PRIVATE_PATH);
error_log("Debug: DB_PATH = " . DB_PATH);
error_log("Debug: LOG_PATH = " . LOG_PATH);

// Check if private directory exists
error_log("Debug: Private directory exists: " . (is_dir(PRIVATE_PATH) ? 'YES' : 'NO'));
error_log("Debug: Private directory writable: " . (is_writable(PRIVATE_PATH) ? 'YES' : 'NO'));

// --- Ensure Private Directory Exists ---
// The script will attempt to create the private directory if it doesn't exist.
if (!is_dir(PRIVATE_PATH)) {
    error_log("Debug: Creating private directory: " . PRIVATE_PATH);
    
    // The @ suppresses warnings, which we handle manually.
    // The `true` allows recursive directory creation.
    if (!@mkdir(PRIVATE_PATH, 0755, true)) {
        $error = error_get_last();
        error_log("Failed to create private directory: " . $error['message']);
        // We can't proceed if this fails.
        http_response_code(500);
        echo json_encode(['error' => 'Server configuration error: cannot create private directory.']);
        exit;
    }
    
    error_log("Debug: Private directory created successfully");
}

// Now try to set up the proper error log
if (is_writable(PRIVATE_PATH)) {
    ini_set('error_log', LOG_PATH);
    error_log("Debug: Error log set to: " . LOG_PATH);
    
    // Test writing to the log file
    $test_log = "Test log entry at " . date('Y-m-d H:i:s') . "\n";
    if (file_put_contents(LOG_PATH, $test_log, FILE_APPEND | LOCK_EX) !== false) {
        error_log("Debug: Successfully wrote to log file");
    } else {
        error_log("Debug: Failed to write to log file");
    }
} else {
    error_log("Debug: Cannot write to private directory, keeping default error log");
}

// For debugging, also show errors in browser temporarily
if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    error_log("Debug: Display errors enabled");
}

error_log("=== CONFIG.PHP COMPLETE ==="); 