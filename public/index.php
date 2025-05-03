<?php
$requestUri = $_SERVER['REQUEST_URI'];

if (php_sapi_name() === 'cli-server') {
    $publicPath = __DIR__ . parse_url($requestUri, PHP_URL_PATH);
    if (file_exists($publicPath) && !is_dir($publicPath) && pathinfo($publicPath, PATHINFO_EXTENSION) !== 'php') {
        return false;
    }
}

if (strpos($requestUri, '/api/') === 0) {
    $apiPath = substr($requestUri, 5);
    $apiEndpoint = strtok($apiPath, '?');
    
    $apiFilePath = dirname(__DIR__) . '/api/' . $apiEndpoint;
    
    if (file_exists($apiFilePath)) {
        include $apiFilePath;
        exit;
    } else {
        header('HTTP/1.1 404 Not Found');
        header('Content-Type: application/json');
        echo json_encode(['error' => 'API endpoint not found: ' . $apiEndpoint]);
        exit;
    }
}

include 'index.html';
