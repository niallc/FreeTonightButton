<?php
// Test the API with the new private database path
header('Content-Type: application/json');

echo "Testing API with private database path...\n";

// Test database connection
$db_file = '/home/private/freetonight/friends.db';

if (file_exists($db_file)) {
    echo "✅ Database file exists at: $db_file\n";
} else {
    echo "❌ Database file not found at: $db_file\n";
    echo "This is expected on local development - the path is for server deployment.\n";
}

// Test if we can create the directory structure locally
$test_dir = 'private/freetonight';
if (!is_dir($test_dir)) {
    mkdir($test_dir, 0755, true);
    echo "✅ Created test directory: $test_dir\n";
} else {
    echo "✅ Test directory exists: $test_dir\n";
}

// Test local database connection
$local_db = 'private/freetonight/friends.db';
try {
    $pdo = new PDO('sqlite:' . $local_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Local database connection successful\n";
    
    // Test a simple query
    $stmt = $pdo->query('SELECT COUNT(*) FROM status');
    $count = $stmt->fetchColumn();
    echo "✅ Database query successful - $count records in status table\n";
    
} catch (PDOException $e) {
    echo "❌ Local database error: " . $e->getMessage() . "\n";
}

echo "\n🎯 Ready for server deployment!\n";
echo "Upload files to /home/public/freetonight/\n";
echo "Create /home/private/freetonight/ on server\n";
?> 