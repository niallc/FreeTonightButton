<?php
/**
 * HTTP Integration Tests for Group Functionality
 * Run with: php tests/test_groups_http.php [--verbose]
 * 
 * Note: Requires the dev server to be running on localhost:8002
 */

// Parse command line arguments
$verbose = in_array('--verbose', $argv);

// Set HTTP_HOST for CLI environment
$_SERVER['HTTP_HOST'] = 'localhost';

// Include the API file for constants
require_once __DIR__ . '/../public/freetonight/config.php';

class GroupHttpTest {
    private $baseUrl = 'http://localhost:8002/freetonight';
    private $testGroupName;
    
    public function __construct() {
        $this->testGroupName = 'http-test-' . time();
    }
    
    public function runAllTests() {
        global $verbose;
        
        echo "Running Group HTTP Integration Tests...\n";
        echo "=====================================\n\n";
        
        if ($verbose) {
            echo "Verbose mode enabled\n";
            echo "Base URL: " . $this->baseUrl . "\n\n";
        }
        
        // Check if server is running
        if (!$this->checkServerRunning()) {
            echo "❌ Server not running on localhost:8002\n";
            echo "Please start the dev server with: php dev_server.php\n";
            return;
        }
        
        $tests = [
            'testGroupNameValidation',
            'testCreateGroup',
            'testCreateDuplicateGroup',
            'testCreateGroupWithReservedName',
            'testCreateGroupWithInvalidName',
            'testDeleteGroup',
            'testDeleteNonExistentGroup',
            'testGroupDataIsolation',
            'testListGroups',
            'testGroupRouting'
        ];
        
        $passed = 0;
        $failed = 0;
        $startTime = microtime(true);
        
        foreach ($tests as $test) {
            try {
                if ($verbose) {
                    echo "Running $test...\n";
                }
                
                $this->$test();
                echo "✓ $test\n";
                $passed++;
            } catch (Exception $e) {
                echo "✗ $test: " . $e->getMessage() . "\n";
                if ($verbose) {
                    echo "  Stack trace: " . $e->getTraceAsString() . "\n";
                }
                $failed++;
            }
        }
        
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        
        echo "\n=====================================\n";
        echo "Results: $passed passed, $failed failed ({$duration}s)\n";
        
        if ($failed === 0) {
            echo "🎉 All group HTTP tests passed!\n";
        } else {
            echo "❌ Some group HTTP tests failed!\n";
        }
    }
    
    private function checkServerRunning() {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . '/api.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode === 200;
    }
    
