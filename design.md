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

