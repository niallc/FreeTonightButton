
# FreeTonight Button: Project Context for Gemini

## Project Goal

The "I'm Free Tonight" button is a simple web application for a trusted group of friends to indicate their availability for social events on any given evening.

## Core Functionality

1.  **User Input:** A user enters their name into a text field.
2.  **Availability:** The user clicks the "I'm Free Tonight!" button.
3.  **Display:** The application displays a list of everyone who has marked themselves as "free" for the current day.
4.  **Persistence:** The user's name is saved in their browser's local storage so they don't have to type it in every time.
5.  **Real-time Updates:** The list of "free" friends automatically refreshes.

## Key Files

This document contains the concatenated content of the core project files.

---

### `index.html`

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>I'm Free Tonight</title>
    <link rel="stylesheet" href="style.css?v=1.1.0&cb=1751883200">
</head>
<body>
    <div id="app-container">
        <h1>I'm Free Tonight</h1>
        
        <div class="input-section">
            <input type="text" id="name-input" placeholder="Enter your name">
            <button id="free-button">I'm Free Tonight!</button>
        </div>
        
        <div id="status-bar"></div>
        
        <div class="list-section">
            <h2>Who's Free Tonight</h2>
            <button id="refresh-button" class="refresh-btn">🔄 Refresh</button>
            <ul id="free-list"></ul>
        </div>
    </div>
    
    <script src="app.js?v=1.1.0&cb=1751883200" defer></script>
    <div class="version-info">v1.1.0</div>
</body>
</html>
```

---

### `app.js`

```javascript
// Version tracking
const APP_VERSION = '1.1.0';
console.log('FreeTonight App v' + APP_VERSION + ' loaded');

document.addEventListener('DOMContentLoaded', function() {
    // DOM references
    const nameInput = document.getElementById('name-input');
    const freeButton = document.getElementById('free-button');
    const statusBar = document.getElementById('status-bar');
    const freeList = document.getElementById('free-list');
    const refreshButton = document.getElementById('refresh-button');
    
    // Global variables
    let lastRefreshTime = new Date();
    
    // Initialize name from localStorage
    const savedName = localStorage.getItem('userName');
    if (savedName) {
        nameInput.value = savedName;
    }
    
    // Save name to localStorage on input
    nameInput.addEventListener('input', function(event) {
        localStorage.setItem('userName', event.target.value);
    });
    
    // Set free status function
    async function setFreeStatus() {
        const userName = nameInput.value.trim();
        
        if (!userName) {
            statusBar.textContent = 'Please enter your name first.';
            return;
        }
        
        try {
            freeButton.disabled = true;
            freeButton.textContent = 'Updating...';
            
            const url = './api.php?action=set&name=' + encodeURIComponent(userName) + '&t=' + Date.now();
            console.log('Making GET request to:', url);
            
            const response = await fetch(url, {
                headers: {
                    'Cache-Control': 'no-cache'
                }
            });
            
            console.log('Response status:', response.status);
            console.log('Response ok:', response.ok);
            
            const result = await response.json();
            console.log('Response data:', result);
            
            if (result.success) {
                statusBar.textContent = `Successfully marked ${userName} as free tonight!`;
                await getFreeList(); // Refresh the list immediately
            } else {
                statusBar.textContent = result.error || 'Failed to update status.';
            }
            
        } catch (error) {
            statusBar.textContent = 'Network error. Please try again.';
            console.error('Error details:', error);
            console.error('Error message:', error.message);
            if (error.response) {
                console.error('Response status:', error.response.status);
                console.error('Response text:', await error.response.text());
            }
        } finally {
            freeButton.disabled = false;
            freeButton.textContent = "I'm Free Tonight!";
        }
    }
    
    // Get free list function
    async function getFreeList() {
        try {
            const response = await fetch('./api.php?t=' + Date.now(), {
                headers: {
                    'Cache-Control': 'no-cache'
                }
            });
            const users = await response.json();
            
            // Clear current list
            freeList.innerHTML = '';
            
            if (users.length === 0) {
                freeList.innerHTML = '<li style="text-align: center; color: #999; font-style: italic;">No one has marked themselves as free yet.</li>';
            } else {
                users.forEach(user => {
                    const li = document.createElement('li');
                    const timeAgo = getTimeAgo(user.timestamp);
                    li.textContent = `${user.name} (${timeAgo})`;
                    freeList.appendChild(li);
                });
            }
            
            lastRefreshTime = new Date();
            
        } catch (error) {
            console.error('Error fetching free list:', error);
            console.error('Error message:', error.message);
            if (error.response) {
                console.error('Response status:', error.response.status);
                console.error('Response text:', await error.response.text());
            }
            statusBar.textContent = 'Failed to load the list. Please refresh the page.';
        }
    }
    
    // Helper function to convert timestamp to human-readable time ago
    function getTimeAgo(timestamp) {
        const now = Math.floor(Date.now() / 1000);
        const diff = now - timestamp;
        
        if (diff < 60) {
            return 'just now';
        } else if (diff < 3600) {
            const minutes = Math.floor(diff / 60);
            return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
        } else if (diff < 86400) {
            const hours = Math.floor(diff / 3600);
            return `${hours} hour${hours > 1 ? 's' : ''} ago`;
        } else {
            const days = Math.floor(diff / 86400);
            return `${days} day${days > 1 ? 's' : ''} ago`;
        }
    }
    
    // Health check function
    async function checkHealth() {
        try {
            const response = await fetch('./api.php?t=' + Date.now(), { 
                method: 'HEAD',
                headers: {
                    'Cache-Control': 'no-cache'
                }
            });
            if (response.ok) {
                console.log('Server health check: OK');
            } else {
                console.warn('Server health check: Failed');
                statusBar.textContent = 'Warning: Server health check failed';
            }
        } catch (error) {
            console.error('Health check error:', error);
            statusBar.textContent = 'Warning: Cannot connect to server';
        }
    }
    
    // Update timer function
    function updateTimer() {
        const now = new Date();
        const secondsSinceRefresh = Math.floor((now - lastRefreshTime) / 1000);
        statusBar.textContent = `Last updated: ${secondsSinceRefresh} second${secondsSinceRefresh !== 1 ? 's' : ''} ago.`;
    }
    
    // Event listeners
    freeButton.addEventListener('click', setFreeStatus);
    refreshButton.addEventListener('click', async function() {
        refreshButton.textContent = '🔄 Refreshing...';
        refreshButton.disabled = true;
        await getFreeList();
        refreshButton.textContent = '🔄 Refresh';
        refreshButton.disabled = false;
    });
    
    // Allow Enter key to submit
    nameInput.addEventListener('keypress', function(event) {
        if (event.key === 'Enter') {
            setFreeStatus();
        }
    });
    
    // Auto-refresh every 2 minutes
    setInterval(getFreeList, 120000);
    
    // Update timer every second
    setInterval(updateTimer, 1000);
    
    // Health check on startup
    checkHealth();
    
    // Initial load
    getFreeList();
    
    // Log version for debugging
    console.log('App initialized with version:', APP_VERSION);
});
```

---

### `api.php`

```php
<?php
header('Content-Type: application/json');

