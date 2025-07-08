<?php
/**
 * Simple test suite for the Free Tonight API
 * Run this from the command line: php tests/test_api.php
 */

// Set HTTP_HOST for CLI environment
$_SERVER['HTTP_HOST'] = 'localhost';

// Include the API file
require_once __DIR__ . '/../public/freetonight/config.php';

class FreeTonightTest {
    private $dbPath;
    private $pdo;
    
    public function __construct() {
        $this->dbPath = DB_PATH;
        $this->setupTestDatabase();
    }
    
    private function setupTestDatabase() {
        // Create a test database
        $this->pdo = new PDO('sqlite:' . $this->dbPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create the table
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS status (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL UNIQUE,
            timestamp INTEGER NOT NULL
        )');
        
        // Clear existing data
        $this->pdo->exec('DELETE FROM status');
    }
    
    public function runAllTests() {
        echo "Running Free Tonight API Tests...\n";
        echo "================================\n\n";
        
        $tests = [
            'testAddUser',
            'testAddUserWithEmptyName',
            'testAddUserWithLongName',
            'testAddUserWithSpecialCharacters',
            'testRemoveUser',
            'testRemoveNonExistentUser',
            'testGetEmptyList',
            'testGetListWithUsers',
            'testExpiredEntries'
        ];
        
        $passed = 0;
        $failed = 0;
        
        foreach ($tests as $test) {
            try {
                $this->$test();
                echo "✓ $test\n";
                $passed++;
            } catch (Exception $e) {
                echo "✗ $test: " . $e->getMessage() . "\n";
                $failed++;
            }
        }
        
        echo "\n================================\n";
        echo "Results: $passed passed, $failed failed\n";
        
        if ($failed === 0) {
            echo "🎉 All tests passed!\n";
        } else {
            echo "❌ Some tests failed!\n";
        }
    }
    
    private function testAddUser() {
        $name = 'TestUser';
        $timestamp = time();
        
        $stmt = $this->pdo->prepare('INSERT INTO status (name, timestamp) VALUES (:name, :timestamp)');
        $stmt->execute(['name' => $name, 'timestamp' => $timestamp]);
        
        $stmt = $this->pdo->prepare('SELECT * FROM status WHERE name = :name');
        $stmt->execute(['name' => $name]);
        $result = $stmt->fetch();
        
        if (!$result || $result['name'] !== $name) {
            throw new Exception('User was not added correctly');
        }
    }
    
    private function testAddUserWithEmptyName() {
        // Test that the database schema allows empty names (this is expected)
        // The API validation will prevent empty names in the actual application
        $stmt = $this->pdo->prepare('INSERT INTO status (name, timestamp) VALUES (:name, :timestamp)');
        $stmt->execute(['name' => '', 'timestamp' => time()]);
        
        // Verify it was added (this is what the database allows)
        $stmt = $this->pdo->prepare('SELECT * FROM status WHERE name = :name');
        $stmt->execute(['name' => '']);
        $result = $stmt->fetch();
        
        if (!$result) {
            throw new Exception('Empty name was not added to database (unexpected)');
        }
        
        // Clean up
        $this->pdo->exec('DELETE FROM status WHERE name = ""');
    }
    
    private function testAddUserWithLongName() {
        // Test that the database schema allows long names (this is expected)
        // The API validation will prevent long names in the actual application
        $longName = str_repeat('a', 51); // 51 characters
        
        $stmt = $this->pdo->prepare('INSERT INTO status (name, timestamp) VALUES (:name, :timestamp)');
        $stmt->execute(['name' => $longName, 'timestamp' => time()]);
        
        // Verify it was added (this is what the database allows)
        $stmt = $this->pdo->prepare('SELECT * FROM status WHERE name = :name');
        $stmt->execute(['name' => $longName]);
        $result = $stmt->fetch();
        
        if (!$result) {
            throw new Exception('Long name was not added to database (unexpected)');
        }
        
        // Clean up
        $stmt = $this->pdo->prepare('DELETE FROM status WHERE name = :name');
        $stmt->execute(['name' => $longName]);
    }
    
    private function testAddUserWithSpecialCharacters() {
        $name = 'Test<User>With<script>alert("xss")</script>';
        $timestamp = time();
        
        $stmt = $this->pdo->prepare('INSERT INTO status (name, timestamp) VALUES (:name, :timestamp)');
        $stmt->execute(['name' => $name, 'timestamp' => $timestamp]);
        
        $stmt = $this->pdo->prepare('SELECT * FROM status WHERE name = :name');
        $stmt->execute(['name' => $name]);
        $result = $stmt->fetch();
        
        if (!$result) {
            throw new Exception('User with special characters was not added');
        }
    }
    
    private function testRemoveUser() {
        $name = 'UserToRemove';
        $timestamp = time();
        
        // Add user first
        $stmt = $this->pdo->prepare('INSERT INTO status (name, timestamp) VALUES (:name, :timestamp)');
        $stmt->execute(['name' => $name, 'timestamp' => $timestamp]);
        
        // Remove user
        $stmt = $this->pdo->prepare('DELETE FROM status WHERE name = :name');
        $stmt->execute(['name' => $name]);
        
        // Verify user is gone
        $stmt = $this->pdo->prepare('SELECT * FROM status WHERE name = :name');
        $stmt->execute(['name' => $name]);
        $result = $stmt->fetch();
        
        if ($result) {
            throw new Exception('User was not removed');
        }
    }
    
    private function testRemoveNonExistentUser() {
        $name = 'NonExistentUser';
        
        $stmt = $this->pdo->prepare('DELETE FROM status WHERE name = :name');
        $stmt->execute(['name' => $name]);
        
        // Should not throw an error
    }
    
    private function testGetEmptyList() {
        // Clear the table
        $this->pdo->exec('DELETE FROM status');
        
        $start_of_day = strtotime('today', time());
        $stmt = $this->pdo->prepare('SELECT * FROM status WHERE timestamp >= :start_of_day');
        $stmt->execute(['start_of_day' => $start_of_day]);
        
        $results = $stmt->fetchAll();
        
        if (count($results) !== 0) {
            throw new Exception('Empty list should return no results');
        }
    }
    
    private function testGetListWithUsers() {
        // Add some test users
        $users = [
            ['name' => 'Alice', 'timestamp' => time()],
            ['name' => 'Bob', 'timestamp' => time() - 300],
            ['name' => 'Charlie', 'timestamp' => time() - 600]
        ];
        
        foreach ($users as $user) {
            $stmt = $this->pdo->prepare('INSERT INTO status (name, timestamp) VALUES (:name, :timestamp)');
            $stmt->execute($user);
        }
        
        $start_of_day = strtotime('today', time());
        $stmt = $this->pdo->prepare('SELECT * FROM status WHERE timestamp >= :start_of_day ORDER BY timestamp DESC');
        $stmt->execute(['start_of_day' => $start_of_day]);
        
        $results = $stmt->fetchAll();
        
        if (count($results) !== 3) {
            throw new Exception('Should return 3 users, got ' . count($results));
        }
    }
    
    private function testExpiredEntries() {
        // Add a user from yesterday
        $yesterday = strtotime('yesterday', time());
        $stmt = $this->pdo->prepare('INSERT INTO status (name, timestamp) VALUES (:name, :timestamp)');
        $stmt->execute(['name' => 'OldUser', 'timestamp' => $yesterday]);
        
        $start_of_day = strtotime('today', time());
        $stmt = $this->pdo->prepare('SELECT * FROM status WHERE timestamp >= :start_of_day');
        $stmt->execute(['start_of_day' => $start_of_day]);
        
        $results = $stmt->fetchAll();
        
        foreach ($results as $result) {
            if ($result['name'] === 'OldUser') {
                throw new Exception('Old user should not appear in today\'s list');
            }
        }
    }
}

// Run the tests
$test = new FreeTonightTest();
$test->runAllTests(); 