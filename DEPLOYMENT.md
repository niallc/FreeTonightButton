# Deployment to NearlyFreeSpeech.net

This guide covers deploying the "I'm Free Tonight" app to NearlyFreeSpeech.net hosting.

## NearlyFreeSpeech.net Directory Structure

NearlyFreeSpeech.net uses this structure:
- **Public files:** `/home/public/` (accessible via web)
- **Private files:** `/home/private/` (not web-accessible)
- **Your domain:** `niallcardin.com` with subdirectory `/freetonight/`

## Step 1: Prepare Your Domain

The application will be available at: `https://niallcardin.com/freetonight/`

## Step 2: Deploy Using the Script

```bash
# Deploy with default settings (recommended)
./deploy.sh

# Or with custom server/username
./deploy.sh ssh.nyc1.nearlyfreespeech.net niallcardin_niallhome
```

The script will:
1. Create `/home/private/freetonight/` directory
2. Set proper permissions
3. Upload public files using rsync (excluding logs, databases, etc.)

## Step 3: Configure the Application

1. **Edit `config.php`:**
   ```bash
   ssh niallcardin_niallhome@ssh.nyc1.nearlyfreespeech.net
   nano /home/public/freetonight/config.php
   ```
   
   Change this line:
   ```php
   define('PRODUCTION_HOSTNAME', 'niallcardin.com');
   ```

## Step 4: Test the Deployment

1. **Visit your app:** `https://niallcardin.com/freetonight/`
2. **Test the API:** `https://niallcardin.com/freetonight/api.php`
3. **Check for errors:** Look in `/home/private/freetonight/php_errors.log`

## Troubleshooting

### Common Issues

1. **404 Errors:**
   - Check that files are in `/home/public/freetonight/`
   - Verify domain configuration in `config.php`

2. **Database Errors:**
   - Check that `/home/private/freetonight/` exists and is writable
   - Verify permissions: `chmod 755 /home/private/freetonight`

3. **Permission Errors:**
   ```bash
   ssh niallcardin_niallhome@ssh.nyc1.nearlyfreespeech.net
   ls -la /home/private/freetonight
   chmod 755 /home/private/freetonight
   ```

### Debug Mode

For troubleshooting, temporarily enable error display:
```php
// In public/freetonight/config.php, change:
ini_set('display_errors', 1); // Show errors in browser
define('DEBUG_MODE', true);   // Enable debug logging
```

**Remember to set them back after debugging!**

## File Structure on Server

After deployment, your server should have:
```
/home/public/freetonight/
├── index.html
├── style.css
├── app.js
├── api.php
└── config.php

/home/private/freetonight/
├── friends.db (auto-created)
└── php_errors.log (auto-created)
```

## Security Notes

- The private directory (`/home/private/freetonight/`) is not web-accessible
- Database and logs are stored securely
- All user input is validated and sanitized
- Error messages help with debugging but don't expose sensitive info

## Updating the Application

To update the app:
1. Run `./deploy.sh` to upload new files
2. Update version numbers in `index.html` and `app.js`
3. Test the application
4. Check error logs if issues arise

## Manual Deployment (Alternative)

If you prefer manual upload:

1. **Upload files via SFTP:**
   - Upload `public/freetonight/*` to `/home/public/freetonight/`

2. **Create private directory:**
   ```bash
   ssh niallcardin_niallhome@ssh.nyc1.nearlyfreespeech.net
   mkdir -p /home/private/freetonight
   chmod 755 /home/private/freetonight
   ```

3. **Configure and test as above** 