// Environment detection
$is_local = ($_SERVER['HTTP_HOST'] === 'localhost:8000' || $_SERVER['HTTP_HOST'] === '127.0.0.1:8000');

// Enable error logging
error_reporting(E_ALL);
ini_set('log_errors', 1);

if ($is_local) {
    // Local development paths
    ini_set('error_log', 'php_errors.log');
    // Use absolute path for local development
    $db_file = dirname(__FILE__) . '/../private/freetonight/friends.db';
} else {
    // Server paths
    ini_set('error_log', '/home/private/freetonight/php_errors.log');
    $db_file = '/home/private/freetonight/friends.db';
}

// Log all API calls with more detail
error_log("API call: " . $_SERVER['REQUEST_METHOD'] . " from " . $_SERVER['REMOTE_ADDR'] . " at " . date('Y-m-d H:i:s') . " - User Agent: " . $_SERVER['HTTP_USER_AGENT']);
error_log("Environment: " . ($is_local ? 'LOCAL' : 'SERVER'));
error_log("Database path: " . $db_file);

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
```

---

### `design.md`

```markdown
# Agent Coding Plan: "I'm Free Tonight" Application

## 1. Project Goal & Technology Stack

**Objective:** Create a single-page web application where users from a small, trusted group can indicate they are "free tonight." The page will display a list of everyone who has marked themselves as free on the current day.

