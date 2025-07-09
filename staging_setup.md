# Staging Environment Setup

## Overview

A staging environment is a copy of your production environment where you can safely run tests and verify changes before deploying to production.

## Why Staging is Important

1. **Safety**: Test changes without affecting real users
2. **Confidence**: Verify deployments work before going live
3. **Debugging**: Reproduce production issues in a controlled environment
4. **Testing**: Run comprehensive tests in an environment that mirrors production

## Setting Up Staging

### Option 1: Separate Server (Recommended)

1. **Create a staging subdomain**:
   ```
   staging.niallcardin.com
   ```

2. **Deploy the same code** as production but with staging configuration

3. **Use separate database** for staging data

4. **Configure staging-specific settings**:
   ```php
   // In config.php for staging
   define('PRODUCTION_HOSTNAME', 'niallcardin.com');
   define('STAGING_HOSTNAME', 'staging.niallcardin.com');
   
   $is_production = ($_SERVER['HTTP_HOST'] === PRODUCTION_HOSTNAME);
   $is_staging = ($_SERVER['HTTP_HOST'] === STAGING_HOSTNAME);
   ```

### Option 2: Local Staging (Development)

Use your local environment with production-like settings:

```bash
# Set environment to simulate production
export FREETONIGHT_ENV=staging
export FREETONIGHT_TEST_DB=1

# Run tests
./run_tests.sh --verbose
```

## Staging Environment Checklist

- [ ] Same PHP version as production
- [ ] Same server configuration (Apache/Nginx)
- [ ] Same database structure
- [ ] Same file permissions
- [ ] Same environment variables
- [ ] Separate database (not production data)
- [ ] Staging-specific domain/subdomain
- [ ] Monitoring and logging enabled

## Running Tests in Staging

```bash
# SSH into staging server
ssh user@staging.niallcardin.com

# Navigate to project
cd /path/to/freetonight

# Run tests
./run_tests.sh --verbose

# Or run directly
php tests/test_api.php --verbose
```

## Pre-Deployment Testing Workflow

1. **Local Development**: Write and test code locally
2. **Staging Deployment**: Deploy to staging environment
3. **Staging Tests**: Run full test suite in staging
4. **Manual Testing**: Test key functionality manually
5. **Production Deployment**: Only after staging tests pass

## Monitoring Staging

- Set up error logging for staging
- Monitor test results
- Track performance metrics
- Alert on test failures

## Best Practices

1. **Keep staging updated**: Regularly sync with production code
2. **Test data management**: Use realistic but safe test data
3. **Automated testing**: Run tests automatically on staging deployments
4. **Documentation**: Keep staging setup documented
5. **Access control**: Limit who can access staging environment 