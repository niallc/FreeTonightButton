<?php
header('Content-Type: application/json');

require_once 'config.php';

smart_log(LOG_INFO, "=== API.PHP STARTING ===");

smart_log(LOG_DEBUG, "Config loaded successfully");

try {
    smart_log(LOG_DEBUG, "Attempting to connect to database", ['path' => DB_PATH]);
    
    $pdo = new PDO('sqlite:' . DB_PATH);
    smart_log(LOG_DEBUG, "PDO object created");
    
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    smart_log(LOG_DEBUG, "PDO error mode set");
    
    // Create table if it doesn't exist
    smart_log(LOG_DEBUG, "Creating table if it doesn't exist");
    $pdo->exec('CREATE TABLE IF NOT EXISTS status (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL UNIQUE,
        activity TEXT DEFAULT "Anything",
        free_in_minutes INTEGER DEFAULT 0,
        available_for_minutes INTEGER DEFAULT 240,
        timestamp INTEGER NOT NULL
    )');
    smart_log(LOG_DEBUG, "Table creation/check completed");
    
} catch (PDOException $e) {
    smart_log(LOG_ERROR, "Database connection failed", [
        'error' => $e->getMessage(),
        'details' => $e->getTraceAsString()
    ]);
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
} catch (Exception $e) {
    smart_log(LOG_ERROR, "Unexpected error", [
        'error' => $e->getMessage(),
        'details' => $e->getTraceAsString()
    ]);
    http_response_code(500);
    echo json_encode(['error' => 'Unexpected error: ' . $e->getMessage()]);
    exit;
}

smart_log(LOG_DEBUG, "Database connection successful");

switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        smart_log(LOG_DEBUG, "Processing POST request");
        // Set status - user declares they are free
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Sanitize name first
        $name = trim(strip_tags($input['name']));
        if ($name === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Name is required']);
            exit;
        }
        if (strlen($name) > 50) {
            http_response_code(400);
            echo json_encode(['error' => 'Name too long (max 50 characters)']);
            exit;
        }
        // Sanitize activity
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
            
            smart_log(LOG_INFO, "User status updated", [
                'name' => $name,
                'activity' => $activity,
                'free_in_minutes' => $free_in_minutes,
                'available_for_minutes' => $available_for_minutes
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => "Status for $name updated."
            ]);
        } catch (PDOException $e) {
            smart_log(LOG_ERROR, "Database error during POST", [
                'error' => $e->getMessage(),
                'name' => $name
            ]);
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update status']);
        }
        break;
        
    case 'DELETE':
        smart_log(LOG_DEBUG, "Processing DELETE request");
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
                smart_log(LOG_INFO, "User removed from list", ['name' => $name]);
                echo json_encode([
                    'success' => true,
                    'message' => "$name removed from list."
                ]);
            } else {
                smart_log(LOG_WARN, "User not found for deletion", ['name' => $name]);
                echo json_encode([
                    'success' => true,
                    'message' => "$name was not in the list."
                ]);
            }
        } catch (PDOException $e) {
            smart_log(LOG_ERROR, "Database error during DELETE", [
                'error' => $e->getMessage(),
                'name' => $name
            ]);
            http_response_code(500);
            echo json_encode(['error' => 'Failed to remove status']);
        }
        break;
        
    case 'GET':
        smart_log(LOG_DEBUG, "Processing GET request");
        // Get list of free friends
        $start_of_day = strtotime('today', time());
        $now = time();
        try {
            $stmt = $pdo->prepare('SELECT name, activity, free_in_minutes, available_for_minutes, timestamp FROM status WHERE timestamp >= :start_of_day ORDER BY timestamp DESC');
            $stmt->execute(['start_of_day' => $start_of_day]);
            
            $users = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $free_in = (int)$row['free_in_minutes'];
                $available_for = (int)$row['available_for_minutes'];
                $posted = (int)$row['timestamp'];
                $free_start = $posted + $free_in * 60;
                $free_end = $free_start + $available_for * 60;
                // If no time specified (available_for == minutes until UTC midnight), clear at UTC midnight
                // If times specified, remove 1 hour after end
                if ($available_for === 0) continue; // skip invalid
                if ($free_in === 0 && $available_for > 0 && $available_for <= 1440) {
                    // No time specified, treat as 'until midnight' (up to 24h)
                    $midnight = strtotime('tomorrow', $posted) - 1;
                    if ($now > $midnight) continue; // past midnight, skip
                } else {
                    // Time specified, remove 1 hour after end
                    if ($now > $free_end + 3600) continue;
                }
                $users[] = [
                    'name' => $row['name'],
                    'activity' => $row['activity'],
                    'free_in_minutes' => $free_in,
                    'available_for_minutes' => $available_for,
                    'timestamp' => $posted
                ];
            }
            
            smart_log(LOG_DEBUG, "Retrieved user list", ['count' => count($users)]);
            echo json_encode($users);
        } catch (PDOException $e) {
            smart_log(LOG_ERROR, "Database error during GET", [
                'error' => $e->getMessage()
            ]);
            http_response_code(500);
            echo json_encode(['error' => 'Failed to retrieve status list']);
        }
        break;
        
    default:
        smart_log(LOG_WARN, "Invalid HTTP method", ['method' => $_SERVER['REQUEST_METHOD']]);
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

smart_log(LOG_INFO, "=== API.PHP COMPLETE ==="); 