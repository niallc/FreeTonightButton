<?php
header('Content-Type: application/json');

require_once 'config.php';

// --- Constants ---
define('MAX_NAME_LENGTH', 50);
define('MAX_ACTIVITY_LENGTH', 100);
define('DEFAULT_AVAILABLE_FOR_MINUTES', 240);
define('GRACE_PERIOD_SECONDS', 3600); // 1 hour after end time
define('MAX_MIDNIGHT_MINUTES', 1440); // 24 hours
define('SECONDS_PER_MINUTE', 60);

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

// --- Time Logic Helper Functions ---

/**
 * Check if a user entry should be included in the current list
 * based on their time settings and current time
 */
function shouldIncludeUser($freeInMinutes, $availableForMinutes, $postedTimestamp) {
    $now = time();
    
    // Skip invalid entries (no available time)
    if ($availableForMinutes === 0) {
        return false;
    }
    
    // Calculate time boundaries
    $freeStart = $postedTimestamp + ($freeInMinutes * SECONDS_PER_MINUTE);
    $freeEnd = $freeStart + ($availableForMinutes * SECONDS_PER_MINUTE);
    $gracePeriodEnd = $freeEnd + GRACE_PERIOD_SECONDS;
    
    // Handle "until midnight" entries (no specific time given)
    if (isUntilMidnightEntry($freeInMinutes, $availableForMinutes)) {
        $midnight = strtotime('tomorrow', $postedTimestamp) - 1;
        return $now <= $midnight;
    }
    
    // Handle specific time entries (remove after grace period)
    return $now < $gracePeriodEnd;
}

/**
 * Check if this is an "until midnight" entry (no specific time given)
 */
function isUntilMidnightEntry($freeInMinutes, $availableForMinutes) {
    return $freeInMinutes === 0 && 
           $availableForMinutes > 0 && 
           $availableForMinutes <= MAX_MIDNIGHT_MINUTES;
}

/**
 * Sanitize and validate user input
 */
function validateUserInput($input) {
    $name = trim(strip_tags($input['name'] ?? ''));
    if ($name === '') {
        return ['error' => 'Name is required'];
    }
    if (strlen($name) > MAX_NAME_LENGTH) {
        return ['error' => 'Name too long (max ' . MAX_NAME_LENGTH . ' characters)'];
    }
    
    $activity = isset($input['activity']) ? trim($input['activity']) : 'Anything';
    if (strlen($activity) > MAX_ACTIVITY_LENGTH) {
        return ['error' => 'Activity too long (max ' . MAX_ACTIVITY_LENGTH . ' characters)'];
    }
    
    $freeInMinutes = isset($input['free_in_minutes']) && is_numeric($input['free_in_minutes']) 
        ? (int)$input['free_in_minutes'] : 0;
    $availableForMinutes = isset($input['available_for_minutes']) && is_numeric($input['available_for_minutes']) 
        ? (int)$input['available_for_minutes'] : DEFAULT_AVAILABLE_FOR_MINUTES;
    
    return [
        'name' => $name,
        'activity' => $activity,
        'free_in_minutes' => $freeInMinutes,
        'available_for_minutes' => $availableForMinutes
    ];
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        smart_log(LOG_DEBUG, "Processing POST request");
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validate and sanitize input
        $validated = validateUserInput($input);
        if (isset($validated['error'])) {
            http_response_code(400);
            echo json_encode(['error' => $validated['error']]);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare('REPLACE INTO status (name, activity, free_in_minutes, available_for_minutes, timestamp) VALUES (:name, :activity, :free_in_minutes, :available_for_minutes, :timestamp)');
            $stmt->execute([
                'name' => $validated['name'],
                'activity' => $validated['activity'],
                'free_in_minutes' => $validated['free_in_minutes'],
                'available_for_minutes' => $validated['available_for_minutes'],
                'timestamp' => time()
            ]);
            
            smart_log(LOG_INFO, "User status updated", [
                'name' => $validated['name'],
                'activity' => $validated['activity'],
                'free_in_minutes' => $validated['free_in_minutes'],
                'available_for_minutes' => $validated['available_for_minutes']
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => "Status for {$validated['name']} updated."
            ]);
        } catch (PDOException $e) {
            smart_log(LOG_ERROR, "Database error during POST", [
                'error' => $e->getMessage(),
                'name' => $validated['name']
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
        $start_of_day = strtotime('today', time());
        
        try {
            $stmt = $pdo->prepare('SELECT name, activity, free_in_minutes, available_for_minutes, timestamp FROM status WHERE timestamp >= :start_of_day ORDER BY timestamp DESC');
            $stmt->execute(['start_of_day' => $start_of_day]);
            
            $users = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $freeIn = (int)$row['free_in_minutes'];
                $availableFor = (int)$row['available_for_minutes'];
                $posted = (int)$row['timestamp'];
                
                // Use the clean time logic function
                if (shouldIncludeUser($freeIn, $availableFor, $posted)) {
                    $users[] = [
                        'name' => $row['name'],
                        'activity' => $row['activity'],
                        'free_in_minutes' => $freeIn,
                        'available_for_minutes' => $availableFor,
                        'timestamp' => $posted
                    ];
                }
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