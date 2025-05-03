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

$customPrompt = $db->getCustomPrompt($userId);

if (!$customPrompt) {
    $promptFile = __DIR__ . '/prompts/chat_system.txt';
    if (file_exists($promptFile)) {
        $customPrompt = file_get_contents($promptFile);
    } else {
        $customPrompt = "あなたは、役立ち、親しみやすく、会話的であるように設計されたパーソナルAIアシスタントです。";
    }
}

echo json_encode([
    'userId' => $userId,
    'prompt' => $customPrompt
]);
