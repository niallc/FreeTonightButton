<?php
header('Content-Type: application/json');

// Log all requests
error_log("TEST: " . $_SERVER['REQUEST_METHOD'] . " request received");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    error_log("TEST: POST data received: " . $input);
    echo json_encode(['success' => true, 'method' => 'POST', 'data' => $input]);
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['success' => true, 'method' => 'GET']);
} else {
    echo json_encode(['success' => false, 'method' => $_SERVER['REQUEST_METHOD']]);
}
?> 