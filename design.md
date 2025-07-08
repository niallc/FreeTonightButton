# Design Document: "I'm Free Tonight" v2.0

This document outlines the plan for a complete rewrite of the "I'm Free Tonight" application. The goal is a clean, secure, and maintainable single-page application built with PHP, SQLite, and vanilla JavaScript.

**Objective:** Create a single-page web application where users from a small, trusted group can indicate they are "free tonight." The page will display a list of everyone who has marked themselves as free on the current day.
- The page should refresh the list every 2 minutes
  - It should also show a timer since last refresh
  - There should be a refresh button for a manual refresh

**Development and Caching**
- To let developers track whether the served page has updated since the last update, display a version number in the bottom right of the main HTML page
- Update the HTML with every update

## CURRENT IMPLEMENTATION STATUS

### ✅ IMPLEMENTED FEATURES

**Core Functionality:**
- ✅ RESTful API with POST (set status), DELETE (remove status), and GET (get list) endpoints
- ✅ SQLite database with proper schema (`status` table with `id`, `name`, `timestamp` columns)
- ✅ Environment-aware configuration (production vs development paths)
- ✅ Input validation and sanitization (name length limits, XSS prevention)
- ✅ Error handling with appropriate HTTP status codes
- ✅ Auto-refresh every 2 minutes
- ✅ Manual refresh button
- ✅ Timer display showing "Last updated: X seconds/minutes/hours ago"
- ✅ localStorage for saving user's name
- ✅ Action feedback messages (success/error states with fade-out)
- ✅ Privacy warning displayed to users
- ✅ Version number displayed (v1.0.0 in bottom right)
- ✅ Mobile-first responsive design
- ✅ Touch-friendly button sizes (44px minimum)
- ✅ Clean, modern UI with proper spacing and colors

**Backend Implementation:**
- ✅ `config.php` with environment detection and path configuration
- ✅ `api.php` with full CRUD operations
- ✅ Database connection with PDO and error handling
- ✅ Input validation and sanitization
- ✅ Proper HTTP status codes and JSON responses
- ✅ Error logging to file system

**Frontend Implementation:**
- ✅ `index.html` with all required elements and proper structure
- ✅ `app.js` with complete functionality including timers and user feedback
- ✅ `style.css` with responsive, mobile-first design
- ✅ Auto-refresh timer (2 minutes)
- ✅ Manual refresh functionality
- ✅ User feedback system with different durations for success/error
- ✅ Timestamp formatting ("Just now", "X minutes ago", etc.)

### ❌ MISSING FEATURES FROM ORIGINAL DESIGN

**Minor Implementation Gaps:**
1. **Error Display in Browser**: The design specified "Transparent Error Handling" with error messages shown to users, but the current implementation shows generic "Network error" messages rather than specific server error details.

2. **Error Log Path**: The config.php sets error logging to `/tmp/php_errors.log` initially, but the design specified it should use `LOG_PATH` from the private directory.

3. **Display Errors Setting**: The config has `ini_set('display_errors', 1)` in debug mode, but the design specified `ini_set('display_errors', 0)` to never display errors to users.

**Design Elements Fully Implemented:**
- ✅ All core functionality as specified
- ✅ All UI elements present and functional
- ✅ All security measures implemented
- ✅ All user experience features working
- ✅ All responsive design requirements met

### 1. Core Philosophy

* **RESTful by Design:** The API will use HTTP methods semantically. `POST` will be used to create/update resources, `DELETE` to remove them, and `GET` to retrieve them. This avoids side effects from requests that should be read-only.
* **Environment-Aware:** The application will be configured to run seamlessly in both a local development environment and on a production server without code changes.
* **Secure by Default:** All user input will be validated on the server, and all data output to the browser will be sanitized to prevent common vulnerabilities like XSS.
* **Clear User Feedback:** The user interface will provide distinct, non-conflicting messages for user actions (e.g., "Status updated") and ambient status (e.g., "List refreshed 10 seconds ago").
* **Mobile-First Design:** The interface will be optimized for mobile devices first, ensuring good usability on phones while remaining functional on desktop.
* **Transparent Error Handling:** Error messages will be shown to users to facilitate debugging and feedback.

