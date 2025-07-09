# Deployment Guide

This guide covers deploying the "I'm Free Tonight" app to any web hosting service.

## Example Deployment

The app is currently deployed at: **https://niallcardin.com/freetonight/**

## Prerequisites

- Web hosting with PHP 7.4+ support
- SSH access to your server
- A domain name (optional but recommended)

## Deployment Steps

### 1. Upload Files

Upload the contents of `public/freetonight/` to your web server's public directory.

### 2. Create Private Directory

Create a private directory outside your web root for database and logs:

```bash
# Example for typical hosting
mkdir /home/private/freetonight
chmod 755 /home/private/freetonight

# Or for shared hosting (adjust path as needed)
mkdir /private/freetonight
chmod 755 /private/freetonight
```

### 3. Configure the Application

Edit `config.php` and update these settings:

```php
// Set your domain name
define('PRODUCTION_HOSTNAME', 'yourdomain.com');

// Update paths if needed
define('PRIVATE_DIR', '/path/to/private/freetonight/');
```

### 4. Test the Deployment

1. **Visit your app:** `https://yourdomain.com/freetonight/`
2. **Test the API:** `https://yourdomain.com/freetonight/api.php`
3. **Check for errors:** Look in your private directory for `php_errors.log`

## Hosting Service Examples

### NearlyFreeSpeech.net
- **Public files:** `/home/public/freetonight/`
- **Private files:** `/home/private/freetonight/`
- **See:** `DEPLOYMENT_NFS.md` for specific details

### Shared Hosting (cPanel)
- **Public files:** `public_html/freetonight/`
- **Private files:** `private/freetonight/` (outside public_html)

### VPS/Dedicated Server
- **Public files:** `/var/www/html/freetonight/`
- **Private files:** `/var/private/freetonight/`

## Troubleshooting

### Common Issues

1. **404 Errors:** Check file paths and domain configuration
2. **Database Errors:** Verify private directory exists and is writable
3. **Permission Errors:** Run `chmod 755 /path/to/private/freetonight`

### Debug Mode

For troubleshooting, temporarily enable error display in `config.php`:
```php
ini_set('display_errors', 1);
define('DEBUG_MODE', true);
```

**Remember to disable after debugging!**

## File Structure

After deployment, your server should have:
```
/public/freetonight/
├── index.html
├── style.css
├── app.js
├── api.php
└── config.php

/private/freetonight/
├── friends.db (auto-created)
└── php_errors.log (auto-created)
```

## Security Notes

- Keep the private directory outside your web root
- Database and logs are stored securely
- All user input is validated and sanitized
- Error messages help with debugging but don't expose sensitive info

## Updating

To update the app:
1. Upload new files to your public directory
2. Update version numbers in `index.html` and `app.js`
3. Test the application 