# Testing Guide for Free Tonight Button

## Overview

This project uses a simple but effective testing strategy that works across local development and staging environments.

## Testing Philosophy

- **Never test in production** - Use staging environments that mirror production
- **Isolated test data** - Tests use separate databases to avoid affecting real data
- **Environment-aware** - Tests automatically adapt to local vs staging environments
- **Simple and reliable** - Focus on core functionality rather than complex test frameworks

## Running Tests

```bash
# Run all tests
php tests/test_api.php

# Run with verbose output
php tests/test_api.php --verbose
```

## Test Database Strategy

The project uses environment variables to automatically switch between databases:

- **Local Development**: Uses `friends_test.db` when `FREETONIGHT_TEST_DB=1`
- **Staging**: Uses `friends_test.db` when `FREETONIGHT_TEST_DB=1`
- **Production**: Never runs tests (safety first!)

## Test Categories

### 1. Database Operations
- Adding users with various name formats
- Removing users (existing and non-existent)
- Handling special characters and edge cases

### 2. Time-based Logic
- Expired entries cleanup
- Midnight boundary conditions
- Grace periods for time entries

### 3. Input Validation
- Empty names
- Names too long
- Special characters and XSS prevention

### 4. API Response Format
- JSON structure validation
- HTTP status codes
- Error message consistency

## Adding New Tests

1. Add your test method to the `FreeTonightTest` class
2. Follow the naming convention: `test[Description]`
3. Add the test name to the `$tests` array in `runAllTests()`
4. Ensure your test cleans up after itself

Example:
```php
private function testNewFeature() {
    // Setup
    $testData = 'test_value';
    
    // Execute
    $result = $this->performTestOperation($testData);
    
    // Assert
    if ($result !== 'expected_value') {
        throw new Exception('Test failed: expected expected_value, got ' . $result);
    }
    
    // Cleanup (if needed)
    $this->pdo->exec('DELETE FROM status WHERE name = "test_value"');
}
```

## Troubleshooting

### Common Issues

1. **Database permissions**: Ensure the private directory is writable
2. **Path issues**: Check that `config.php` correctly resolves paths
3. **Environment variables**: Verify `FREETONIGHT_TEST_DB=1` is set

### Debug Mode

Tests automatically run in debug mode when `DEBUG_MODE=true` in `config.php`.

## Best Practices

1. **Keep tests simple** - Focus on core functionality
2. **Test edge cases** - Empty strings, long inputs, special characters
3. **Clean up** - Always remove test data after tests
4. **Isolate tests** - Each test should be independent
5. **Meaningful assertions** - Clear error messages when tests fail 