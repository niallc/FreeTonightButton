<?php
header('Content-Type: application/json');

// Database setup
$db_file = 'friends.db';

try {
    $pdo = new PDO('sqlite:' . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create table if it doesn't exist
    $pdo->exec('CREATE TABLE IF NOT EXISTS status (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL UNIQUE,
        timestamp INTEGER NOT NULL
    )');
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Route based on HTTP method
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle setting status
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!isset($data['name']) || empty(trim($data['name']))) {
        http_response_code(400);
        echo json_encode(['error' => 'Name is required']);
        exit;
    }
    
    $name = trim($data['name']);
    $timestamp = time();
    
    try {
        $stmt = $pdo->prepare('REPLACE INTO status (name, timestamp) VALUES (:name, :timestamp)');
        $stmt->execute(['name' => $name, 'timestamp' => $timestamp]);
        
        echo json_encode(['success' => true, 'name' => $name]);
        
    } catch (PDOException $e) {
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
        echo json_encode($users);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch status list: ' . $e->getMessage()]);
    }
    
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?> 