# I'm Free Tonight - Web App

A simple web application for a trusted group to indicate availability.

## 🏗️ **New Directory Structure**

The app has been refactored to solve SQLite write permission issues on shared hosting:

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