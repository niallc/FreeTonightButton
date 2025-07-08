# Design Document: "I'm Free Tonight" v2.0

This document outlines the plan for a complete rewrite of the "I'm Free Tonight" application. The goal is a clean, secure, and maintainable single-page application built with PHP, SQLite, and vanilla JavaScript.

**Objective:** Create a single-page web application where users from a small, trusted group can indicate they are "free tonight." The page will display a list of everyone who has marked themselves as free on the current day.
- The page should refresh the list every 2 minutes
  - It should also show a timer since last refresh
  - There should be a refresh button for a manual refresh

**Development and Caching**
- To let developers track whehter the served page has updated since the last update, display a version number in the bottom right of the main HTML page
- Update the HTML with every update

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
* **`updateTimer()`:** This function will run on a `setInterval` (every second). It calculates the time since the last successful refresh and updates the text of `#status-bar`.

**Step 5.6: Event Listeners & Timers**
* Attach click listeners to the "I'm Free", "Remove Me", and "Refresh" buttons.
* Set up an interval to call `getFreeList()` automatically every 2 minutes.
* Set up the one-second interval for `updateTimer()`.
* Call `getFreeList()` once on initial page load.

### 6. Deployment & Server Setup

1.  **Upload Files:**
    * Upload the contents of `public/freetonight/` to your server's public web root (e.g., `/home/public_html/freetonight/` or similar).
    * Manually create the private directory on your server: `/home/private/freetonight/`.
2.  **Set Permissions:**
    * The `private` directory needs to be writable by the web server. A common permission setting is `755`.
    * `chmod 755 /home/private/freetonight`
3.  **Configure `config.php`:**
    * Edit the `config.php` file on the server and replace `'your-domain.com'` with your actual domain name.
4.  **Test:**
    * Load the page in your browser. The PHP script should automatically create the database file and error log in the private directory. Check the directory to confirm. If there are errors, check the `php_errors.log` file for details.

### 7. Future Development Ideas

This section tracks potential enhancements for future versions:

**High Priority:**
* **Remove functionality:** Allow users to remove themselves from the list (implemented in v2.0)

**Medium Priority:**
* **WebSocket support:** Real-time updates without polling (if server setup is simple)
* **Avatar support:** Allow users to add profile pictures or initials
* **Status messages:** Allow users to add notes like "I'm free tonight and want to see a movie"
* **Group functionality:** Support for different friend groups or lists

**Low Priority:**
* **Historical data:** Store and display who was free on previous nights
* **Advanced UI:** More sophisticated visual design with animations
* **Authentication:** Optional user accounts for more features
* **Mobile app:** Native mobile application wrapper

**Technical Considerations:**
* Design the database schema to accommodate future features (e.g., groups, avatars)
* Structure the API to be extensible for new endpoints
* Keep the codebase modular to facilitate adding new features
