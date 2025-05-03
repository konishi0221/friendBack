<?php
// Router script for PHP's built-in server
$uri = $_SERVER['REQUEST_URI'];

// Set CORS headers for all requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("HTTP/1.1 200 OK");
    exit;
}

// Handle API requests
if (strpos($uri, '/api/') === 0) {
    // Extract the API endpoint name
    $apiPath = substr($uri, 5);
    $apiEndpoint = strtok($apiPath, '?');
    
    // Map to the actual API file
    $apiFilePath = dirname(__DIR__) . '/api/' . $apiEndpoint;
    
    if (file_exists($apiFilePath)) {
        // Set content type for API responses
        header("Content-Type: application/json; charset=UTF-8");
        
        // Include the API file
        require $apiFilePath;
        exit;
    } else {
        // API endpoint not found
        header('HTTP/1.1 404 Not Found');
        header('Content-Type: application/json');
        echo json_encode(['error' => 'API endpoint not found: ' . $apiEndpoint, 'path' => $apiFilePath]);
        exit;
    }
}

// For static files that exist, return false to let the server handle it
$filePath = __DIR__ . $uri;
if (is_file($filePath)) {
    // For non-API requests, reset content type to let the server decide
    header_remove('Content-Type');
    return false;
}

// For all other requests, serve the frontend
include __DIR__ . '/index.html';
