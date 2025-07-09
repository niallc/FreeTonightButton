# Deployment to NearlyFreeSpeech.net

This guide covers deploying the "I'm Free Tonight" app (v1.3.1) to NearlyFreeSpeech.net hosting.

## Directory Structure

NearlyFreeSpeech.net uses:
- **Public files:** `/home/public/` (accessible via web)
- **Private files:** `/home/private/` (not web-accessible)
- **Your domain:** `niallcardin.com` with subdirectory `/freetonight/`

## Deployment

```bash
# Deploy with default settings (recommended)
./deploy.sh

# Or with custom server/username
./deploy.sh ssh.nyc1.nearlyfreespeech.net niallcardin_niallhome
```

The script will:
1. Create `/home/private/freetonight/` directory
2. Set proper permissions
3. Upload public files using rsync

## Configuration

1. **Edit `config.php`:**
   ```bash
   ssh niallcardin_niallhome@ssh.nyc1.nearlyfreespeech.net
   nano /home/public/freetonight/config.php
   ```
   
   Change this line:
   ```php
   define('PRODUCTION_HOSTNAME', 'niallcardin.com');
   ```

## Testing

1. **Visit your app:** `https://niallcardin.com/freetonight/`
2. **Test the API:** `https://niallcardin.com/freetonight/api.php`
3. **Check for errors:** Look in `/home/private/freetonight/php_errors.log`

## Troubleshooting

### Common Issues

1. **404 Errors:** Check that files are in `/home/public/freetonight/`
2. **Database Errors:** Verify `/home/private/freetonight/` exists and is writable
3. **Permission Errors:** Run `chmod 755 /home/private/freetonight`

### Debug Mode

For troubleshooting, temporarily enable error display in `config.php`:
```php
ini_set('display_errors', 1);
define('DEBUG_MODE', true);
```

**Remember to disable after debugging!**

## File Structure on Server

After deployment:
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

## Updating

To update the app:
1. Run `./deploy.sh` to upload new files
2. Update version numbers in `index.html` and `app.js`
3. Test the application 