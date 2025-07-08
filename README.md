# I'm Free Tonight v2.0

A simple web application where friends can indicate they are "free tonight" and see who else is available.

## Features

- ✅ Add yourself to the "free tonight" list
- ✅ Remove yourself from the list
- ✅ See who else is free tonight
- ✅ Auto-refresh every 2 minutes
- ✅ Mobile-first responsive design
- ✅ Privacy warning for users
- ✅ Automatic expiration at midnight
- ✅ Error handling and user feedback
- ✅ Development version tracking

## Quick Start

### Local Development

1. **Start the development server:**
   ```bash
   php dev_server.php
   ```

2. **Open your browser:**
   ```
   http://localhost:8000/freetonight/
   ```

3. **Test the application:**
   - Enter your name and click "I'm Free Tonight!"
   - Try the "Remove Me" button
   - Check that the list refreshes automatically
   - Test on mobile devices

### Running Tests

```bash
php tests/test_api.php
```

This will test:
- Adding users to the list
- Removing users from the list
- Input validation (empty names, long names, special characters)
- List retrieval and filtering
- Expired entries (entries from previous days)

## Project Structure

```
FreeTonightButton2/
├── private/freetonight/          # Database and logs (auto-created)
│   ├── friends.db               # SQLite database
│   └── php_errors.log          # Error log
├── public/freetonight/          # Web-accessible files
│   ├── index.html              # Main application page
│   ├── style.css               # Mobile-first styles
│   ├── app.js                  # Client-side JavaScript
│   ├── api.php                 # Backend API endpoint
│   └── config.php              # Environment configuration
├── tests/
│   └── test_api.php            # API test suite
├── dev_server.php              # Development server script
├── design.md                   # Design documentation
└── README.md                   # This file
```

## API Endpoints

### GET /api.php
Returns the list of users who are free tonight.

**Response:**
```json
[
  {
    "name": "Alice",
    "timestamp": 1640995200
  },
  {
    "name": "Bob", 
    "timestamp": 1640994900
  }
]
```

### POST /api.php
Add yourself to the "free tonight" list.

**Request:**
```json
{
  "name": "Your Name"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Status for Your Name updated."
}
```

### DELETE /api.php
Remove yourself from the "free tonight" list.

**Request:**
```json
{
  "name": "Your Name"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Your Name removed from list."
}
```

## Development

### Version Tracking

The application displays a version number in the bottom-right corner. Update this in:
- `public/freetonight/index.html` (line with `v1.0.0`)
- `public/freetonight/app.js` (APP_VERSION constant)

### Adding Features

1. **Backend changes:** Modify `api.php` and test with `tests/test_api.php`
2. **Frontend changes:** Update `app.js` and `style.css` as needed
3. **Database changes:** Update the schema in `api.php` and add migration tests

### Testing Strategy

- **Unit tests:** `tests/test_api.php` covers core API functionality
- **Manual testing:** Use the development server to test the full application
- **Mobile testing:** Test on various devices and screen sizes
- **Error testing:** Try invalid inputs, network failures, etc.

## Deployment

### Local to Server

1. **Upload files:** Copy `public/freetonight/` to your server's web root
2. **Create private directory:** `mkdir /home/private/freetonight`
3. **Set permissions:** `chmod 755 /home/private/freetonight`
4. **Update config:** Edit `config.php` with your domain name
5. **Test:** Visit your domain/freetonight/

### Environment Configuration

The application automatically detects local vs production environment:
- **Local:** Uses relative paths from the project structure
- **Production:** Uses absolute paths on the server

Update `PRODUCTION_HOSTNAME` in `config.php` with your actual domain.

## Security Considerations

- All user input is validated and sanitized
- Output is HTML-escaped to prevent XSS
- No authentication (by design for this use case)
- Privacy warning displayed to users
- Error messages help with debugging

## Future Development

See `design.md` for planned features:
- WebSocket support for real-time updates
- Avatar/profile picture support
- Status messages and notes
- Group functionality for different friend circles
- Historical data tracking

## Troubleshooting

### Common Issues

1. **Database not created:** Check that the private directory is writable
2. **API errors:** Check `private/freetonight/php_errors.log`
3. **CORS issues:** Make sure you're accessing via the development server
4. **Mobile issues:** Test with different browsers and screen sizes

### Debug Mode

For development, you can temporarily enable error display by changing in `config.php`:
```php
ini_set('display_errors', 1); // Show errors in browser
```

## License

This is a personal project for friends to coordinate social activities. 