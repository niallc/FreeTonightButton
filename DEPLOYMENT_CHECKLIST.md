# Deployment Checklist

## Pre-Deployment
- [ ] All tests pass locally (`php tests/test_api.php`)
- [ ] Application works on local dev server (`php dev_server.php`)
- [ ] You have your NearlyFreeSpeech.net server details
- [ ] You have your domain name ready

## Deployment Steps
- [ ] Upload files using `./deploy.sh` or manual upload
- [ ] Create `/home/private/freetonight/` directory on server
- [ ] Set permissions: `chmod 755 /home/private/freetonight`
- [ ] Edit `config.php` to set your domain name
- [ ] Test the API endpoint: `https://yourdomain.com/freetonight/api.php`
- [ ] Test the main page: `https://yourdomain.com/freetonight/`
- [ ] Test adding/removing users
- [ ] Check error logs: `/home/private/freetonight/php_errors.log`

## Post-Deployment Verification
- [ ] Database file created: `/home/private/freetonight/friends.db`
- [ ] Error log file created: `/home/private/freetonight/php_errors.log`
- [ ] No errors in browser console
- [ ] Mobile testing works
- [ ] Auto-refresh works (wait 2 minutes)
- [ ] Privacy warning displays
- [ ] Version number shows in bottom-right

## Troubleshooting
If something doesn't work:

1. **Enable debug mode:**
   ```php
   // In config.php, change:
   define('DEBUG_MODE', true);
   ```

2. **Check error logs:**
   ```bash
   ssh username@server
   tail -f /home/private/freetonight/php_errors.log
   ```

3. **Test API directly:**
   ```bash
   curl -X GET https://yourdomain.com/freetonight/api.php
   curl -X POST -H "Content-Type: application/json" \
     -d '{"name":"TestUser"}' \
     https://yourdomain.com/freetonight/api.php
   ```

4. **Check file permissions:**
   ```bash
   ssh username@server
   ls -la /home/private/freetonight/
   ls -la /home/public/freetonight/
   ```

## Common Issues
- **404 errors:** Check file paths and domain configuration
- **Database errors:** Check private directory permissions
- **CORS errors:** Make sure you're using HTTPS
- **Empty responses:** Check PHP error logs

## Security Reminder
- [ ] Set `DEBUG_MODE` back to `false` after troubleshooting
- [ ] Set `display_errors` back to `0` after debugging
- [ ] Verify private directory is not web-accessible 