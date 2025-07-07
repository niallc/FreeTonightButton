#!/bin/bash

# Deployment script for FreeTonight app
# This script helps set up the proper directory structure on NearlyFreeSpeech

echo "🚀 FreeTonight App Deployment Script"
echo "====================================="

echo ""
echo "📁 Directory Structure:"
echo "   ./public/           (web-accessible files for upload)"
echo "   ./private/freetonight/ (private, writable files)"

echo ""
echo "📋 Files in ./public/ (upload to /home/public/freetonight/):"
echo "   ✅ index.html"
echo "   ✅ style.css" 
echo "   ✅ app.js"
echo "   ✅ api.php"
echo "   ✅ test_api.html (optional)"

echo ""
echo "📋 Files in ./private/freetonight/ (upload to /home/private/freetonight/):"
echo "   ✅ friends.db (will be created automatically)"
echo "   ✅ php_errors.log (will be created automatically)"

echo ""
echo "🔧 Server setup commands (run on server):"
echo "   mkdir -p /home/private/freetonight"
echo "   chmod 755 /home/private/freetonight"
echo "   chmod 666 /home/private/freetonight/friends.db (if exists)"

echo ""
echo "🎯 Key Changes:"
echo "   • Database moved to /home/private/freetonight/friends.db"
echo "   • Error logs moved to /home/private/freetonight/php_errors.log"
echo "   • SQLite can now create journal files in writable directory"

echo ""
echo "✅ Ready for deployment!" 