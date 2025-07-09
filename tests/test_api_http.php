<?php
/**
 * HTTP Integration tests for Free Tonight API
 * Run with: php tests/test_api_http.php
 * Assumes dev server is running at http://localhost:8002/freetonight/api.php
 */

$apiUrl = 'http://localhost:8002/freetonight/api.php';

function http_request($method, $url, $data = null) {
    $opts = [
        'http' => [
            'method' => $method,
            'header' => "Content-Type: application/json\r\n",
            'ignore_errors' => true,
        ]
    ];
    if ($data !== null) {
        $opts['http']['content'] = json_encode($data);
    }
    $context = stream_context_create($opts);
    $result = file_get_contents($url, false, $context);
    $statusLine = $http_response_header[0] ?? '';
    preg_match('#HTTP/\d+\.\d+\s+(\d+)#', $statusLine, $matches);
    $status = isset($matches[1]) ? (int)$matches[1] : 0;
    return [
        'status' => $status,
        'body' => $result,
        'json' => json_decode($result, true)
    ];
}

function print_result($desc, $ok, $details = '') {
    if ($ok) {
        echo "\033[32m✓\033[0m $desc\n";
    } else {
        echo "\033[31m✗ $desc\033[0m\n";
        if ($details) echo "    $details\n";
    }
}

$tests = [];

// --- TEST CASES ---

$tests[] = function() use ($apiUrl) {
    // POST valid user
    $name = 'HttpTestUser';
    $resp = http_request('POST', $apiUrl, [
        'name' => $name,
        'activity' => 'Testing',
        'free_in_minutes' => 0,
        'available_for_minutes' => 10
    ]);
    $ok = $resp['status'] === 200 && ($resp['json']['success'] ?? false);
    print_result('POST valid user', $ok, $resp['body']);
};

$tests[] = function() use ($apiUrl) {
    // GET list, should include user
    $resp = http_request('GET', $apiUrl);
    $ok = $resp['status'] === 200 && is_array($resp['json']) &&
        array_reduce($resp['json'], function($carry, $item) {
            return $carry || ($item['name'] ?? null) === 'HttpTestUser';
        }, false);
    print_result('GET list includes user', $ok, $resp['body']);
};

$tests[] = function() use ($apiUrl) {
    // POST invalid user (empty name)
    $resp = http_request('POST', $apiUrl, [
        'name' => '',
        'activity' => 'Testing'
    ]);
    $ok = $resp['status'] === 400 && isset($resp['json']['error']);
    print_result('POST invalid user (empty name)', $ok, $resp['body']);
};

$tests[] = function() use ($apiUrl) {
    // POST invalid user (name too long)
    $resp = http_request('POST', $apiUrl, [
        'name' => str_repeat('a', 51),
        'activity' => 'Testing'
    ]);
    $ok = $resp['status'] === 400 && isset($resp['json']['error']);
    print_result('POST invalid user (name too long)', $ok, $resp['body']);
};

$tests[] = function() use ($apiUrl) {
    // POST invalid activity (too long)
    $resp = http_request('POST', $apiUrl, [
        'name' => 'HttpTestUser2',
        'activity' => str_repeat('a', 101)
    ]);
    $ok = $resp['status'] === 400 && isset($resp['json']['error']);
    print_result('POST invalid activity (too long)', $ok, $resp['body']);
};

$tests[] = function() use ($apiUrl) {
    // DELETE user
    $resp = http_request('DELETE', $apiUrl, [ 'name' => 'HttpTestUser' ]);
    $ok = $resp['status'] === 200 && ($resp['json']['success'] ?? false);
    print_result('DELETE user', $ok, $resp['body']);
};

$tests[] = function() use ($apiUrl) {
    // GET list, user should be gone
    $resp = http_request('GET', $apiUrl);
    $ok = $resp['status'] === 200 && is_array($resp['json']) &&
        !array_reduce($resp['json'], function($carry, $item) {
            return $carry || ($item['name'] ?? null) === 'HttpTestUser';
        }, false);
    print_result('GET list user gone after DELETE', $ok, $resp['body']);
};

// --- RUN TESTS ---

$failures = 0;
foreach ($tests as $test) {
    ob_start();
    $test();
    $output = ob_get_clean();
    echo $output;
    if (strpos($output, '✗') !== false) {
        $failures++;
    }
}

if ($failures === 0) {
    echo "\n\033[32mAll HTTP integration tests passed!\033[0m\n";
} else {
    echo "\n\033[31m$failures HTTP integration test(s) failed.\033[0m\n";
} 