<?php
require_once dirname(__DIR__).'/public/core/config.php';
require_once __DIR__.'/cros.php';
require_once dirname(__DIR__).'/public/core/FirestoreDB.php';

header('Content-Type: application/json');

$userId = $_GET['userId'] ?? '';

if (!$userId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing userId parameter']);
    exit;
}

$db = FirestoreDB::getInstance();

$context = $db->getContext($userId);

echo json_encode([
    'userId' => $userId,
    'context' => $context
]);
