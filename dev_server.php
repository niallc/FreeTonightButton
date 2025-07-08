<?php
/**
 * Simple development server for Free Tonight app
 * Run with: php dev_server.php
 */

echo "Starting Free Tonight development server...\n";
echo "Server will be available at: http://localhost:8000/freetonight/\n";
echo "Press Ctrl+C to stop the server\n\n";

// Change to the public directory
chdir(__DIR__ . '/public');

// Start the PHP development server
$command = 'php -S localhost:8000';
echo "Running: $command\n";
system($command); 