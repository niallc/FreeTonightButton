<?php
header('Content-Type: application/json');

require_once 'config.php';

// --- Constants ---
define('MAX_NAME_LENGTH', 50);
define('MAX_ACTIVITY_LENGTH', 100);
define('DEFAULT_AVAILABLE_FOR_MINUTES', 240);
define('GRACE_PERIOD_SECONDS', 3600); // 1 hour after end time
define('MAX_MIDNIGHT_MINUTES', 1440); // 24 hours (local timezone)
define('SECONDS_PER_MINUTE', 60);

// Group-related constants
define('MAX_GROUP_NAME_LENGTH', 20);
define('DEFAULT_GROUP_NAME', 'default');
define('RESERVED_GROUP_NAMES', ['main', 'admin', 'test', 'api', 'default']);

smart_log(LOG_INFO, "=== API.PHP STARTING ===");

smart_log(LOG_DEBUG, "Config loaded successfully");

try {
    smart_log(LOG_DEBUG, "Attempting to connect to database", ['path' => DB_PATH]);
    
    $pdo = new PDO('sqlite:' . DB_PATH);
    smart_log(LOG_DEBUG, "PDO object created");
    
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    smart_log(LOG_DEBUG, "PDO error mode set");
    
    // Create tables if they don't exist
    smart_log(LOG_DEBUG, "Creating tables if they don't exist");
    
    // Main status table (add group_name column if it doesn't exist)
    $pdo->exec('CREATE TABLE IF NOT EXISTS status (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL,
        activity TEXT DEFAULT "Anything",
        free_in_minutes INTEGER DEFAULT 0,
        available_for_minutes INTEGER DEFAULT 240,
        timestamp INTEGER NOT NULL,
        group_name TEXT NOT NULL DEFAULT "default",
        UNIQUE(name, group_name)
    )');
    
    // Add group_name column to existing tables if it doesn't exist
    $result = $pdo->query("PRAGMA table_info(status)");
    $columns = $result->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('group_name', $columns)) {
        $pdo->exec('ALTER TABLE status ADD COLUMN group_name TEXT NOT NULL DEFAULT "default"');
        smart_log(LOG_INFO, "Added group_name column to status table");
        
        // Add unique constraint for existing tables
        try {
            $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_status_name_group ON status(name, group_name)');
            smart_log(LOG_INFO, "Added unique constraint for name and group_name");
        } catch (PDOException $e) {
            smart_log(LOG_WARN, "Could not add unique constraint (may already exist)", ['error' => $e->getMessage()]);
        }
    }
    
    // Groups metadata table
    $pdo->exec('CREATE TABLE IF NOT EXISTS groups (
        id INTEGER PRIMARY KEY,
        name TEXT UNIQUE NOT NULL,
        display_name TEXT NOT NULL,
        created_at INTEGER NOT NULL
    )');
    
    // Insert default group if it doesn't exist
    $stmt = $pdo->prepare('INSERT OR IGNORE INTO groups (name, display_name, created_at) VALUES (?, ?, ?)');
    $stmt->execute([DEFAULT_GROUP_NAME, 'Default Group', time()]);
    
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
 * 
 * DESIGN CHOICE: Uses local server timezone for "until midnight" calculations.
 * This is intentional - if you live in New Zealand, you go to bed on NZ time,
 * not UTC midnight. This provides better UX for users in different timezones.
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
    // Uses local server timezone - this is intentional for better UX
    if (isUntilMidnightEntry($freeInMinutes, $availableForMinutes)) {
        $midnight = strtotime('tomorrow', $postedTimestamp) - 1;
        return $now <= $midnight;
    }
    
    // Handle specific time entries (remove after grace period)
    return $now < $gracePeriodEnd;
}

/**
 * Check if this is an "until midnight" entry (no specific time given)
 * 
 * These entries are cleared at local midnight (server timezone), not UTC.
 * This provides better UX for users in different timezones.
 */
function isUntilMidnightEntry($freeInMinutes, $availableForMinutes) {
    return $freeInMinutes === 0 && 
           $availableForMinutes > 0 && 
           $availableForMinutes <= MAX_MIDNIGHT_MINUTES;
}

/**
 * Validate and sanitize group name
 */
