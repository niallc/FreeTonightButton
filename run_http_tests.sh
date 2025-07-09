#!/bin/bash

# Run HTTP integration tests for Free Tonight API
# Assumes dev server is running at http://localhost:8002/freetonight/api.php

API_URL="http://localhost:8002/freetonight/api.php"

# Check if dev server is running
if ! curl --output /dev/null --silent --fail "$API_URL" > /dev/null 2>&1; then
  echo -e "\033[31mError: Dev server is not running at $API_URL\033[0m"
  echo "Please start it with: php dev_server.php"
  exit 1
fi

echo -e "\033[34mRunning HTTP integration tests...\033[0m"
php tests/test_api_http.php 