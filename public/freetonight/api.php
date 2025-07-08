<?php
header('Content-Type: application/json');

error_log("=== API.PHP STARTING ===");

require_once 'config.php';

error_log("Debug: Config loaded successfully");

try {
    error_log("Debug: Attempting to connect to database at: " . DB_PATH);
    
    $pdo = new PDO('sqlite:' . DB_PATH);
    error_log("Debug: PDO object created");
    
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    error_log("Debug: PDO error mode set");
    
    // Create table if it doesn't exist
    error_log("Debug: Creating table if it doesn't exist");
    $pdo->exec('CREATE TABLE IF NOT EXISTS status (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL UNIQUE,
        activity TEXT DEFAULT "Anything",
        free_in_minutes INTEGER DEFAULT 0,
        available_for_minutes INTEGER DEFAULT 240,
        timestamp INTEGER NOT NULL
    )');
    error_log("Debug: Table creation/check completed");
    
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    error_log("Debug: PDO error details: " . print_r($e, true));
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
} catch (Exception $e) {
    error_log("Unexpected error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Unexpected error: ' . $e->getMessage()]);
    exit;
}

error_log("Debug: Database connection successful");

switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        error_log("Debug: Processing POST request");
        // Set status - user declares they are free
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['name']) || trim($input['name']) === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Name is required']);
            exit;
        }
        
        $name = trim(strip_tags($input['name']));
        if (strlen($name) > 50) {
            http_response_code(400);
            echo json_encode(['error' => 'Name too long (max 50 characters)']);
            exit;
        }
        
        // New fields with defaults
        $activity = isset($input['activity']) ? trim($input['activity']) : 'Anything';
        if (strlen($activity) > 100) {
            http_response_code(400);
            echo json_encode(['error' => 'Activity too long (max 100 characters)']);
            exit;
        }
        $free_in_minutes = isset($input['free_in_minutes']) && is_numeric($input['free_in_minutes']) ? (int)$input['free_in_minutes'] : 0;
        $available_for_minutes = isset($input['available_for_minutes']) && is_numeric($input['available_for_minutes']) ? (int)$input['available_for_minutes'] : 240;
        
        try {
            $stmt = $pdo->prepare('REPLACE INTO status (name, activity, free_in_minutes, available_for_minutes, timestamp) VALUES (:name, :activity, :free_in_minutes, :available_for_minutes, :timestamp)');
            $stmt->execute([
                'name' => $name,
                'activity' => $activity,
                'free_in_minutes' => $free_in_minutes,
                'available_for_minutes' => $available_for_minutes,
                'timestamp' => time()
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => "Status for $name updated."
            ]);
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update status']);
        }
        break;
        
    case 'DELETE':
        error_log("Debug: Processing DELETE request");
        // Remove status - user removes themselves from list
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['name']) || trim($input['name']) === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Name is required']);
            exit;
        }
        
        $name = trim(strip_tags($input['name']));
        
        try {
            $stmt = $pdo->prepare('DELETE FROM status WHERE name = :name');
            $stmt->execute(['name' => $name]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => "$name removed from list."
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'message' => "$name was not in the list."
                ]);
            }
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to remove status']);
        }
        break;
        
    case 'GET':
        error_log("Debug: Processing GET request");
        // Get list of free friends
        $start_of_day = strtotime('today', time());
        
        try {
            $stmt = $pdo->prepare('SELECT name, activity, free_in_minutes, available_for_minutes, timestamp FROM status WHERE timestamp >= :start_of_day ORDER BY timestamp DESC');
            $stmt->execute(['start_of_day' => $start_of_day]);
            
            $users = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $users[] = [
                    'name' => $row['name'],
                    'activity' => $row['activity'],
                    'free_in_minutes' => (int)$row['free_in_minutes'],
                    'available_for_minutes' => (int)$row['available_for_minutes'],
                    'timestamp' => $row['timestamp']
                ];
            }
            
            echo json_encode($users);
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to retrieve status list']);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

error_log("=== API.PHP COMPLETE ==="); 