function validateGroupName($groupName) {
    smart_log(LOG_DEBUG, "Validating group name", ['input' => $groupName]);
    
    $groupName = trim($groupName ?? '');
    if ($groupName === '') {
        smart_log(LOG_DEBUG, "Group name validation failed: empty");
        return ['error' => 'Group name is required'];
    }
    
    // Check length
    if (strlen($groupName) > MAX_GROUP_NAME_LENGTH) {
        smart_log(LOG_DEBUG, "Group name validation failed: too long", [
            'length' => strlen($groupName),
            'max' => MAX_GROUP_NAME_LENGTH
        ]);
        return ['error' => 'Group name too long (max ' . MAX_GROUP_NAME_LENGTH . ' characters)'];
    }
    
    // Check for reserved names
    if (in_array(strtolower($groupName), array_map('strtolower', RESERVED_GROUP_NAMES))) {
        smart_log(LOG_DEBUG, "Group name validation failed: reserved name", [
            'name' => $groupName,
            'reserved_names' => RESERVED_GROUP_NAMES
        ]);
        return ['error' => 'Group name is reserved'];
    }
    
    // Check for valid characters (alphanumeric + underscore + hyphen)
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $groupName)) {
        smart_log(LOG_DEBUG, "Group name validation failed: invalid characters", ['name' => $groupName]);
        return ['error' => 'Group name can only contain letters, numbers, underscores, and hyphens'];
    }
    
    smart_log(LOG_DEBUG, "Group name validation passed", ['name' => $groupName]);
    return ['group_name' => $groupName];
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
    
    // Get group name (default to 'default' if not specified)
    $groupName = isset($input['group_name']) ? trim($input['group_name']) : DEFAULT_GROUP_NAME;
    
    return [
        'name' => $name,
        'activity' => $activity,
        'free_in_minutes' => $freeInMinutes,
        'available_for_minutes' => $availableForMinutes,
        'group_name' => $groupName
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
            $stmt = $pdo->prepare('REPLACE INTO status (name, activity, free_in_minutes, available_for_minutes, timestamp, group_name) VALUES (:name, :activity, :free_in_minutes, :available_for_minutes, :timestamp, :group_name)');
            $stmt->execute([
                'name' => $validated['name'],
                'activity' => $validated['activity'],
                'free_in_minutes' => $validated['free_in_minutes'],
                'available_for_minutes' => $validated['available_for_minutes'],
                'timestamp' => time(),
                'group_name' => $validated['group_name']
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
        $groupName = isset($input['group_name']) ? trim($input['group_name']) : DEFAULT_GROUP_NAME;
        
        try {
            $stmt = $pdo->prepare('DELETE FROM status WHERE name = :name AND group_name = :group_name');
            $stmt->execute(['name' => $name, 'group_name' => $groupName]);
            
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
        
        // Check if this is a group management request
        if (isset($_GET['action'])) {
            switch ($_GET['action']) {
                case 'list_groups':
                    try {
                        $stmt = $pdo->prepare('SELECT name, display_name, created_at FROM groups ORDER BY created_at DESC');
                        $stmt->execute();
                        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        echo json_encode($groups);
                    } catch (PDOException $e) {
                        smart_log(LOG_ERROR, "Database error listing groups", ['error' => $e->getMessage()]);
                        http_response_code(500);
                        echo json_encode(['error' => 'Failed to list groups']);
                    }
                    break;
                    
                default:
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid action']);
                    break;
            }
            break;
        }
        
        // Regular status list request
        $start_of_day = strtotime('today', time());
        $groupName = $_GET['group'] ?? DEFAULT_GROUP_NAME;
        
        try {
            $stmt = $pdo->prepare('SELECT name, activity, free_in_minutes, available_for_minutes, timestamp FROM status WHERE timestamp >= :start_of_day AND group_name = :group_name ORDER BY timestamp DESC');
            $stmt->execute(['start_of_day' => $start_of_day, 'group_name' => $groupName]);
            
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
            
            smart_log(LOG_DEBUG, "Retrieved user list", ['count' => count($users), 'group' => $groupName]);
            echo json_encode($users);
        } catch (PDOException $e) {
            smart_log(LOG_ERROR, "Database error during GET", ['error' => $e->getMessage()]);
            http_response_code(500);
            echo json_encode(['error' => 'Failed to retrieve status list']);
        }
        break;
        
    case 'PUT':
        smart_log(LOG_DEBUG, "Processing PUT request");
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['action'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Action is required']);
            exit;
        }
        
        switch ($input['action']) {
            case 'create_group':
                smart_log(LOG_DEBUG, "Creating group", [
                    'group_name' => $input['group_name'] ?? 'null',
                    'display_name' => $input['display_name'] ?? 'null'
                ]);
                
                $validated = validateGroupName($input['group_name'] ?? '');
                if (isset($validated['error'])) {
                    smart_log(LOG_DEBUG, "Group creation failed: validation error", ['error' => $validated['error']]);
                    http_response_code(400);
                    echo json_encode(['error' => $validated['error']]);
                    exit;
                }
                
                $displayName = trim($input['display_name'] ?? $validated['group_name']);
                
                try {
                    // Check if group already exists (case-insensitive)
                    $stmt = $pdo->prepare('SELECT name FROM groups WHERE LOWER(name) = LOWER(?)');
                    $stmt->execute([$validated['group_name']]);
                    
                    if ($stmt->fetch()) {
                        smart_log(LOG_DEBUG, "Group creation failed: name already taken", ['name' => $validated['group_name']]);
                        http_response_code(400);
                        echo json_encode(['error' => 'Group name already taken']);
                        exit;
                    }
                    
                    // Create the group
                    $stmt = $pdo->prepare('INSERT INTO groups (name, display_name, created_at) VALUES (?, ?, ?)');
                    $stmt->execute([$validated['group_name'], $displayName, time()]);
                    
                    smart_log(LOG_INFO, "Group created", [
                        'name' => $validated['group_name'],
                        'display_name' => $displayName
                    ]);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => "Group '{$displayName}' created successfully.",
                        'group_name' => $validated['group_name']
                    ]);
                } catch (PDOException $e) {
                    smart_log(LOG_ERROR, "Database error creating group", [
                        'error' => $e->getMessage(),
                        'group_name' => $validated['group_name']
                    ]);
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to create group']);
                }
                break;
                
            case 'delete_group':
                smart_log(LOG_DEBUG, "Deleting group", [
                    'group_name' => $input['group_name'] ?? 'null'
                ]);
                
                $validated = validateGroupName($input['group_name'] ?? '');
                if (isset($validated['error'])) {
                    smart_log(LOG_DEBUG, "Group deletion failed: validation error", ['error' => $validated['error']]);
                    http_response_code(400);
                    echo json_encode(['error' => $validated['error']]);
                    exit;
                }
                
                // Prevent deletion of default group
                if ($validated['group_name'] === DEFAULT_GROUP_NAME) {
                    smart_log(LOG_DEBUG, "Group deletion failed: cannot delete default group", ['name' => $validated['group_name']]);
                    http_response_code(400);
                    echo json_encode(['error' => 'Cannot delete the default group']);
                    exit;
                }
                
                try {
                    // Delete all status entries for this group
                    $stmt = $pdo->prepare('DELETE FROM status WHERE group_name = ?');
                    $stmt->execute([$validated['group_name']]);
                    $deletedStatuses = $stmt->rowCount();
                    smart_log(LOG_DEBUG, "Deleted status entries for group", [
                        'group_name' => $validated['group_name'],
                        'count' => $deletedStatuses
                    ]);
                    
                    // Delete the group itself
                    $stmt = $pdo->prepare('DELETE FROM groups WHERE name = ?');
                    $stmt->execute([$validated['group_name']]);
                    $groupDeleted = $stmt->rowCount() > 0;
                    
                    if (!$groupDeleted) {
                        smart_log(LOG_DEBUG, "Group deletion failed: group not found", ['name' => $validated['group_name']]);
                        http_response_code(404);
                        echo json_encode(['error' => 'Group not found']);
                        exit;
                    }
                    
                    smart_log(LOG_INFO, "Group deleted", [
                        'group_name' => $validated['group_name'],
                        'deleted_statuses' => $deletedStatuses
                    ]);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => "Group deleted successfully. Removed {$deletedStatuses} status entries."
                    ]);
                } catch (PDOException $e) {
                    smart_log(LOG_ERROR, "Database error deleting group", [
                        'error' => $e->getMessage(),
                        'group_name' => $validated['group_name']
                    ]);
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to delete group']);
                }
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
                break;
        }
        break;
        
    default:
        smart_log(LOG_WARN, "Invalid HTTP method", ['method' => $_SERVER['REQUEST_METHOD']]);
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

smart_log(LOG_INFO, "=== API.PHP COMPLETE ==="); // Temporary marker to force transfer