**Technology Stack:**
-   **Backend:** PHP
-   **Database:** SQLite (via PHP's PDO extension)
-   **Frontend:** HTML, CSS, vanilla JavaScript (ES6+)
-   **Data Format:** JSON for all client-server communication.

## 2. File Structure

Create the following files in the root directory:
-   `index.html`: The main user interface.
-   `style.css`: For all visual styling.
-   `app.js`: All client-side JavaScript logic.
-   `api.php`: The server-side backend API.
-   `friends.db`: This will be created automatically by the PHP script.

## 3. Phase 1: Backend API (`api.php`)

**Goal:** Build the server-side logic to manage and retrieve user statuses. This is the foundation and should be built first.

**Step 1.1: Initial Setup & Database Connection**
-   At the top of the script, set the response header to `header('Content-Type: application/json');`.
-   Define the path to the SQLite database file: `$db_file = 'friends.db';`.
-   Establish a connection using `new PDO('sqlite:' . $db_file);`. Wrap this in a `try/catch` block to handle connection errors.
-   Immediately after connecting, execute a `CREATE TABLE IF NOT EXISTS` query to ensure the `status` table exists.
    -   **Schema:** `status (id INTEGER PRIMARY KEY, name TEXT NOT NULL UNIQUE, timestamp INTEGER NOT NULL)`
    -   The `UNIQUE` constraint on `name` is critical for simplifying the update logic.

**Step 1.2: API Routing**
-   Use `$_SERVER['REQUEST_METHOD']` to determine if the request is `GET` or `POST`. Create an `if/elseif` block to handle these two methods.

**Step 1.3: Implement POST Request Logic (Setting Status)**
-   This block handles a user declaring they are free.
-   Retrieve the incoming JSON payload using `file_get_contents('php://input')` and decode it with `json_decode()`. The payload will be `{"name": "username"}`.
-   **Input Sanitization:** Sanitize the name using `strip_tags()` and limit length to 50 characters. Validate that the name is not empty after sanitization.
-   **Core Logic:** Use SQLite's `REPLACE INTO` statement. This is the simplest way to handle both new and existing users. `REPLACE INTO status (name, timestamp) VALUES (:name, :timestamp);`.
    -   This command will insert a new row if the `name` doesn't exist, or it will delete the old row and insert a new one if the `name` already exists, effectively updating the timestamp.
-   Bind the user's name and the current UNIX timestamp (`time()`) to the query.
-   Return a JSON success message, e.g., `{"success": true, "name": "username"}`.

**Step 1.4: Implement GET Request Logic (Fetching the List)**
-   This block handles requests to see who is free.
-   **Core Logic:** Define "tonight" as any time after the beginning of the current calendar day in the server's timezone.
    -   Calculate the timestamp for the start of today: `$start_of_day = strtotime('today', time());`.
-   Prepare and execute a SQL query: `SELECT name, timestamp FROM status WHERE timestamp >= :start_of_day`.
-   Bind the `$start_of_day` value.
-   Fetch all results into an associative array.
-   **Output Sanitization:** Sanitize all names using `htmlspecialchars()` before sending to client to prevent XSS attacks.
-   Encode the resulting array into JSON and `echo` it as the response.

## 4. Phase 2: Frontend Static Files (`index.html`, `style.css`)

**Goal:** Create the static view layer of the application.

**Step 2.1: `index.html`**
-   Create a standard HTML5 document.
-   In the `<body>`, create the following elements with these specific `id`s:
    -   A main container: `<div id="app-container">`
    -   A text input for the user's name: `<input type="text" id="name-input" placeholder="Enter your name">`
    -   The main action button: `<button id="free-button">I'm Free Tonight!</button>`
    -   A status bar for messages and the timer: `<div id="status-bar"></div>`
    -   An unordered list to display the names: `<ul id="free-list"></ul>`
-   Link to `style.css` in the `<head>`.
-   Link to `app.js` at the end of the `<body>` using `<script src="app.js" defer></script>`.

**Step 2.2: `style.css`**
-   Implement basic, clean styling.
-   Center the `#app-container` on the page.
-   Style the input field and button to be large and easy to use on mobile.
-   Style the `#free-list` to be legible (e.g., add padding to list items).

## 5. Phase 3: Frontend Logic (`app.js`)

**Goal:** Bring the page to life by connecting user actions to the backend API.

**Step 3.1: Initialization and DOM References**
-   Wrap all code in a `DOMContentLoaded` event listener to ensure the HTML is loaded before the script runs.
-   Get references to all key DOM elements (`#name-input`, `#free-button`, `#status-bar`, `#free-list`).

**Step 3.2: Local Storage for Name Persistence**
-   On script load, check `localStorage.getItem('userName')`. If a name exists, populate the `#name-input` with it.
-   Add an `input` event listener to `#name-input`. On every keystroke, save the current value to local storage: `localStorage.setItem('userName', event.target.value)`.

**Step 3.3: `setFreeStatus` Function**
-   Create an `async` function named `setFreeStatus`.
-   Add a `click` event listener to `#free-button` that calls this function.
-   Inside the function:
    -   Get the name from `#name-input`. Perform basic validation (e.g., it shouldn't be empty).
    -   Use `fetch('./api.php', { ... })` to make a `POST` request.
    -   **Request Headers:** `'Content-Type': 'application/json'`.
    -   **Request Body:** `JSON.stringify({ name: userName })`.
    -   After the fetch resolves successfully, immediately call the `getFreeList` function to show the updated list.

**Step 3.4: `getFreeList` Function**
-   Create an `async` function named `getFreeList`.
-   Call this function once when the script first loads.
-   Inside the function:
    -   Use `fetch('./api.php')` to make a `GET` request.
    -   Parse the JSON response: `const users = await response.json();`.
    -   Clear the current list: `#free-list.innerHTML = '';`.
    -   Iterate through the `users` array. For each user object, create an `<li>` element and append it to `#free-list`.
    -   The text of the `<li>` should include the user's name and a human-readable time difference (e.g., "Alice (5 minutes ago)"). This will require a helper function to convert the UNIX timestamp difference into a string.

## 6. Phase 4: Polling and User Feedback

**Goal:** Add auto-refresh and a visual timer to improve user experience.

**Step 4.1: Auto-Refresh**
-   In the main `DOMContentLoaded` block, set up an interval to automatically refresh the list: `setInterval(getFreeList, 120000);` (every 2 minutes).

**Step 4.2: "Last Updated" Timer**
-   Declare a variable `lastRefreshTime = new Date();` in the global scope of the script.
-   In the `getFreeList` function, after a successful fetch, update this variable: `lastRefreshTime = new Date();`.
-   Create a new `setInterval` that runs every second: `setInterval(updateTimer, 1000);`.
-   Create the `updateTimer` function. It should:
    -   Calculate the seconds since `lastRefreshTime`.
    -   Update the text of the `#status-bar` to show: `Last updated: [X] seconds ago.`.
```

---

### `README.md`

```markdown
# I'm Free Tonight - Web App

A simple web application for a trusted group to indicate availability.

## 🏗️ **Directory Structure**

The app has been refactored to solve SQLite write permission issues on shared hosting:

**Local Development:**
```
FreeTonightButton/
├── public/           # Web-accessible files
│   ├── index.html
│   ├── style.css
│   ├── app.js
│   ├── api.php
│   └── test_api.html
└── private/freetonight/  # Private, writable files
    └── friends.db
```

**Server Deployment:**
```
/home/public/freetonight/     # Web-accessible files
├── index.html
├── style.css
├── app.js
├── api.php
└── test_api.html

/home/private/freetonight/    # Private, writable files
├── friends.db
└── php_errors.log
```

## 🚀 **Deployment**

### **Step 1: Upload Files**

**To `/home/public/freetonight/`:**
- `index.html`
- `style.css`
- `app.js`
- `api.php`
- `test_api.html` (optional)

**To `/home/private/freetonight/`:**
- Create the directory: `mkdir -p /home/private/freetonight`
- The database and log files will be created automatically

### **Step 2: Set Permissions**

```bash
# Create private directory
mkdir -p /home/private/freetonight

# Set directory permissions
chmod 755 /home/private/freetonight

# If database exists, set file permissions
chmod 666 /home/private/freetonight/friends.db
```

## 🔧 **Key Changes in v1.1.0**

- **Database Location**: Moved from `./friends.db` to `/home/private/freetonight/friends.db`
- **Error Logs**: Moved to `/home/private/freetonight/php_errors.log`
- **SQLite Journal Files**: Can now be created in the writable private directory

## 🎯 **Why This Fixes the Issue**

The original error `SQLSTATE[HY000]: General error: 8 attempt to write a readonly database` occurred because:

1. SQLite needs to create temporary journal files (e.g., `friends.db-journal`) in the same directory as the database
2. Shared hosting typically doesn't allow write access to the public web root
3. Moving the database to `/home/private/` provides the necessary write permissions

## 📝 **Testing**

Use `test_api.html` to verify both GET and SET operations work correctly.

## 🔍 **Troubleshooting**

- Check `/home/private/freetonight/php_errors.log` for detailed error messages
- Verify directory permissions: `ls -la /home/private/freetonight/`
- Test database connectivity: `php -r "echo extension_loaded('sqlite3') ? 'SQLite OK' : 'SQLite missing';"`
```