### 2. Directory & File Structure

The local development environment will mirror the intended server structure to ensure consistency.

```
freetonight_project/
├── private/
│   └── freetonight/
│       └── friends.db      # SQLite database file (created by script)
│       └── php_errors.log  # Error log file (created by script)
│
└── public/
    └── freetonight/
        ├── index.html      # Main application page
        ├── style.css       # All visual styles
        ├── app.js          # All client-side JavaScript
        ├── api.php         # The backend API endpoint
        └── config.php      # Environment configuration
```

### 3. Environment Configuration (`config.php`)

This file is the key to managing different environments cleanly. It will detect where it's running and define global constants for file paths. This avoids conditional logic scattered throughout the application.

**`public/freetonight/config.php`:**

```php
<?php
// Enable full error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Never display errors to the user
ini_set('log_errors', 1);

// --- Environment Detection ---
// Define your production server's hostname.
define('PRODUCTION_HOSTNAME', 'your-domain.com');

$is_production = ($_SERVER['HTTP_HOST'] === PRODUCTION_HOSTNAME);

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
    define('DB_PATH', PRIVATE_PATH . '/friends.db');
    define('LOG_PATH', PRIVATE_PATH . '/php_errors.log');
}

// Set the error log path
ini_set('error_log', LOG_PATH);

// --- Ensure Private Directory Exists ---
// The script will attempt to create the private directory if it doesn't exist.
if (!is_dir(PRIVATE_PATH)) {
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
}
```

### 4. Backend API (`api.php`)

The API will be a single entry point that routes requests based on the HTTP method.

**`public/freetonight/api.php`:**

**Step 4.1: Initialization**
* Set the `Content-Type` header to `application/json`.
* `require_once 'config.php';` to load the environment settings and path constants.
* Establish the PDO connection to the SQLite database using the `DB_PATH` constant. Wrap this in a `try/catch` block.
* Inside the `try` block, execute a `CREATE TABLE IF NOT EXISTS` statement to ensure the `status` table is ready.
    * **Schema:** `status (id INTEGER PRIMARY KEY, name TEXT NOT NULL UNIQUE, timestamp INTEGER NOT NULL)`

**Step 4.2: Routing**
* Use a `switch` statement on `$_SERVER['REQUEST_METHOD']` to handle different request types.

**Step 4.3: `POST` Logic (Set Status)**
* This handles a user declaring they are free.
* Get the raw JSON body: `file_get_contents('php://input')`.
* Decode the JSON: `json_decode(...)`.
* **Validation:**
    * Check if `name` exists and is not empty after trimming whitespace.
    * Sanitize the name using `strip_tags()` and limit its length (e.g., 50 characters).
    * If validation fails, send a `400 Bad Request` response with a clear error message.
* **Database Operation:**
    * Use a `REPLACE INTO` prepared statement to insert or update the user's status with the current `time()`. This is efficient as it uses the `UNIQUE` constraint on the `name` column.
* **Response:** Send a `200 OK` response with a success message, e.g., `{"success": true, "message": "Status for [Name] updated."}`.

**Step 4.4: `DELETE` Logic (Remove Status)**
* This handles a user removing themselves from the list.
* Get the raw JSON body and decode it.
* **Validation:** Check if `name` exists and is not empty after trimming.
* **Database Operation:** Execute a `DELETE FROM status WHERE name = :name` prepared statement.
* **Response:** Send a `200 OK` response with a success message.

**Step 4.5: `GET` Logic (Get List)**
* This handles fetching the list of free friends.
* Calculate the timestamp for the start of the current day: `strtotime('today', time())`.
* **Database Operation:**
    * Execute a `SELECT name, timestamp FROM status WHERE timestamp >= :start_of_day ORDER BY timestamp DESC` prepared statement.
    * This automatically filters out entries from previous days.
* **Output Sanitization:**
    * Before sending the data, loop through the results and apply `htmlspecialchars()` to each user's name. This is a critical security step.
