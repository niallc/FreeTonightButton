# I'm Free Tonight v2.0

A simple web application where friends can indicate they are "free tonight" and see who else is available.

## Features

- Add/remove yourself from the "free tonight" list
- See who else is free tonight with auto-refresh
- Group functionality for separate friend circles
- Mobile-first responsive design
- Privacy-focused with automatic expiration at midnight

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

### Running Tests

```bash
# Run all tests
./run_all_tests.sh

# Run specific test suites
php tests/test_api.php          # Core API functionality
php tests/test_groups_api.php   # Group functionality
php tests/test_groups_http.php  # HTTP integration tests
```

## Project Structure

```
FreeTonightButton/
├── private/freetonight/          # Database and logs (auto-created)
├── public/freetonight/           # Web-accessible files
│   ├── index.html               # Main application page
│   ├── style.css                # Mobile-first styles
│   ├── app.js                   # Client-side JavaScript
│   ├── api.php                  # Backend API endpoint
│   └── config.php               # Environment configuration
├── tests/                       # Test suites
├── dev_server.php               # Development server script
├── run_all_tests.sh            # Test runner script
├── design.md                    # Design documentation
├── DEPLOYMENT.md               # Deployment guide
└── README.md                   # This file
```

## API

The application provides a simple REST API:

- `GET /api.php` - Get list of users free tonight
- `POST /api.php` - Add yourself to the list
- `DELETE /api.php` - Remove yourself from the list

All endpoints accept and return JSON data.

## Development

### Version Updates

Update the version number in:
- `public/freetonight/index.html` (line with `v1.3.1`)
- `public/freetonight/app.js` (APP_VERSION constant)

### Debug Mode

Add `?debug=true` to the URL for detailed console logging during development.

## Deployment

See `DEPLOYMENT.md` for deployment instructions to any web hosting service. The app is currently deployed at https://niallcardin.com/freetonight/ as an example.

## Future Development

See `design.md` for planned features including:
- WebSocket support for real-time updates
- Avatar/profile picture support
- Enhanced group management features 