    private function makeRequest($url, $method = 'GET', $data = null) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            }
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("cURL error: $error");
        }
        
        return [
            'code' => $httpCode,
            'body' => $response
        ];
    }
    
    private function testGroupNameValidation() {
        // Test valid group names
        $validNames = ['test-group', 'test_group', 'TestGroup123'];
        
        foreach ($validNames as $name) {
            $response = $this->makeRequest($this->baseUrl . '/api.php', 'POST', [
                'action' => 'create_group',
                'group_name' => $name,
                'display_name' => 'Test Group'
            ]);
            
            if ($response['code'] !== 200) {
                throw new Exception("Valid group name '$name' was rejected");
            }
            
            $data = json_decode($response['body'], true);
            if (!$data || !isset($data['success'])) {
                throw new Exception("Invalid JSON response for '$name'");
            }
        }
        
        // Test invalid group names
        $invalidNames = ['', 'invalid group', 'invalid@group', str_repeat('a', 21)];
        
        foreach ($invalidNames as $name) {
            $response = $this->makeRequest($this->baseUrl . '/api.php', 'POST', [
                'action' => 'create_group',
                'group_name' => $name,
                'display_name' => 'Test Group'
            ]);
            
            if ($response['code'] !== 200) {
                continue; // Server error is acceptable for invalid names
            }
            
            $data = json_decode($response['body'], true);
            if ($data && isset($data['success']) && $data['success']) {
                throw new Exception("Invalid group name '$name' was accepted");
            }
        }
    }
    
    private function testCreateGroup() {
        $groupName = $this->testGroupName . '-create';
        $displayName = 'HTTP Test Group';
        
        $response = $this->makeRequest($this->baseUrl . '/api.php', 'POST', [
            'action' => 'create_group',
            'group_name' => $groupName,
            'display_name' => $displayName
        ]);
        
        if ($response['code'] !== 200) {
            throw new Exception("Failed to create group: HTTP " . $response['code']);
        }
        
        $data = json_decode($response['body'], true);
        if (!$data || !isset($data['success'])) {
            throw new Exception("Invalid JSON response for group creation");
        }
        
        if (!$data['success']) {
            throw new Exception("Group creation failed: " . ($data['error'] ?? 'Unknown error'));
        }
        
        // Verify group was created by listing groups
        $response = $this->makeRequest($this->baseUrl . '/api.php', 'POST', [
            'action' => 'list_groups'
        ]);
        
        if ($response['code'] !== 200) {
            throw new Exception("Failed to list groups: HTTP " . $response['code']);
        }
        
        $data = json_decode($response['body'], true);
        if (!$data || !isset($data['groups'])) {
            throw new Exception("Invalid JSON response for group listing");
        }
        
        $groupFound = false;
        foreach ($data['groups'] as $group) {
            if ($group['name'] === $groupName) {
                $groupFound = true;
                break;
            }
        }
        
        if (!$groupFound) {
            throw new Exception("Created group not found in group list");
        }
    }
    
    private function testCreateDuplicateGroup() {
        $groupName = $this->testGroupName . '-duplicate';
        
        // Create first group
        $response = $this->makeRequest($this->baseUrl . '/api.php', 'POST', [
            'action' => 'create_group',
            'group_name' => $groupName,
            'display_name' => 'First Group'
        ]);
        
        if ($response['code'] !== 200) {
            throw new Exception("Failed to create first group");
        }
        
        // Try to create duplicate
        $response = $this->makeRequest($this->baseUrl . '/api.php', 'POST', [
            'action' => 'create_group',
            'group_name' => $groupName,
            'display_name' => 'Second Group'
        ]);
        
        if ($response['code'] !== 200) {
            throw new Exception("Duplicate group creation should return 200");
        }
        
        $data = json_decode($response['body'], true);
        if ($data && isset($data['success']) && $data['success']) {
            throw new Exception("Duplicate group creation should fail");
        }
    }
    
    private function testCreateGroupWithReservedName() {
        $reservedNames = RESERVED_GROUP_NAMES;
        
        foreach ($reservedNames as $name) {
            $response = $this->makeRequest($this->baseUrl . '/api.php', 'POST', [
                'action' => 'create_group',
                'group_name' => $name,
                'display_name' => 'Reserved Group'
            ]);
            
            if ($response['code'] !== 200) {
                continue; // Server error is acceptable for reserved names
            }
            
            $data = json_decode($response['body'], true);
            if ($data && isset($data['success']) && $data['success']) {
                throw new Exception("Reserved name '$name' was accepted");
            }
        }
    }
    
    private function testCreateGroupWithInvalidName() {
        $invalidNames = ['', 'invalid group', 'invalid@group', str_repeat('a', 21)];
        
        foreach ($invalidNames as $name) {
            $response = $this->makeRequest($this->baseUrl . '/api.php', 'POST', [
                'action' => 'create_group',
                'group_name' => $name,
                'display_name' => 'Invalid Group'
            ]);
            
            if ($response['code'] !== 200) {
                continue; // Server error is acceptable for invalid names
            }
            
            $data = json_decode($response['body'], true);
            if ($data && isset($data['success']) && $data['success']) {
                throw new Exception("Invalid name '$name' was accepted");
            }
        }
    }
    
    private function testDeleteGroup() {
        $groupName = $this->testGroupName . '-delete';
        
        // Create group first
        $response = $this->makeRequest($this->baseUrl . '/api.php', 'POST', [
            'action' => 'create_group',
            'group_name' => $groupName,
            'display_name' => 'Delete Test Group'
        ]);
        
        if ($response['code'] !== 200) {
            throw new Exception("Failed to create group for deletion test");
        }
        
        // Add some data to the group
        $response = $this->makeRequest($this->baseUrl . '/api.php', 'POST', [
            'action' => 'set_free',
            'name' => 'TestUser',
            'activity' => 'Testing',
            'group_name' => $groupName
        ]);
        
        if ($response['code'] !== 200) {
            throw new Exception("Failed to add data to group");
        }
        
        // Delete group
        $response = $this->makeRequest($this->baseUrl . '/api.php', 'POST', [
            'action' => 'delete_group',
            'group_name' => $groupName
        ]);
        
        if ($response['code'] !== 200) {
            throw new Exception("Failed to delete group: HTTP " . $response['code']);
        }
        
        $data = json_decode($response['body'], true);
        if (!$data || !isset($data['success'])) {
            throw new Exception("Invalid JSON response for group deletion");
        }
        
        if (!$data['success']) {
            throw new Exception("Group deletion failed: " . ($data['error'] ?? 'Unknown error'));
        }
        
        // Verify group is gone
        $response = $this->makeRequest($this->baseUrl . '/api.php', 'POST', [
            'action' => 'list_groups'
        ]);
        
        $data = json_decode($response['body'], true);
        if ($data && isset($data['groups'])) {
            foreach ($data['groups'] as $group) {
                if ($group['name'] === $groupName) {
                    throw new Exception("Deleted group still exists in group list");
                }
            }
        }
    }
    
    private function testDeleteNonExistentGroup() {
        $nonExistentGroup = 'non-existent-' . time();
        
        $response = $this->makeRequest($this->baseUrl . '/api.php', 'POST', [
            'action' => 'delete_group',
            'group_name' => $nonExistentGroup
        ]);
        
        if ($response['code'] !== 200) {
            throw new Exception("Delete non-existent group should return 200");
        }
        
        $data = json_decode($response['body'], true);
        if ($data && isset($data['success']) && $data['success']) {
            throw new Exception("Deleting non-existent group should fail");
        }
    }
    
    private function testGroupDataIsolation() {
        $group1 = $this->testGroupName . '-isolation-1';
        $group2 = $this->testGroupName . '-isolation-2';
        
        // Create groups
        $response = $this->makeRequest($this->baseUrl . '/api.php', 'POST', [
            'action' => 'create_group',
            'group_name' => $group1,
            'display_name' => 'Isolation Test 1'
        ]);
        
        if ($response['code'] !== 200) {
            throw new Exception("Failed to create group 1");
        }
        
        $response = $this->makeRequest($this->baseUrl . '/api.php', 'POST', [
            'action' => 'create_group',
            'group_name' => $group2,
            'display_name' => 'Isolation Test 2'
        ]);
        
        if ($response['code'] !== 200) {
            throw new Exception("Failed to create group 2");
        }
        
        // Add data to group1
        $response = $this->makeRequest($this->baseUrl . '/api.php', 'POST', [
            'action' => 'set_free',
            'name' => 'User1',
            'activity' => 'Activity1',
            'group_name' => $group1
        ]);
        
        if ($response['code'] !== 200) {
            throw new Exception("Failed to add data to group 1");
        }
        
        // Add data to group2
        $response = $this->makeRequest($this->baseUrl . '/api.php', 'POST', [
            'action' => 'set_free',
            'name' => 'User2',
            'activity' => 'Activity2',
            'group_name' => $group2
        ]);
        
        if ($response['code'] !== 200) {
            throw new Exception("Failed to add data to group 2");
        }
        
        // Check group1 data
        $response = $this->makeRequest($this->baseUrl . '/api.php', 'POST', [
            'action' => 'get_free',
            'group_name' => $group1
        ]);
        
        if ($response['code'] !== 200) {
            throw new Exception("Failed to get group 1 data");
        }
        
        $data = json_decode($response['body'], true);
        if (!$data || !isset($data['people'])) {
            throw new Exception("Invalid JSON response for group 1 data");
        }
        
        $user1Found = false;
        foreach ($data['people'] as $person) {
            if ($person['name'] === 'User1') {
                $user1Found = true;
                break;
            }
        }
        
        if (!$user1Found) {
            throw new Exception("User1 not found in group 1 data");
        }
        
        // Check group2 data
        $response = $this->makeRequest($this->baseUrl . '/api.php', 'POST', [
            'action' => 'get_free',
            'group_name' => $group2
        ]);
        
        if ($response['code'] !== 200) {
            throw new Exception("Failed to get group 2 data");
        }
        
        $data = json_decode($response['body'], true);
        if (!$data || !isset($data['people'])) {
            throw new Exception("Invalid JSON response for group 2 data");
        }
        
        $user2Found = false;
        foreach ($data['people'] as $person) {
            if ($person['name'] === 'User2') {
                $user2Found = true;
                break;
            }
        }
        
        if (!$user2Found) {
            throw new Exception("User2 not found in group 2 data");
        }
        
        // Verify User1 is NOT in group2
        $user1InGroup2 = false;
        foreach ($data['people'] as $person) {
            if ($person['name'] === 'User1') {
                $user1InGroup2 = true;
                break;
            }
        }
        
        if ($user1InGroup2) {
            throw new Exception("User1 found in group 2 data - isolation failed");
        }
    }
    
    private function testListGroups() {
        $response = $this->makeRequest($this->baseUrl . '/api.php', 'POST', [
            'action' => 'list_groups'
        ]);
        
        if ($response['code'] !== 200) {
            throw new Exception("Failed to list groups: HTTP " . $response['code']);
        }
        
        $data = json_decode($response['body'], true);
        if (!$data || !isset($data['groups'])) {
            throw new Exception("Invalid JSON response for group listing");
        }
        
        if (!is_array($data['groups'])) {
            throw new Exception("Groups should be an array");
        }
        
        // Verify default group exists
        $defaultGroupFound = false;
        foreach ($data['groups'] as $group) {
            if ($group['name'] === DEFAULT_GROUP_NAME) {
                $defaultGroupFound = true;
                break;
            }
        }
        
        if (!$defaultGroupFound) {
            throw new Exception("Default group not found in group list");
        }
    }
    
    private function testGroupRouting() {
        // Test that the main page loads
        $response = $this->makeRequest($this->baseUrl . '/');
        
        if ($response['code'] !== 200) {
            throw new Exception("Main page failed to load: HTTP " . $response['code']);
        }
        
        // Test that the page contains group-related JavaScript
        if (strpos($response['body'], 'currentGroup') === false) {
            throw new Exception("Page does not contain group functionality");
        }
        
        if (strpos($response['body'], 'createGroup') === false) {
            throw new Exception("Page does not contain group creation functionality");
        }
        
        if (strpos($response['body'], 'deleteGroup') === false) {
            throw new Exception("Page does not contain group deletion functionality");
        }
    }
}

// Run the tests
$test = new GroupHttpTest();
$test->runAllTests(); 