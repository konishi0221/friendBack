<?php
require_once dirname(__DIR__).'/public/core/config.php';
require_once __DIR__.'/cros.php';
require_once dirname(__DIR__).'/public/core/FirestoreDB.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $data['userId'] ?? '';
    $model = $data['model'] ?? '';
    
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing userId parameter']);
        exit;
    }
    
    if (!$model || ($model !== 'gpt-3.5-turbo' && $model !== 'gpt-4o')) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid model parameter. Must be gpt-3.5-turbo or gpt-4o']);
        exit;
    }
    
    $db = FirestoreDB::getInstance();
    $context = $db->getContext($userId);
    $context['model'] = $model;
    $success = $db->saveContext($userId, $context);
    
    if ($success) {
        echo json_encode([
            'userId' => $userId,
            'model' => $model,
            'status' => 'success'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update model preference']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $userId = $_GET['userId'] ?? '';
    
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing userId parameter']);
        exit;
    }
    
    $db = FirestoreDB::getInstance();
    $context = $db->getContext($userId);
    $model = $context['model'] ?? 'gpt-4o'; // Default to GPT-4o
    
    echo json_encode([
        'userId' => $userId,
        'model' => $model
    ]);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