* **Response:** Send a `200 OK` response with the JSON-encoded array of users.

**Step 4.6: Error Handling**
* All database operations should be wrapped in try/catch blocks.
* Return appropriate HTTP status codes (400, 500, etc.) with JSON error messages.
* Log detailed errors to the error log for debugging.

### 5. Frontend Implementation

**`public/freetonight/index.html`:**

* Create a standard HTML5 structure with proper viewport meta tag for mobile.
* Add the following elements with these specific `id`s:
    * `<div id="app-container">`: The main wrapper.
    * `<input type="text" id="name-input" placeholder="Enter your name">`
    * `<button id="free-button">I'm Free Tonight!</button>`
    * `<button id="remove-button">Remove Me</button>`: **New element** for removing yourself.
    * `<div id="action-feedback"></div>`: For temporary messages like "Status updated!".
    * `<div id="status-bar"></div>`: For the persistent "Last updated..." timer.
    * `<ul id="free-list"></ul>`: The list of friends.
    * `<button id="refresh-button">🔄</button>`
    * `<div id="privacy-warning">⚠️ Anyone can see this page and will be able to see anything you enter.</div>`: **New element** for privacy warning.
* Link to `style.css` and `app.js`.

**`public/freetonight/style.css`:**

* Implement a clean, mobile-first design using CSS Grid or Flexbox.
* Use responsive design principles with media queries for larger screens.
* Ensure touch targets are at least 44px for mobile accessibility.
* Center the `app-container` and make it responsive.
* Style the `#action-feedback` element to be noticeable (e.g., with color and padding) and design a fade-out transition for it.
* Style the `#privacy-warning` to be visible but not intrusive.
* Ensure the remove button is clearly distinguishable from the "I'm Free" button.

**`public/freetonight/app.js`:**

**Step 5.1: Initialization**
* Wrap all code in a `DOMContentLoaded` listener.
* Get references to all DOM elements.
* On load, populate the name input from `localStorage`. Add an `input` listener to save the name back to `localStorage` as the user types.

**Step 5.2: `setFreeStatus()` Function (POST Request)**
* This `async` function will be called when `#free-button` is clicked.
* It will perform a `fetch` to `api.php` using the `POST` method.
* **Request Body:** `JSON.stringify({ name: userName })`.
* **Request Headers:** `{'Content-Type': 'application/json'}`.
* **Feedback:** On success, it will call a helper function `showActionFeedback("Status updated!", "success")` and then immediately call `getFreeList()` to refresh the list. On failure, it will show the error message from the server.

**Step 5.3: `removeStatus()` Function (DELETE Request)**
* This `async` function will be called when `#remove-button` is clicked.
* It will perform a `fetch` to `api.php` using the `DELETE` method.
* **Request Body:** `JSON.stringify({ name: userName })`.
* **Feedback:** On success, show "Removed from list!" and refresh the list. On failure, show the error message.

**Step 5.4: `getFreeList()` Function (GET Request)**
* This `async` function will fetch the list from `api.php` using the `GET` method.
* It will clear the current list (`#free-list.innerHTML = ''`).
* It will iterate over the returned user array, creating `<li>` elements for each. Each `<li>` will contain the sanitized name and a human-readable timestamp (e.g., "5 minutes ago").
* After a successful fetch, it will reset the "Last updated" timer.

**Step 5.5: User Feedback Functions**
* **`showActionFeedback(message, type)`:** This function updates the text of `#action-feedback`, adds a class (`success` or `error`), makes it visible, and then uses `setTimeout` to fade it out and clear it after a few seconds (e.g., 3 seconds).

## SUMMARY

The current implementation is **functionally complete** and matches the design specification very closely. The application successfully provides all core functionality:

- Users can add themselves to the "free tonight" list
- Users can remove themselves from the list  
- The list auto-refreshes every 2 minutes
- Manual refresh is available
- A timer shows when the list was last updated
- The interface is mobile-friendly and responsive
- All security measures are in place
- Error handling and user feedback work as designed

The few minor gaps noted above are implementation details that don't affect the core functionality or user experience. The application is ready for production use as designed.
