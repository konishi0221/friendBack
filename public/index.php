<?php
$requestUri = $_SERVER['REQUEST_URI'];

if (strpos($requestUri, '/api/') === 0) {
    $apiPath = substr($requestUri, 5);
    
    $apiFilePath = dirname(__DIR__) . '/api/' . $apiPath;
    
    if (file_exists($apiFilePath)) {
        include $apiFilePath;
        exit;
    }
    
    header('HTTP/1.1 404 Not Found');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'API endpoint not found']);
    exit;
}

include 'index.html';
