#!/bin/bash

# Test runner for Free Tonight Button
# Usage: ./run_tests.sh [--staging] [--verbose]

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Default values
STAGING=false
VERBOSE=false

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --staging)
            STAGING=true
            shift
            ;;
        --verbose)
            VERBOSE=true
            shift
            ;;
        --help)
            echo "Usage: $0 [--staging] [--verbose]"
            echo ""
            echo "Options:"
            echo "  --staging    Run tests in staging environment"
            echo "  --verbose    Enable verbose output"
            echo "  --help       Show this help message"
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            echo "Use --help for usage information"
            exit 1
            ;;
    esac
done

echo -e "${GREEN}Free Tonight Button - Test Runner${NC}"
echo "=================================="

# Set environment variables
export FREETONIGHT_TEST_DB=1

if [ "$STAGING" = true ]; then
    echo -e "${YELLOW}Running tests in STAGING environment${NC}"
    echo "Make sure you're connected to the staging server"
else
    echo -e "${YELLOW}Running tests in LOCAL environment${NC}"
fi

# Check if PHP is available
if ! command -v php &> /dev/null; then
    echo -e "${RED}Error: PHP is not installed or not in PATH${NC}"
    exit 1
fi

# Check if test file exists
if [ ! -f "tests/test_api.php" ]; then
    echo -e "${RED}Error: Test file tests/test_api.php not found${NC}"
    exit 1
fi

# Run the tests
echo ""
echo "Running tests..."
echo ""

if [ "$VERBOSE" = true ]; then
    php tests/test_api.php --verbose
else
    php tests/test_api.php
fi

# Capture exit code
EXIT_CODE=$?

echo ""
echo "=================================="

if [ $EXIT_CODE -eq 0 ]; then
    echo -e "${GREEN}✅ All tests passed!${NC}"
else
    echo -e "${RED}❌ Some tests failed!${NC}"
fi

exit $EXIT_CODE 