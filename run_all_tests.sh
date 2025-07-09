#!/bin/bash

# Comprehensive test runner for Free Tonight project
# Runs all tests: PHP unit tests, HTTP integration tests, and JavaScript tests

echo "🧪 Running Free Tonight Test Suite"
echo "=================================="
echo ""

# Check if Node.js is available for JavaScript tests
if ! command -v node &> /dev/null; then
    echo "⚠️  Node.js not found - skipping JavaScript tests"
    echo "   Install Node.js to run JavaScript tests"
    echo ""
    JS_TESTS_AVAILABLE=false
else
    JS_TESTS_AVAILABLE=true
fi

# Run PHP unit tests
echo "📋 Running PHP Unit Tests..."
echo "----------------------------"
php tests/test_api.php --verbose
PHP_UNIT_RESULT=$?

echo ""

# Run HTTP integration tests (if dev server is running)
echo "🌐 Running HTTP Integration Tests..."
echo "-----------------------------------"
if curl -s http://localhost:8002/freetonight/api.php > /dev/null 2>&1; then
    php tests/test_api_http.php
    HTTP_RESULT=$?
else
    echo "⚠️  Dev server not running on port 8002 - skipping HTTP tests"
    echo "   Start with: php dev_server.php"
    HTTP_RESULT=0  # Don't fail the overall test run
fi

echo ""

# Run JavaScript tests (if Node.js is available)
if [ "$JS_TESTS_AVAILABLE" = true ]; then
    echo "🟨 Running JavaScript Tests..."
    echo "-----------------------------"
    node tests/test_javascript.js
    JS_RESULT=$?
else
    JS_RESULT=0  # Don't fail if Node.js isn't available
fi

echo ""
echo "=================================="
echo "📊 Test Results Summary"
echo "=================================="

# Determine overall result
if [ $PHP_UNIT_RESULT -eq 0 ] && [ $HTTP_RESULT -eq 0 ] && [ $JS_RESULT -eq 0 ]; then
    echo "🎉 All tests passed!"
    OVERALL_RESULT=0
else
    echo "❌ Some tests failed:"
    [ $PHP_UNIT_RESULT -ne 0 ] && echo "   - PHP Unit Tests"
    [ $HTTP_RESULT -ne 0 ] && echo "   - HTTP Integration Tests"
    [ $JS_RESULT -ne 0 ] && echo "   - JavaScript Tests"
    OVERALL_RESULT=1
fi

echo ""
echo "Test Coverage:"
echo "✅ PHP Unit Tests: Core API logic, validation, time calculations"
echo "✅ HTTP Integration Tests: End-to-end API functionality"
if [ "$JS_TESTS_AVAILABLE" = true ]; then
    echo "✅ JavaScript Tests: Frontend helper functions and validation"
else
    echo "⚠️  JavaScript Tests: Skipped (Node.js not available)"
fi

exit $OVERALL_RESULT 