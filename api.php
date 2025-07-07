<?php
header('Content-Type: application/json');

// Enable error logging
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

// Log all API calls with more detail
error_log("API call: " . $_SERVER['REQUEST_METHOD'] . " from " . $_SERVER['REMOTE_ADDR'] . " at " . date('Y-m-d H:i:s') . " - User Agent: " . $_SERVER['HTTP_USER_AGENT']);

// Database setup
$db_file = 'friends.db';

try {
    error_log("Attempting to connect to database: " . $db_file);
    $pdo = new PDO('sqlite:' . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    error_log("Database connection successful");
    
    // Create table if it doesn't exist
    $create_table_sql = 'CREATE TABLE IF NOT EXISTS status (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL UNIQUE,
        timestamp INTEGER NOT NULL
    )';
    error_log("Executing table creation SQL: " . $create_table_sql);
    $pdo->exec($create_table_sql);
    error_log("Table creation/check completed");
    
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    error_log("Database file path: " . realpath($db_file));
    error_log("Current working directory: " . getcwd());
    error_log("File permissions: " . substr(sprintf('%o', fileperms($db_file)), -4));
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Route based on HTTP method and query parameters
error_log("Processing " . $_SERVER['REQUEST_METHOD'] . " request");

// Check if this is a set action via GET
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'set') {
    // Handle setting status via GET
    error_log("Processing SET action via GET");
    
    if (!isset($_GET['name']) || empty(trim($_GET['name']))) {
        error_log("Validation failed: name is missing or empty in GET parameters");
        http_response_code(400);
        echo json_encode(['error' => 'Name is required']);
        exit;
    }
    
    $name = trim(strip_tags($_GET['name']));
    $name = substr($name, 0, 50); // Limit length to 50 characters
    
    if (empty($name)) {
        http_response_code(400);
        echo json_encode(['error' => 'Name cannot be empty']);
        exit;
    }
    
    $timestamp = time();
    
    try {
        error_log("Preparing to insert/update name: " . $name . " with timestamp: " . $timestamp);
        $stmt = $pdo->prepare('REPLACE INTO status (name, timestamp) VALUES (:name, :timestamp)');
        $result = $stmt->execute(['name' => $name, 'timestamp' => $timestamp]);
        error_log("Database operation result: " . ($result ? 'success' : 'failed'));
        
        echo json_encode(['success' => true, 'name' => $name]);
        
    } catch (PDOException $e) {
        error_log("Failed to update status: " . $e->getMessage());
        error_log("SQL State: " . $e->getCode());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update status: ' . $e->getMessage()]);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle setting status via POST (fallback)
    $input = file_get_contents('php://input');
    error_log("Raw input received: " . $input);
    
    $data = json_decode($input, true);
    error_log("Decoded JSON data: " . print_r($data, true));
    
    if (!isset($data['name']) || empty(trim($data['name']))) {
        error_log("Validation failed: name is missing or empty");
        http_response_code(400);
        echo json_encode(['error' => 'Name is required']);
        exit;
    }
    
    $name = trim(strip_tags($data['name']));
    $name = substr($name, 0, 50); // Limit length to 50 characters
    
    if (empty($name)) {
        http_response_code(400);
        echo json_encode(['error' => 'Name cannot be empty']);
        exit;
    }
    
    $timestamp = time();
    
    try {
        error_log("Preparing to insert/update name: " . $name . " with timestamp: " . $timestamp);
        $stmt = $pdo->prepare('REPLACE INTO status (name, timestamp) VALUES (:name, :timestamp)');
        $result = $stmt->execute(['name' => $name, 'timestamp' => $timestamp]);
        error_log("Database operation result: " . ($result ? 'success' : 'failed'));
        
        echo json_encode(['success' => true, 'name' => $name]);
        
    } catch (PDOException $e) {
        error_log("Failed to update status: " . $e->getMessage());
        error_log("SQL State: " . $e->getCode());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update status: ' . $e->getMessage()]);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Handle fetching the list
    $start_of_day = strtotime('today', time());
    
    try {
        $stmt = $pdo->prepare('SELECT name, timestamp FROM status WHERE timestamp >= :start_of_day ORDER BY timestamp DESC');
        $stmt->execute(['start_of_day' => $start_of_day]);
        
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Sanitize names before sending to client
        foreach ($users as &$user) {
            $user['name'] = htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8');
        }
        
        echo json_encode($users);
        
    } catch (PDOException $e) {
        error_log("Failed to fetch status list: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch status list: ' . $e->getMessage()]);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
    // Health check endpoint
    echo json_encode(['status' => 'ok', 'timestamp' => time()]);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?> 